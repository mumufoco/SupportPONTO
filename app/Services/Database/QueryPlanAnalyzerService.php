<?php

namespace App\Services\Database;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Pacote 448 — análise opcional de planos de consulta.
 *
 * Não executa alterações no banco. Usa EXPLAIN (FORMAT JSON) somente em PostgreSQL
 * e retorna um diagnóstico simples para consultas críticas de ponto/relatórios.
 */
class QueryPlanAnalyzerService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @return array<string,mixed>
     */
    public function explain(string $sql, array $binds = []): array
    {
        if ($this->db->DBDriver !== 'Postgre') {
            return [
                'success' => false,
                'reason' => 'EXPLAIN JSON disponível somente para PostgreSQL neste projeto.',
            ];
        }

        if (! $this->isSafeSelect($sql)) {
            return [
                'success' => false,
                'reason' => 'Somente SELECT é permitido para análise de plano.',
            ];
        }

        try {
            $result = $this->db->query('EXPLAIN (FORMAT JSON, COSTS TRUE, VERBOSE FALSE, BUFFERS FALSE) ' . $sql, $binds);
            $row = $result->getFirstRow('array');
            $json = $row['QUERY PLAN'] ?? reset($row);
            $plan = is_string($json) ? json_decode($json, true) : null;

            return [
                'success' => true,
                'uses_index' => $this->planUsesIndex($plan),
                'has_sequential_scan' => $this->planHasNode($plan, 'Seq Scan'),
                'plan' => $plan,
            ];
        } catch (\Throwable $e) {
            log_message('warning', '[Package448] Falha ao gerar EXPLAIN: ' . $e->getMessage());

            return [
                'success' => false,
                'reason' => 'Falha ao gerar EXPLAIN: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public function explainCriticalQueries(string $startDate, string $endDate, ?int $employeeId = null): array
    {
        $employeeFilter = $employeeId !== null ? ' AND employee_id = ?' : '';
        $binds = [$startDate . ' 00:00:00', date('Y-m-d H:i:s', strtotime($endDate . ' +1 day'))];
        if ($employeeId !== null) {
            $binds[] = $employeeId;
        }

        return [
            'time_punches_period' => $this->explain(
                'SELECT id, employee_id, punch_time, punch_type, method, status FROM time_punches WHERE punch_time >= ? AND punch_time < ?' . $employeeFilter . ' ORDER BY punch_time ASC LIMIT 500',
                $binds
            ),
            'timesheet_consolidated_period' => $this->explain(
                'SELECT employee_id, date, total_worked, expected, extra, owed FROM timesheet_consolidated WHERE date >= ? AND date <= ?' . ($employeeId !== null ? ' AND employee_id = ?' : '') . ' ORDER BY date ASC LIMIT 500',
                $employeeId !== null ? [$startDate, $endDate, $employeeId] : [$startDate, $endDate]
            ),
        ];
    }

    private function isSafeSelect(string $sql): bool
    {
        $normalized = ltrim(preg_replace('/\s+/', ' ', trim($sql)) ?? '');
        if (! str_starts_with(strtolower($normalized), 'select ')) {
            return false;
        }

        return ! preg_match('/\b(insert|update|delete|drop|alter|truncate|create|grant|revoke|copy|call)\b/i', $normalized);
    }

    private function planUsesIndex(mixed $node): bool
    {
        return $this->planHasNode($node, 'Index Scan')
            || $this->planHasNode($node, 'Index Only Scan')
            || $this->planHasNode($node, 'Bitmap Index Scan');
    }

    private function planHasNode(mixed $node, string $nodeType): bool
    {
        if (is_array($node)) {
            if (($node['Node Type'] ?? null) === $nodeType) {
                return true;
            }

            foreach ($node as $child) {
                if ($this->planHasNode($child, $nodeType)) {
                    return true;
                }
            }
        }

        return false;
    }
}
