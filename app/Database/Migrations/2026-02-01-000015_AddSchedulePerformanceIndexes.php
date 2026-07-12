<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add Performance Indexes to Schedules and Shifts Tables.
 */
class AddSchedulePerformanceIndexes extends Migration
{
    private function assertPostgre(): void
    {
        if ($this->db->DBDriver !== 'Postgre') {
            throw new \RuntimeException('This migration supports PostgreSQL only.');
        }
    }

    public function up()
    {
        $this->assertPostgre();

        if ($this->db->tableExists('schedules')) {
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_schedules_date_employee ON schedules(date, employee_id)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_schedules_date_shift ON schedules(date, shift_id)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_schedules_status_date ON schedules(status, date)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_schedules_recurring_date ON schedules(is_recurring, date)');
        }

        if ($this->db->tableExists('work_shifts')) {
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_work_shifts_active_type ON work_shifts(active, type)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_work_shifts_deleted_at ON work_shifts(deleted_at)');
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'Postgre') {
            return;
        }

        foreach ([
            'idx_work_shifts_deleted_at',
            'idx_work_shifts_active_type',
            'idx_schedules_recurring_date',
            'idx_schedules_status_date',
            'idx_schedules_date_shift',
            'idx_schedules_date_employee',
        ] as $index) {
            $this->db->query("DROP INDEX IF EXISTS {$index}");
        }
    }
}
