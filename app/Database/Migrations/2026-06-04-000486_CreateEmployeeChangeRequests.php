<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeeChangeRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'employee_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'requested_by'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => false],
            'field_key'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'field_label'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'current_value'   => ['type' => 'TEXT', 'null' => true],
            'requested_value' => ['type' => 'TEXT', 'null' => false],
            'justification'   => ['type' => 'TEXT', 'null' => false],
            'status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'reviewed_by'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reviewed_at'     => ['type' => 'DATETIME', 'null' => true],
            'review_note'     => ['type' => 'TEXT', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'updated_at'      => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id']);
        $this->forge->addKey(['status']);
        $this->forge->createTable('employee_change_requests', true);

        // CHECK constraint para status (PostgreSQL)
        $this->db->query(
            "ALTER TABLE employee_change_requests
             ADD CONSTRAINT chk_ecr_status
             CHECK (status IN ('pending','approved','rejected'))"
        );
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_change_requests', true);
    }
}
