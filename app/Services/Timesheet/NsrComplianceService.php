<?php

declare(strict_types=1);

namespace App\Services\Timesheet;

use CodeIgniter\Database\BaseConnection;

class NsrComplianceService
{
    private string $eventLogPath;

    public function __construct(
        private readonly BaseConnection $db,
        ?string $eventLogPath = null,
    ) {
        $this->eventLogPath = $eventLogPath ?? WRITEPATH . 'compliance/nsr_contingency_events.json';
    }

    public static function createDefault(): self
    {
        return new self(db_connect());
    }

    public function recordFallbackEvent(string $reason, array $context = []): void
    {
        $events = $this->readEvents();
        $event = [
            'id' => bin2hex(random_bytes(12)),
            'type' => 'nsr_fallback',
            'reason' => $reason,
            'recorded_at' => gmdate(DATE_ATOM),
            'context' => $this->sanitizeContext($context),
        ];

        array_unshift($events, $event);
        $events = array_slice($events, 0, 250);

        $directory = dirname($this->eventLogPath);
        if (! is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        @file_put_contents($this->eventLogPath, json_encode($events, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function recentFallbackEvents(int $days = 30): array
    {
        $cutoff = strtotime('-' . max(1, $days) . ' days');

        return array_values(array_filter($this->readEvents(), static function (array $event) use ($cutoff): bool {
            $recordedAt = strtotime((string) ($event['recorded_at'] ?? ''));
            return $recordedAt !== false && $recordedAt >= $cutoff;
        }));
    }

    public function recentNsrGaps(int $days = 30): array
    {
        $from = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));

        // O NSR e compartilhado entre time_punches, employee_record_events, clock_adjustments,
        // rep_availability_events e company_record_events (mesma sequencia atomica — ver
        // migracao 2026-06-07-000489). A query de gaps precisa considerar TODAS as tabelas
        // para nao classificar como lacuna um NSR legitimamente consumido por outro tipo de
        // evento (ALTO-04 na auditoria: company_record_events ficou de fora aqui, gerando
        // falsos positivos de "gap" sempre que uma alteracao cadastral da empresa consumia
        // um NSR entre duas batidas de ponto).
        $sql = "
            SELECT nsr, LAG(nsr) OVER (ORDER BY nsr) AS prev_nsr, recorded_at AS event_time, source_table
            FROM (
                SELECT CAST(nsr AS BIGINT) AS nsr, created_at AS recorded_at, 'time_punches' AS source_table
                FROM time_punches WHERE nsr IS NOT NULL AND created_at >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), recorded_at, 'employee_record_events'
                FROM employee_record_events WHERE nsr IS NOT NULL AND recorded_at >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), adjusted_datetime AS recorded_at, 'clock_adjustments'
                FROM clock_adjustments WHERE nsr IS NOT NULL AND adjusted_datetime >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), recorded_at, 'rep_availability_events'
                FROM rep_availability_events WHERE nsr IS NOT NULL AND recorded_at >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), recorded_at, 'company_record_events'
                FROM company_record_events WHERE nsr IS NOT NULL AND recorded_at >= ?
            ) all_events
            ORDER BY nsr ASC
        ";

        $rows = $this->db->query($sql, [$from, $from, $from, $from, $from])->getResult();

        $issues = [];
        foreach ($rows as $row) {
            $current  = (int) ($row->nsr ?? 0);
            $previous = $row->prev_nsr !== null ? (int) $row->prev_nsr : null;
            if ($previous !== null && ($current - $previous) > 1) {
                $issues[] = [
                    'gap_from'      => $previous,
                    'gap_to'        => $current,
                    'missing_count' => max(0, $current - $previous - 1),
                    'date'          => $row->event_time,
                    'source_table'  => $row->source_table,
                ];
            }
        }

        return $issues;
    }

    /**
     * Duplicatas de NSR entre QUALQUER uma das 5 tabelas que compartilham a mesma
     * sequência canônica — não apenas time_punches. Checar só time_punches aqui
     * deixava passar despercebida uma duplicata real entre employee_record_events
     * e rep_availability_events (mesmo NSR usado em duas tabelas diferentes),
     * já que o count-by-nsr nunca via as duas linhas juntas (ver CreateNsrLedgerTable
     * para o achado e a rede de segurança adicionada para novos NSRs).
     */
    public function recentDuplicateNsrs(int $days = 30): array
    {
        $from = date('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));

        $sql = "
            SELECT nsr, COUNT(*) AS total,
                   MIN(recorded_at) AS first_occurrence,
                   MAX(recorded_at) AS last_occurrence,
                   STRING_AGG(DISTINCT source_table, ', ' ORDER BY source_table) AS source_tables
            FROM (
                SELECT CAST(nsr AS BIGINT) AS nsr, created_at AS recorded_at, 'time_punches' AS source_table
                FROM time_punches WHERE nsr IS NOT NULL AND created_at >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), recorded_at, 'employee_record_events'
                FROM employee_record_events WHERE nsr IS NOT NULL AND recorded_at >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), adjusted_datetime AS recorded_at, 'clock_adjustments'
                FROM clock_adjustments WHERE nsr IS NOT NULL AND adjusted_datetime >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), recorded_at, 'rep_availability_events'
                FROM rep_availability_events WHERE nsr IS NOT NULL AND recorded_at >= ?
                UNION ALL
                SELECT CAST(nsr AS BIGINT), recorded_at, 'company_record_events'
                FROM company_record_events WHERE nsr IS NOT NULL AND recorded_at >= ?
            ) all_events
            GROUP BY nsr
            HAVING COUNT(*) > 1
            ORDER BY nsr ASC
        ";

        $rows = $this->db->query($sql, [$from, $from, $from, $from, $from])->getResultArray();

        return array_map(static fn(array $row): array => [
            'nsr' => (int) ($row['nsr'] ?? 0),
            'count' => (int) ($row['total'] ?? 0),
            'source_tables' => (string) ($row['source_tables'] ?? ''),
            'first_occurrence' => $row['first_occurrence'] ?? null,
            'last_occurrence' => $row['last_occurrence'] ?? null,
        ], $rows);
    }

    public function counterHealth(): array
    {
        try {
            $counterRow = $this->db->table('nsr_counter')->select('value')->where('id', 1)->get()->getRowArray();
            $counterValue = isset($counterRow['value']) ? (int) $counterRow['value'] : null;

            // ALTO-04 (auditoria): usar so MAX(time_punches.nsr) subestimava o maior NSR
            // persistido sempre que a marcacao mais recente da sequencia era, na verdade,
            // um evento de outra tabela (alteracao cadastral, ajuste de relogio etc.) — o
            // que fazia counterHealth() acusar "drift" positivo indevido (contador
            // supostamente "avancado demais") mesmo com o contador perfeitamente coerente.
            $maxValue = (int) ($this->db->query(
                "SELECT MAX(nsr) AS max_nsr FROM (
                    SELECT MAX(CAST(nsr AS BIGINT)) AS nsr FROM time_punches WHERE nsr IS NOT NULL
                    UNION ALL
                    SELECT MAX(CAST(nsr AS BIGINT)) FROM employee_record_events WHERE nsr IS NOT NULL
                    UNION ALL
                    SELECT MAX(CAST(nsr AS BIGINT)) FROM clock_adjustments WHERE nsr IS NOT NULL
                    UNION ALL
                    SELECT MAX(CAST(nsr AS BIGINT)) FROM rep_availability_events WHERE nsr IS NOT NULL
                    UNION ALL
                    SELECT MAX(CAST(nsr AS BIGINT)) FROM company_record_events WHERE nsr IS NOT NULL
                ) all_max"
            )->getRow()->max_nsr ?? 0);

            $drift = $counterValue === null ? null : ($counterValue - $maxValue);
            $status = 'ok';
            $message = 'Contador NSR coerente com o último registro persistido.';

            if ($counterValue === null) {
                $status = 'warning';
                $message = 'Tabela nsr_counter sem registro inicial; fallback regulatório pode ser acionado.';
            } elseif ($drift < 0) {
                $status = 'error';
                $message = 'Contador NSR está atrás do maior NSR persistido. Verifique concorrência e migrações.';
            } elseif ($drift > 25) {
                $status = 'warning';
                $message = 'Contador NSR avançou além da janela esperada. Verifique contingências ou descartes indevidos.';
            }

            return [
                'status' => $status,
                'message' => $message,
                'counter_value' => $counterValue,
                'max_persisted_nsr' => $maxValue,
                'drift' => $drift,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Não foi possível validar a saúde do contador NSR.',
                'error' => $e->getMessage(),
                'counter_value' => null,
                'max_persisted_nsr' => null,
                'drift' => null,
            ];
        }
    }

    public function contingencySummary(int $days = 30): array
    {
        $gaps = $this->recentNsrGaps($days);
        $duplicates = $this->recentDuplicateNsrs($days);
        $fallbackEvents = $this->recentFallbackEvents($days);
        $counterHealth = $this->counterHealth();

        $alerts = [];
        if (! empty($fallbackEvents)) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => count($fallbackEvents) . ' fallback(s) de NSR registrados nos últimos ' . $days . ' dias.',
            ];
        }
        if (! empty($duplicates)) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => count($duplicates) . ' NSR(s) duplicados detectados na janela monitorada.',
            ];
        }
        if (! empty($gaps)) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => count($gaps) . ' gap(s) de NSR detectados na janela monitorada.',
            ];
        }
        if (($counterHealth['status'] ?? 'error') !== 'ok') {
            $alerts[] = [
                'severity' => (($counterHealth['status'] ?? 'error') === 'error') ? 'critical' : 'warning',
                'message' => (string) ($counterHealth['message'] ?? 'Anomalia no contador NSR.'),
            ];
        }

        $status = 'ok';
        foreach ($alerts as $alert) {
            if (($alert['severity'] ?? '') === 'critical') {
                $status = 'error';
                break;
            }
            $status = 'warning';
        }

        return [
            'status' => $status,
            'fallback_events_count' => count($fallbackEvents),
            'latest_fallback_event' => $fallbackEvents[0] ?? null,
            'nsr_gaps_count' => count($gaps),
            'nsr_gaps' => $gaps,
            'duplicate_nsrs_count' => count($duplicates),
            'duplicate_nsrs' => $duplicates,
            'counter_health' => $counterHealth,
            'alerts' => $alerts,
        ];
    }

    private function readEvents(): array
    {
        if (! is_file($this->eventLogPath)) {
            return [];
        }

        $content = @file_get_contents($this->eventLogPath);
        if (! is_string($content) || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function sanitizeContext(array $context): array
    {
        helper('observability');
        return supportponto_sanitize_log_context($context);
    }
}
