<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShiftsAndSchedules extends Migration
{
    public function up()
    {
        // Create work_shifts table
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'unsigned' => true,
                'auto_increment' => true
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true
            ],
            'start_time' => [
                'type' => 'TIME',
                'null' => false
            ],
            'end_time' => [
                'type' => 'TIME',
                'null' => false
            ],
            'color' => [
                'type' => 'VARCHAR',
                'constraint' => 7,
                'null' => true,
                'comment' => 'Hex color code for calendar display'
            ],
            'type' => [
                'type' => 'VARCHAR',
                'constraint' => '20',
                'default' => 'custom',
                'null' => false
            ],
            'break_duration' => [
                'type' => 'INT',
                'unsigned' => true,
                'default' => 0,
                'comment' => 'Break duration in minutes'
            ],
            'active' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => false
            ],
            'created_by' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('type');
        $this->forge->addKey('active');
        $this->forge->addKey('deleted_at');
        $this->forge->createTable('work_shifts', true); // true = IF NOT EXISTS

        // Create schedules table
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'unsigned' => true,
                'auto_increment' => true
            ],
            'employee_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false
            ],
            'shift_id' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => false
            ],
            'date' => [
                'type' => 'DATE',
                'null' => false
            ],
            'week_day' => [
                'type' => 'INT',
                'constraint' => 11,
                'null' => true,
                'comment' => '0=Sunday, 1=Monday, ..., 6=Saturday'
            ],
            'is_recurring' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'null' => false
            ],
            'recurrence_end_date' => [
                'type' => 'DATE',
                'null' => true
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'scheduled',
                'null' => false
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true
            ],
            'created_by' => [
                'type' => 'INT',
                'unsigned' => true,
                'null' => true
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true
            ]
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('shift_id');
        $this->forge->addKey('date');
        $this->forge->addKey(['employee_id', 'date']); // Composite index for quick lookups
        $this->forge->addKey('status');
        $this->forge->addKey('is_recurring');

        // Foreign keys
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('shift_id', 'work_shifts', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('schedules', true); // true = IF NOT EXISTS

        // Insert default shifts only if table is empty
        $existingShifts = $this->db->table('work_shifts')->countAllResults();
        if ($existingShifts > 0) {
            return; // Already has data, skip inserts
        }

        $this->db->table('work_shifts')->insertBatch([
            [
                'name' => 'Manhã',
                'description' => 'Turno da manhã padrão (08:00 - 12:00)',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'color' => '#FFA500',
                'type' => 'morning',
                'break_duration' => 0,
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Tarde',
                'description' => 'Turno da tarde padrão (13:00 - 18:00)',
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
                'color' => '#4169E1',
                'type' => 'afternoon',
                'break_duration' => 0,
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Noite',
                'description' => 'Turno da noite (22:00 - 06:00) com intervalo de 1h',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'color' => '#2F4F4F',
                'type' => 'night',
                'break_duration' => 60,
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ],
            [
                'name' => 'Comercial',
                'description' => 'Horário comercial completo (08:00 - 18:00) com intervalo de 1h',
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'color' => '#228B22',
                'type' => 'custom',
                'break_duration' => 60,
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    public function down()
    {
        // PostgreSQL remove dependências ao dropar a tabela.

        // Drop tables
        $this->forge->dropTable('schedules', true);
        $this->forge->dropTable('work_shifts', true);
    }
}
