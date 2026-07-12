<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersistencePerformanceIndexes extends Migration
{
    private function assertPostgre(): void
    {
        if ($this->db->DBDriver !== 'Postgre') {
            throw new \RuntimeException('This migration supports PostgreSQL only.');
        }
    }

    private function addIndexIfTableExists(string $table, string $indexName, string $columns): void
    {
        if ($this->db->tableExists($table)) {
            $this->db->query("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})");
        }
    }

    public function up()
    {
        $this->assertPostgre();

        $this->addIndexIfTableExists('audit', 'idx_audit_level_created_at', 'level, created_at');
        $this->addIndexIfTableExists('audit', 'idx_audit_action_created_at', 'action, created_at');
        $this->addIndexIfTableExists('time_punches', 'idx_time_punches_employee_type_time', 'employee_id, punch_type, punch_time');
        $this->addIndexIfTableExists('timesheet_consolidated', 'idx_timesheet_consolidated_employee_date_incomplete', 'employee_id, date, incomplete');
        $this->addIndexIfTableExists('justifications', 'idx_justifications_employee_status_date_persistence', 'employee_id, status, date');
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'Postgre') {
            return;
        }

        foreach ([
            'idx_justifications_employee_status_date_persistence',
            'idx_timesheet_consolidated_employee_date_incomplete',
            'idx_time_punches_employee_type_time',
            'idx_audit_action_created_at',
            'idx_audit_level_created_at',
        ] as $index) {
            $this->db->query("DROP INDEX IF EXISTS {$index}");
        }
    }
}
