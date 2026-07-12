<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add Soft Delete Support to Schedules Table.
 */
class AddSoftDeleteToSchedules extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('schedules')) {
            log_message('warning', 'Migration AddSoftDeleteToSchedules adiada: tabela schedules ainda não existe.');
            return;
        }

        if ($this->db->fieldExists('deleted_at', 'schedules')) {
            log_message('info', 'Migration: deleted_at column already exists in schedules table');
            return;
        }

        $this->forge->addColumn('schedules', [
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ]);

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_schedule_deleted ON schedules(deleted_at)');
        log_message('info', 'Soft delete column added to schedules table');
    }

    public function down()
    {
        $this->db->query('DROP INDEX IF EXISTS idx_schedule_deleted');

        if ($this->db->tableExists('schedules') && $this->db->fieldExists('deleted_at', 'schedules')) {
            $this->forge->dropColumn('schedules', 'deleted_at');
        }

        log_message('info', 'Soft delete column removed from schedules table');
    }
}
