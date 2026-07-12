<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFacialRecognitionLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'success' => [
                'type'       => 'INT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'similarity_score' => [
                'type'    => 'DECIMAL',
                'constraint' => '5,4',
                'null'    => true,
            ],
            'threshold_used' => [
                'type'    => 'DECIMAL',
                'constraint' => '5,4',
                'null'    => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('action');
        $this->forge->addKey('created_at');
        
        $this->forge->createTable('facial_recognition_logs', true);

        $this->db->query('CREATE INDEX idx_facial_logs_employee_date ON facial_recognition_logs (employee_id, created_at)');
    }

    public function down()
    {
        $this->forge->dropTable('facial_recognition_logs', true);
    }
}
