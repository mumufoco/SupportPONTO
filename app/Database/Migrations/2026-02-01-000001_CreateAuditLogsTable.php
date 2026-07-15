<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'table_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'record_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'old_values' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'new_values' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'severity' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'info',
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id']);
        $this->forge->addKey(['action']);
        $this->forge->addKey(['table_name']);
        $this->forge->addKey(['created_at']);
        $this->forge->createTable('audit_logs');

        // Add indexes for better performance
        $this->db->query("CREATE INDEX idx_audit_logs_user_action ON audit_logs(user_id, action)");
        $this->db->query("CREATE INDEX idx_audit_logs_table_record ON audit_logs(table_name, record_id)");
        $this->db->query("CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at)");
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs');
    }
}
