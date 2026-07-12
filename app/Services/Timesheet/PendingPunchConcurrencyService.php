<?php

namespace App\Services\Timesheet;

use CodeIgniter\Database\BaseConnection;

/**
 * Trava a aprovação/rejeição de um registro de ponto pendente específico.
 *
 * Mesma estratégia de TimePunchConcurrencyService (lock consultivo transacional do
 * PostgreSQL), aplicada agora a PendingPunchService::approve()/reject() — ALTO-08 na
 * auditoria: sem lock, duas aprovações quase simultâneas do mesmo pendingId (dois
 * gestores, ou duplo clique) passavam ambas pela checagem "status === pending" antes
 * de qualquer uma persistir, criando dois pontos efetivos duplicados para a mesma
 * solicitação.
 */
class PendingPunchConcurrencyService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function lockPendingPunch(int $pendingId): void
    {
        if ($pendingId <= 0) {
            throw new \InvalidArgumentException('pendingId inválido para lock de aprovação.');
        }

        $driver = strtolower((string) ($this->db->DBDriver ?? ''));
        if (str_contains($driver, 'postgre') || str_contains($driver, 'postgres')) {
            $this->db->query('SELECT pg_advisory_xact_lock(?)', [$this->lockKey($pendingId)]);
            return;
        }

        // Fallback não-PostgreSQL só protege o processo/conexão atual. Produção
        // permanece PostgreSQL (mesma ressalva de TimePunchConcurrencyService).
        $this->db->query('SELECT 1');
    }

    private function lockKey(int $pendingId): int
    {
        return (int) (crc32('supportponto:pending_punch_review:' . $pendingId) & 0x7fffffff);
    }
}
