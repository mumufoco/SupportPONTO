<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pacote 448 — Performance de consultas e índices.
 *
 * Índices PostgreSQL idempotentes para os filtros críticos do SupportPONTO:
 * funcionário, data/período, status, empresa/unidade e setor/departamento.
 */
class PerformanceIndexesForReportsAndTimesheet extends Migration
{
    public function up(): void
    {
        if (! $this->isPostgre()) {
            log_message('warning', '[Package448] Índices avançados ignorados: banco não PostgreSQL.');
            return;
        }

        $this->indexTimePunches();
        $this->indexTimesheetConsolidated();
        $this->indexEmployees();
        $this->indexJustifications();
        $this->indexSchedules();
        $this->indexWarnings();
        $this->indexReportsAndAudit();
    }

    public function down(): void
    {
        if (! $this->isPostgre()) {
            return;
        }

        foreach ([
            'idx448_report_queue_status_priority_created',
            'idx448_audit_logs_period_actor_action',
            'idx448_warnings_status_period_employee',
            'idx448_warnings_employee_period_status',
            'idx448_schedules_employee_period_status',
            'idx448_schedules_period_department',
            'idx448_justifications_status_period_employee',
            'idx448_justifications_employee_period_status',
            'idx448_employees_company_department_active_name',
            'idx448_employees_work_unit_active_name',
            'idx448_employees_manager_active_name',
            'idx448_employees_active_role_name',
            'idx448_timesheet_recalc_period_employee',
            'idx448_timesheet_status_period_employee',
            'idx448_timesheet_employee_period_status',
            'idx448_timesheet_period_employee',
            'idx448_time_punches_status_period_employee',
            'idx448_time_punches_method_period_employee',
            'idx448_time_punches_type_period_employee',
            'idx448_time_punches_employee_status_period',
            'idx448_time_punches_employee_period_cover',
            'idx448_time_punches_period_employee',
        ] as $index) {
            $this->safeQuery('DROP INDEX IF EXISTS ' . $index);
        }
    }

    private function indexTimePunches(): void
    {
        if (! $this->hasTable('time_punches')) {
            return;
        }

        $this->createIndex('time_punches', 'idx448_time_punches_period_employee', ['punch_time', 'employee_id']);
        $this->createIndex('time_punches', 'idx448_time_punches_employee_period_cover', ['employee_id', 'punch_time DESC', 'punch_type', 'method', 'status']);
        $this->createIndex('time_punches', 'idx448_time_punches_employee_status_period', ['employee_id', 'status', 'punch_time DESC']);
        $this->createIndex('time_punches', 'idx448_time_punches_type_period_employee', ['punch_type', 'punch_time DESC', 'employee_id']);
        $this->createIndex('time_punches', 'idx448_time_punches_method_period_employee', ['method', 'punch_time DESC', 'employee_id']);
        $this->createIndex('time_punches', 'idx448_time_punches_status_period_employee', ['status', 'punch_time DESC', 'employee_id']);
    }

    private function indexTimesheetConsolidated(): void
    {
        if (! $this->hasTable('timesheet_consolidated')) {
            return;
        }

        $this->createIndex('timesheet_consolidated', 'idx448_timesheet_period_employee', ['date', 'employee_id']);
        $this->createIndex('timesheet_consolidated', 'idx448_timesheet_employee_period_status', ['employee_id', 'date DESC', 'incomplete', 'justified']);
        $this->createIndex('timesheet_consolidated', 'idx448_timesheet_status_period_employee', ['incomplete', 'justified', 'date DESC', 'employee_id']);
        $this->createIndex('timesheet_consolidated', 'idx448_timesheet_recalc_period_employee', ['needs_recalculation', 'date DESC', 'employee_id']);
    }

    private function indexEmployees(): void
    {
        if (! $this->hasTable('employees')) {
            return;
        }

        $this->createIndex('employees', 'idx448_employees_active_role_name', ['active', 'role', 'name']);
        $this->createIndex('employees', 'idx448_employees_manager_active_name', ['manager_id', 'active', 'name']);
        $this->createIndex('employees', 'idx448_employees_work_unit_active_name', ['work_unit_id', 'active', 'name']);
        $this->createIndex('employees', 'idx448_employees_company_department_active_name', ['work_unit_id', 'department_id', 'department', 'active', 'name']);
    }

