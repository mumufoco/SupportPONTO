<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add Two-Factor Authentication
 *
 * Adds columns to employees table for 2FA functionality
 */
class AddTwoFactorAuth extends Migration
{
    public function up()
    {
        $fields = [
            'two_factor_enabled' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'comment' => '2FA habilitado (TOTP)',
            ],
            'two_factor_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Secret do TOTP (Base32, encrypted)',
            ],
            'two_factor_backup_codes' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Códigos de backup (JSON encrypted)',
            ],
            'two_factor_verified_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
                'comment' => 'Data de verificação do 2FA',
            ],
        ];

        if (!$this->db->tableExists('employees')) {
            log_message('warning', 'Migration AddTwoFactorAuth ignorada: tabela employees ainda não existe.');
            return;
        }

        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'employees')) {
                $this->forge->addColumn('employees', [$name => $definition]);
            }
        }

        // Add index for 2FA enabled employees (PostgreSQL syntax)
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_employees_2fa ON employees(two_factor_enabled, active)');
    }

    public function down()
    {
        // Drop index (PostgreSQL syntax)
        $this->db->query('DROP INDEX IF EXISTS idx_employees_2fa');

        // Drop columns
        $this->forge->dropColumn('employees', [
            'two_factor_enabled',
            'two_factor_secret',
            'two_factor_backup_codes',
            'two_factor_verified_at',
        ]);
    }
}
