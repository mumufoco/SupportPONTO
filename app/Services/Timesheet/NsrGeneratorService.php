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

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function next(): int
    {
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

        return $nsr;
    }

    public function assertReady(): void
    {
        if (! $this->db->tableExists('nsr_counter')) {
            throw new \RuntimeException('Tabela nsr_counter ausente. A geração de NSR foi bloqueada por segurança.');
        }
    }
}