    private function indexJustifications(): void
    {
        if (! $this->hasTable('justifications')) {
            return;
        }

        $dateColumn = $this->columnName('justifications', ['justification_date', 'date']);
        if ($dateColumn === null) {
            return;
        }

        $this->createIndex('justifications', 'idx448_justifications_employee_period_status', ['employee_id', $dateColumn . ' DESC', 'status']);
        $this->createIndex('justifications', 'idx448_justifications_status_period_employee', ['status', $dateColumn . ' DESC', 'employee_id']);
    }

    private function indexSchedules(): void
    {
        if (! $this->hasTable('schedules')) {
            return;
        }

        $this->createIndex('schedules', 'idx448_schedules_period_department', ['date', 'department_id', 'work_unit_id']);
        $this->createIndex('schedules', 'idx448_schedules_employee_period_status', ['employee_id', 'date DESC', 'status']);
    }

    private function indexWarnings(): void
    {
        if (! $this->hasTable('warnings')) {
            return;
        }

        $dateColumn = $this->columnName('warnings', ['occurrence_date', 'date', 'created_at']);
        if ($dateColumn === null) {
            return;
        }

        $this->createIndex('warnings', 'idx448_warnings_employee_period_status', ['employee_id', $dateColumn . ' DESC', 'status']);
        $this->createIndex('warnings', 'idx448_warnings_status_period_employee', ['status', $dateColumn . ' DESC', 'employee_id']);
    }

    private function indexReportsAndAudit(): void
    {
        if ($this->hasTable('audit_logs')) {
            $this->createIndex('audit_logs', 'idx448_audit_logs_period_actor_action', ['created_at DESC', 'user_id', 'action']);
        }

        if ($this->hasTable('report_queue')) {
            $this->createIndex('report_queue', 'idx448_report_queue_status_priority_created', ['status', 'priority DESC', 'created_at ASC']);
        }
    }

    /** @param list<string> $columns */
    private function createIndex(string $table, string $indexName, array $columns): void
    {
        if (! $this->hasColumns($table, $columns)) {
            return;
        }

        $sqlColumns = implode(', ', array_map(static function (string $column): string {
            return preg_match('/\s|\(|\)/', $column) ? $column : '"' . $column . '"';
        }, $columns));

        $this->safeQuery(sprintf('CREATE INDEX IF NOT EXISTS %s ON %s (%s)', $indexName, $table, $sqlColumns));
    }

    /** @param list<string> $columns */
    private function hasColumns(string $table, array $columns): bool
    {
        $existing = $this->safeFields($table);
        if ($existing === []) {
            return false;
        }

        foreach ($columns as $column) {
            $normalized = strtolower(trim(preg_replace('/\s+(asc|desc)$/i', '', $column)));
            if (str_contains($normalized, '(') || str_contains($normalized, ')')) {
                continue;
            }

            if (! in_array(trim($normalized, '"'), $existing, true)) {
                log_message('info', sprintf('[Package448] Índice em %s ignorado: coluna ausente %s.', $table, $column));
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $candidates */
    private function columnName(string $table, array $candidates): ?string
    {
        $existing = $this->safeFields($table);
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $existing, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function safeFields(string $table): array
    {
        try {
            return array_map('strtolower', $this->db->getFieldNames($table));
        } catch (\Throwable $e) {
            log_message('warning', '[Package448] Não foi possível listar colunas de ' . $table . ': ' . $e->getMessage());
            return [];
        }
    }

    private function hasTable(string $table): bool
    {
        try {
            return $this->db->tableExists($table);
        } catch (\Throwable $e) {
            log_message('warning', '[Package448] Não foi possível verificar tabela ' . $table . ': ' . $e->getMessage());
            return false;
        }
    }

    private function isPostgre(): bool
    {
        return $this->db->DBDriver === 'Postgre';
    }

    private function safeQuery(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            log_message('warning', '[Package448] SQL de índice ignorado: ' . $e->getMessage() . ' | SQL=' . $sql);
        }
    }
}
