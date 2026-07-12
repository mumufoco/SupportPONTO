<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add Manager Hierarchy
 *
 * Adds manager_id field to employees table to support hierarchical structure.
 * This migration is PostgreSQL-first but keeps SQLite compatibility for tests.
 */
class AddManagerHierarchy extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('manager_id', 'employees')) {
            $this->forge->addColumn('employees', [
                'manager_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                    'comment'    => 'ID do gestor responsável (FK para employees)',
                ],
            ]);
        }

        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('ALTER TABLE employees DROP CONSTRAINT IF EXISTS fk_employees_manager');
            $this->db->query(
                'ALTER TABLE employees ADD CONSTRAINT fk_employees_manager FOREIGN KEY (manager_id) REFERENCES employees(id) ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }

        $this->db->query('DROP INDEX IF EXISTS idx_employees_manager');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_employees_manager ON employees(manager_id, active)');
    }

    public function down()
    {
        if ($this->db->DBDriver !== 'SQLite3') {
            $this->db->query('ALTER TABLE employees DROP CONSTRAINT IF EXISTS fk_employees_manager');
        }

        $this->db->query('DROP INDEX IF EXISTS idx_employees_manager');

        if ($this->db->fieldExists('manager_id', 'employees')) {
            $this->forge->dropColumn('employees', 'manager_id');
        }
    }
}
