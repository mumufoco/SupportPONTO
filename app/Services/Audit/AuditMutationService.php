<?php

namespace App\Services\Audit;

use CodeIgniter\Database\BaseConnection;

/**
 * Executa operações controladas de manutenção em audit_logs.
 *
 * FIX OBS-1 (v1.1.279): Adicionada allowlist de razões permitidas para
 * evitar injeção acidental via strings dinâmicas em SET LOCAL.
 */
class AuditMutationService
{
    /**
     * Valores permitidos para o parâmetro $reason.
     * SET LOCAL não suporta queries parametrizadas no PostgreSQL,
     * por isso validamos contra uma lista fixa antes do escape manual.
     */
    private const ALLOWED_REASONS = ['retention', 'anonymization', 'maintenance', 'test'];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function createDefault(): self
    {
        return new self(db_connect());
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     * @throws \InvalidArgumentException se $reason não estiver na allowlist
     * @throws \RuntimeException se a transação falhar
     */
    public function runControlled(callable $callback, string $reason = 'maintenance'): mixed
    {
        if (!in_array($reason, self::ALLOWED_REASONS, true)) {
            throw new \InvalidArgumentException(
                "Motivo de manutenção não permitido: '{$reason}'. "
                . "Valores aceitos: " . implode(', ', self::ALLOWED_REASONS) . '.'
            );
        }

        if ($this->db->DBDriver !== 'Postgre') {
            // Fora do PostgreSQL, não há trigger de imutabilidade — executa diretamente
            return $callback();
        }

        $this->db->transStart();

        // Escape defensivo adicional (já validado pela allowlist acima)
        $escapedReason = str_replace("'", "''", $reason);
        $this->db->query("SET LOCAL app.audit_maintenance_mode = 'on'");
        $this->db->query("SET LOCAL app.audit_maintenance_reason = '{$escapedReason}'");

        try {
            $result = $callback();
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Falha ao concluir operação controlada em audit_logs.');
            }

            return $result;
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * Retorna os valores de razão permitidos.
     * Útil para documentação e testes.
     */
    public static function allowedReasons(): array
    {
        return self::ALLOWED_REASONS;
    }
}
