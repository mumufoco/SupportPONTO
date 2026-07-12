<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class HardenConsentTermsImmutability extends Migration
{
    public function up(): void
    {
        // 1. Add snapshot columns so consent is preserved even if employee is deleted
        $this->addColumnsIfMissing('user_consents', [
            'employee_name_snapshot' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'employee_id'],
            'employee_cpf_snapshot'  => ['type' => 'VARCHAR', 'constraint' => 20,  'null' => true, 'after' => 'employee_name_snapshot'],
        ]);

        // 2. Populate snapshots for existing records
        $this->db->query("
            UPDATE user_consents uc
            SET employee_name_snapshot = e.name,
                employee_cpf_snapshot  = e.cpf
            FROM employees e
            WHERE uc.employee_id = e.id
              AND uc.employee_name_snapshot IS NULL
        ");

        // 3. Change FK from CASCADE to RESTRICT — blocks employee deletion if consent exists
        $this->db->query('ALTER TABLE user_consents DROP CONSTRAINT IF EXISTS user_consents_employee_id_foreign');
        $this->db->query('
            ALTER TABLE user_consents
            ADD CONSTRAINT user_consents_employee_id_foreign
            FOREIGN KEY (employee_id)
            REFERENCES employees(id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT
        ');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE user_consents DROP CONSTRAINT IF EXISTS user_consents_employee_id_foreign');
        $this->db->query('
            ALTER TABLE user_consents
            ADD CONSTRAINT user_consents_employee_id_foreign
            FOREIGN KEY (employee_id)
            REFERENCES employees(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ');
    }

    private function addColumnsIfMissing(string $table, array $columns): void
    {
        $existing = $this->db->getFieldNames($table);
        foreach ($columns as $col => $def) {
            if (!in_array($col, $existing, true)) {
                $this->forge->addColumn($table, [$col => $def]);
            }
        }
    }
}
