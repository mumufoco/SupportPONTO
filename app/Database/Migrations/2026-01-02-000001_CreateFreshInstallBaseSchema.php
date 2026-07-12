<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fresh-install base schema for critical tables.
 *
 * Historical packages had additive migrations that touched employees and
 * time_punches before their original CREATE TABLE migrations were reached.
 * This base migration creates the minimum canonical tables early, allowing
 * legacy additive migrations to run deterministically on a blank PostgreSQL
 * database while preserving upgrade compatibility for existing installations.
 */
class CreateFreshInstallBaseSchema extends Migration
{
    public function up(): void
    {
        $this->createEmployeesTable();
        $this->createTimePunchesTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('time_punches', true);
        $this->forge->dropTable('employees', true);
    }

    private function createEmployeesTable(): void
    {
        if ($this->db->tableExists('employees')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'unique'     => true,
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => '14',
                'unique'     => true,
            ],
            'unique_code' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'unique'     => true,
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'funcionario',
            ],
            'department' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'position' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'expected_hours_daily' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => '8.00',
            ],
            'work_schedule_start' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'work_schedule_end' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'active' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'extra_hours_balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => '0.00',
            ],
            'owed_hours_balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => '0.00',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['role', 'active']);
        $this->forge->addKey('department');
        $this->forge->createTable('employees', true);
    }

    private function createTimePunchesTable(): void
    {
        if ($this->db->tableExists('time_punches')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type' => 'INT',
                'null' => false,
            ],
            'punch_time' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
            'punch_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => false,
            ],
            'latitude' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,8',
                'null'       => true,
            ],
            'longitude' => [
                'type'       => 'DECIMAL',
                'constraint' => '11,8',
                'null'       => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'device_info' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'photo_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
            ],
            'validation_method' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'manual',
            ],
            'is_valid' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'punch_time']);
        $this->forge->addKey('punch_time');
        $this->forge->addKey(['employee_id', 'punch_type']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('time_punches', true);
    }
}
