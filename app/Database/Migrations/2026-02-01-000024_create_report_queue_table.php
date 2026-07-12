<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Report Queue Table
 *
 * Stores large report generation jobs for background processing
 */
class CreateReportQueueTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'job_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'unique'     => true,
                'comment'    => 'Unique job identifier',
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'Employee who requested the report',
            ],
            'report_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'comment'    => 'Type of report (attendance, absences, etc.)',
            ],
            'report_format' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'comment'    => 'Output format',
            ],
            'filters' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Filters applied to report (JSON)',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'pending',
                'comment'    => 'Job status',
            ],
            'progress' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => 'Progress percentage (0-100)',
            ],
            'result_file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '500',
                'null'       => true,
                'comment'    => 'Path to generated file',
            ],
            'error_message' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Error message if failed',
            ],
            'attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => 'Number of processing attempts',
            ],
            'max_attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 3,
                'comment'    => 'Maximum attempts before fail',
            ],
            'started_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => 'When processing started',
            ],
            'completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => 'When job completed',
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
        // job_id already has unique index from field definition
        $this->forge->addKey(['status', 'created_at']);
        $this->forge->addKey('employee_id');

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('report_queue');
    }

    public function down()
    {
        $this->forge->dropTable('report_queue');
    }
}
