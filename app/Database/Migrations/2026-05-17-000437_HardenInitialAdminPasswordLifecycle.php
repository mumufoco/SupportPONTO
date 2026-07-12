<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenInitialAdminPasswordLifecycle extends Migration
{
    public function up(): void
    {
        if (! $this->db->fieldExists('must_change_password', 'employees')) {
            $this->forge->addColumn('employees', [
                'must_change_password' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                    'null' => false,
                    'after' => 'password',
                ],
            ]);
        }

        if (! $this->db->fieldExists('password_changed_at', 'employees')) {
            $this->forge->addColumn('employees', [
                'password_changed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'must_change_password',
                ],
            ]);
        }

        if (! $this->db->fieldExists('remember_token', 'employees')) {
            $this->forge->addColumn('employees', [
                'remember_token' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'password_changed_at',
                ],
            ]);
        }

        if (! $this->db->fieldExists('remember_token_expires', 'employees')) {
            $this->forge->addColumn('employees', [
                'remember_token_expires' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'after' => 'remember_token',
                ],
            ]);
        }
    }

    public function down(): void
    {
        // Migração de hardening idempotente: não remove colunas de segurança no rollback.
    }
}
