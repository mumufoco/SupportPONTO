<?php

namespace App\Services\Timesheet;

use CodeIgniter\Database\BaseConnection;

/**
 * Canonical NSR generator for time-punch records.
 *
 * The NSR must be generated from a single atomic source. This service replaces
 * legacy trigger/sequence and SELECT MAX fallbacks with one PostgreSQL-backed
 * counter table. Failures are explicit because silent fallback can create
 * duplicated or out-of-order NSR values under concurrency.
 */
class NsrGeneratorService
{
    private const COUNTER_ID = 1;
    private const MAX_COLLISION_RETRIES = 20;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * @param string|null $source Nome da tabela/evento que vai consumir este NSR
     *                            (ex.: 'time_punches') — apenas para diagnóstico em nsr_ledger.
     *
     * Cada NSR emitido é imediatamente gravado em nsr_ledger, cuja chave primária
     * garante unicidade GLOBAL entre as 5 tabelas que compartilham a sequência
     * (time_punches, clock_adjustments, rep_availability_events,
     * employee_record_events, company_record_events). Se nsr_counter algum dia for
     * reinicializado por engano (ex.: recriação manual da tabela) e reemitir um
     * valor já usado, o insert no ledger falha por violação de unicidade — o
     * gerador detecta isso e avança o contador automaticamente até encontrar um
     * valor realmente livre, em vez de devolver silenciosamente um NSR duplicado.
     */
    public function next(?string $source = null): int
    {
        for ($attempt = 0; $attempt < self::MAX_COLLISION_RETRIES; $attempt++) {
            $query = $this->db->query(
                'INSERT INTO nsr_counter (id, value, updated_at) VALUES (?, 1, CURRENT_TIMESTAMP)
                 ON CONFLICT (id) DO UPDATE SET value = nsr_counter.value + 1, updated_at = CURRENT_TIMESTAMP
                 RETURNING value',
                [self::COUNTER_ID]
            );

            $row = $query->getRow();
            if (! $row || ! isset($row->value)) {
                throw new \RuntimeException('Falha ao gerar NSR atômico: contador não retornou valor.');
            }

            $nsr = (int) $row->value;
            if ($nsr < 1) {
                throw new \RuntimeException('Falha ao gerar NSR atômico: valor inválido retornado.');
            }

            try {
                $this->db->query(
                    'INSERT INTO nsr_ledger (nsr, source, issued_at) VALUES (?, ?, CURRENT_TIMESTAMP)',
                    [$nsr, $source]
                );

                return $nsr;
            } catch (\Throwable $e) {
                // Violação de unicidade: nsr_counter reemitiu um valor já registrado no
                // ledger. Não silenciar — loga como crítico e tenta o próximo valor.
                log_message('critical', '[NsrGeneratorService] Colisão de NSR detectada e evitada: valor {nsr} já emitido anteriormente (source={source}). Verifique se nsr_counter foi reinicializado indevidamente.', [
                    'nsr' => $nsr,
                    'source' => $source ?? 'desconhecido',
                ]);
            }
        }

        throw new \RuntimeException('Falha ao gerar NSR atômico: colisões consecutivas excederam o limite de tentativas. Intervenção manual necessária.');
    }

    public function assertReady(): void
    {
        if (! $this->db->tableExists('nsr_counter')) {
            throw new \RuntimeException('Tabela nsr_counter ausente. A geração de NSR foi bloqueada por segurança.');
        }

        if (! $this->db->tableExists('nsr_ledger')) {
            throw new \RuntimeException('Tabela nsr_ledger ausente. A geração de NSR foi bloqueada por segurança.');
        }
    }
}
