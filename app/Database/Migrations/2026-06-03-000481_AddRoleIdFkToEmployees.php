<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRoleIdFkToEmployees extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE employees ADD COLUMN IF NOT EXISTS role_id INTEGER');

        $this->db->query(
            "UPDATE employees e SET role_id = r.id FROM roles r WHERE LOWER(r.name) = LOWER(e.role) AND e.role_id IS NULL"
        );

        $this->db->query(
            "UPDATE employees SET role_id = (SELECT id FROM roles WHERE LOWER(name)='funcionario' ORDER BY id LIMIT 1) WHERE role_id IS NULL"
        );

        try {
            $this->db->query(
                'ALTER TABLE employees ADD CONSTRAINT fk_employees_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT ON UPDATE CASCADE'
            );
        } catch (\Throwable $e) {
            log_message('info', 'fk_employees_role_id: ' . $e->getMessage());
        }

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_employees_role_id ON employees(role_id)');
    }

    public function down(): void
    {
        try {
            $this->db->query('ALTER TABLE employees DROP CONSTRAINT IF EXISTS fk_employees_role_id');
        } catch (\Throwable) {}
        $this->db->query('DROP INDEX IF EXISTS idx_employees_role_id');
        $this->db->query('ALTER TABLE employees DROP COLUMN IF EXISTS role_id');
    }
}
