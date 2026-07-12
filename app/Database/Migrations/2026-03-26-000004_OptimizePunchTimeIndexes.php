<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * MELHORIA 10: Índices no PostgreSQL para Consultas de Ponto.
 *
 * A query mais executada do sistema é "registros de ponto do dia",
 * que usa WHERE DATE(punch_time) = ?. Sem índice funcional, o PostgreSQL
 * tende a fazer full table scan em bases grandes.
 *
 * Esta migration cria:
 * 1. Índice funcional em DATE(punch_time) para uso direto na cláusula WHERE
 * 2. Índice parcial nos últimos 90 dias (consultas mais frequentes)
 * 3. Índice composto para relatórios mensais por departamento
 *
 * Importante: esta migration roda dentro do fluxo padrão do CI4, então evita
 * CREATE/DROP INDEX CONCURRENTLY, que são incompatíveis com transaction block.
 */
class OptimizePunchTimeIndexes extends Migration
{
    private function isPostgre(): bool
    {
        return $this->db->DBDriver === 'Postgre';
    }

    private function createIndex(string $sql): void
    {
        $this->db->query($sql);
    }

    private function dropIndex(string $index): void
    {
        $this->db->query("DROP INDEX IF EXISTS {$index}");
    }

    public function up(): void
    {
        if (! $this->isPostgre()) {
            return;
        }

        $this->createIndex(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_tp_punch_date_employee
            ON time_punches (DATE(punch_time), employee_id)
        SQL);

        $this->createIndex(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_tp_recent_by_employee
            ON time_punches (employee_id, punch_time DESC)
            WHERE punch_time >= NOW() - INTERVAL '90 days'
        SQL);

        $this->createIndex(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_tp_month_trunc
            ON time_punches (DATE_TRUNC('month', punch_time), employee_id)
        SQL);

        $this->createIndex(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_tp_method_date
            ON time_punches (method, DATE(punch_time))
        SQL);

        $this->createIndex(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_audit_created_level
            ON audit_logs (created_at DESC, level)
            WHERE level IN ('warning', 'error', 'critical')
        SQL);

        $this->createIndex(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_employees_active_dept
            ON employees (department, name)
            WHERE active = TRUE
        SQL);
    }

    public function down(): void
    {
        if (! $this->isPostgre()) {
            return;
        }

        foreach ([
            'idx_tp_punch_date_employee',
            'idx_tp_recent_by_employee',
            'idx_tp_month_trunc',
            'idx_tp_method_date',
            'idx_audit_created_level',
            'idx_employees_active_dept',
        ] as $index) {
            $this->dropIndex($index);
        }
    }
}
