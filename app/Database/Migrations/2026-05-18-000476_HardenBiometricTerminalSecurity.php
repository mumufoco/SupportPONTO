<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase 11: hardening de biometria e endpoints públicos.
 *
 * Registra terminais/kiosks autorizados e nonces para bloquear replay em
 * endpoints sem CSRF por sessão de navegador.
 */
class HardenBiometricTerminalSecurity extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('punch_terminal_devices')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGSERIAL'],
                'terminal_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => false],
                'name' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
                'secret_hash' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
                'allowed_ip' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'device_fingerprint' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
                'active' => ['type' => 'BOOLEAN', 'default' => true],
                'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
                'last_ip' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'last_user_agent' => ['type' => 'TEXT', 'null' => true],
                'created_by' => ['type' => 'BIGINT', 'null' => true],
                'revoked_at' => ['type' => 'DATETIME', 'null' => true],
                'revoked_by' => ['type' => 'BIGINT', 'null' => true],
                'notes' => ['type' => 'TEXT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('punch_terminal_devices', true);
        }

        if (! $this->db->tableExists('punch_terminal_nonces')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGSERIAL'],
                'terminal_id' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => false],
                'nonce_hash' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => false],
                'purpose' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => false, 'default' => 'punch'],
                'ip_address' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
                'expires_at' => ['type' => 'DATETIME', 'null' => false],
                'consumed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('punch_terminal_nonces', true);
        }

        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_punch_terminal_devices_terminal_id ON punch_terminal_devices(terminal_id)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_punch_terminal_devices_active ON punch_terminal_devices(active, terminal_id)');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_punch_terminal_nonces_unique ON punch_terminal_nonces(terminal_id, nonce_hash, purpose)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_punch_terminal_nonces_expires ON punch_terminal_nonces(expires_at)');
    }

    public function down(): void
    {
        $this->db->query('DROP INDEX IF EXISTS idx_punch_terminal_nonces_expires');
        $this->db->query('DROP INDEX IF EXISTS idx_punch_terminal_nonces_unique');
        $this->db->query('DROP INDEX IF EXISTS idx_punch_terminal_devices_active');
        $this->db->query('DROP INDEX IF EXISTS idx_punch_terminal_devices_terminal_id');
        $this->forge->dropTable('punch_terminal_nonces', true);
        $this->forge->dropTable('punch_terminal_devices', true);
    }
}
