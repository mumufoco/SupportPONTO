<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Database Performance Optimization.
 *
 * PostgreSQL-first migration with globally unique index names.
 */
class AddPerformanceIndexes extends Migration
{
    private function assertPostgre(): void
    {
        if ($this->db->DBDriver !== 'Postgre') {
            throw new \RuntimeException('This migration supports PostgreSQL only.');
        }
    }

    private function addIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        $this->db->query("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})");
    }

    private function dropIndexIfExists(string $indexName): void
    {
        $this->db->query("DROP INDEX IF EXISTS {$indexName}");
    }

    public function up()
    {
        $this->assertPostgre();

        if ($this->db->tableExists('time_punches')) {
            $this->addIndexIfNotExists('time_punches', 'idx_time_punches_employee_punch_time', 'employee_id, punch_time DESC');
            $this->addIndexIfNotExists('time_punches', 'idx_time_punches_type_punch_time', 'punch_type, punch_time DESC');
            $this->addIndexIfNotExists('time_punches', 'idx_time_punches_geofence_punch_time', 'within_geofence, punch_time DESC');
            $this->addIndexIfNotExists('time_punches', 'idx_time_punches_employee_method_time', 'employee_id, method, punch_time DESC');
        }

        if ($this->db->tableExists('audit_logs')) {
            $this->addIndexIfNotExists('audit_logs', 'idx_audit_logs_user_action_date', 'user_id, action, created_at DESC');
            $this->addIndexIfNotExists('audit_logs', 'idx_audit_logs_action_date', 'action, created_at DESC');
            $this->addIndexIfNotExists('audit_logs', 'idx_audit_logs_entity_type_id_date', 'entity_type, entity_id, created_at DESC');
        }

        if ($this->db->tableExists('chat_messages')) {
            $this->addIndexIfNotExists('chat_messages', 'idx_chat_messages_room_date', 'room_id, created_at DESC');
            $this->addIndexIfNotExists('chat_messages', 'idx_chat_messages_sender_room_date', 'sender_id, room_id, created_at DESC');
        }

        if ($this->db->tableExists('employees')) {
            $this->addIndexIfNotExists('employees', 'idx_employees_department_active_name', 'department, active, name');
        }

        if ($this->db->tableExists('justifications')) {
            $this->addIndexIfNotExists('justifications', 'idx_justifications_employee_status_date_v2', 'employee_id, status, justification_date DESC');
            $this->addIndexIfNotExists('justifications', 'idx_justifications_status_created_at', 'status, created_at DESC');
        }

        if ($this->db->tableExists('biometric_templates')) {
            $this->addIndexIfNotExists('biometric_templates', 'idx_biometric_templates_employee_type_active', 'employee_id, biometric_type, active');
        }

        if ($this->db->tableExists('warnings')) {
            $this->addIndexIfNotExists('warnings', 'idx_warnings_employee_occurrence_date', 'employee_id, occurrence_date DESC');
            $this->addIndexIfNotExists('warnings', 'idx_warnings_type_status_occurrence_date', 'warning_type, status, occurrence_date DESC');
        }
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'Postgre') {
            return;
        }

        foreach ([
            'idx_warnings_type_status_occurrence_date',
            'idx_warnings_employee_occurrence_date',
            'idx_biometric_templates_employee_type_active',
            'idx_justifications_status_created_at',
            'idx_justifications_employee_status_date_v2',
            'idx_employees_department_active_name',
            'idx_chat_messages_sender_room_date',
            'idx_chat_messages_room_date',
            'idx_audit_logs_entity_type_id_date',
            'idx_audit_logs_action_date',
            'idx_audit_logs_user_action_date',
            'idx_time_punches_employee_method_time',
            'idx_time_punches_geofence_punch_time',
            'idx_time_punches_type_punch_time',
            'idx_time_punches_employee_punch_time',
        ] as $index) {
            $this->dropIndexIfExists($index);
        }
    }
}
