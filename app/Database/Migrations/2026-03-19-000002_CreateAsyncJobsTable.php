<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAsyncJobsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'job_id' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => false,
            ],
            'job_type' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => false,
            ],
            'queue' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'default',
            ],
            'employee_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'pending',
            ],
            'priority' => [
                'type' => 'SMALLINT',
                'constraint' => 5,
                'unsigned' => true,
                'default' => 50,
            ],
            'progress' => [
                'type' => 'INT',
                'constraint' => 3,
                'unsigned' => true,
                'default' => 0,
            ],
            'attempts' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 0,
            ],
            'max_attempts' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'default' => 3,
            ],
            'payload' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'result_payload' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'result_file_path' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'trace_id' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'available_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('job_id');
        $this->forge->addKey(['queue', 'status', 'priority', 'available_at']);
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('async_jobs');
    }

    public function down()
    {
        $this->forge->dropTable('async_jobs');
    }
}
