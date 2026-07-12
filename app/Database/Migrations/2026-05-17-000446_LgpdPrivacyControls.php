<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LgpdPrivacyControls extends Migration
{
    public function up(): void
    {
        $this->createInventoryTable();
        $this->createSubjectRequestsTable();
        $this->enhanceConsentTable();
        $this->enhanceDataExportsTable();
        $this->enhanceBiometricTemplatesTable();
    }

    public function down(): void
    {
        $this->forge->dropTable('lgpd_subject_requests', true);
        $this->forge->dropTable('lgpd_data_inventory', true);

        $this->dropColumnsIfExists('user_consents', ['revocation_reason', 'evidence_hash', 'processing_context']);
        $this->dropColumnsIfExists('data_exports', ['scope', 'format', 'sensitive_redaction_applied', 'requested_ip']);
        $this->dropColumnsIfExists('biometric_templates', ['retention_until', 'legal_basis', 'consent_id', 'privacy_status']);
    }

    private function createInventoryTable(): void
    {
        if ($this->db->tableExists('lgpd_data_inventory')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'inventory_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'label' => ['type' => 'VARCHAR', 'constraint' => 180],
            'category' => ['type' => 'VARCHAR', 'constraint' => 40, 'comment' => 'dados_pessoais|dados_sensiveis'],
            'legal_basis' => ['type' => 'TEXT'],
            'purpose' => ['type' => 'TEXT'],
            'retention_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'sensitivity' => ['type' => 'VARCHAR', 'constraint' => 30],
            'owner_role' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'dpo'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('inventory_key');
        $this->forge->addKey(['category', 'sensitivity']);
        $this->forge->createTable('lgpd_data_inventory');
    }

    private function createSubjectRequestsTable(): void
    {
        if ($this->db->tableExists('lgpd_subject_requests')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'request_id' => ['type' => 'VARCHAR', 'constraint' => 80],
            'employee_id' => ['type' => 'INT', 'unsigned' => true],
            'requested_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'request_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'comment' => 'access|export|correction|anonymization|deactivation|biometric_purge'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'pending'],
            'reason' => ['type' => 'TEXT', 'null' => true],
            'resolution_notes' => ['type' => 'TEXT', 'null' => true],
            'due_at' => ['type' => 'DATETIME', 'null' => true],
            'resolved_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('request_id');
        $this->forge->addKey(['employee_id', 'status']);
        $this->forge->addKey(['request_type', 'due_at']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('lgpd_subject_requests');
    }

    private function enhanceConsentTable(): void
    {
        $this->addColumnsIfMissing('user_consents', [
            'revocation_reason' => ['type' => 'TEXT', 'null' => true, 'after' => 'revoked_at'],
            'evidence_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'consent_text'],
            'processing_context' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'web_portal', 'after' => 'version'],
        ]);
    }

    private function enhanceDataExportsTable(): void
    {
        $this->addColumnsIfMissing('data_exports', [
            'scope' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'full_subject_export', 'after' => 'status'],
            'format' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'json_zip_aes256', 'after' => 'scope'],
            'sensitive_redaction_applied' => ['type' => 'BOOLEAN', 'default' => true, 'after' => 'format'],
            'requested_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true, 'after' => 'requested_by'],
        ]);
    }

    private function enhanceBiometricTemplatesTable(): void
    {
        $this->addColumnsIfMissing('biometric_templates', [
            'retention_until' => ['type' => 'DATE', 'null' => true, 'after' => 'encryption_version'],
            'legal_basis' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true, 'after' => 'retention_until'],
            'consent_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'legal_basis'],
            'privacy_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'active', 'after' => 'consent_id'],
        ]);
    }

    /** @param array<string,array<string,mixed>> $columns */
    private function addColumnsIfMissing(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }

        $existing = $this->db->getFieldNames($table);
        $toAdd = [];
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $toAdd[$name] = $definition;
            }
        }

        if ($toAdd !== []) {
            $this->forge->addColumn($table, $toAdd);
        }
    }

    /** @param array<int,string> $columns */
    private function dropColumnsIfExists(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }

        $existing = $this->db->getFieldNames($table);
        foreach ($columns as $column) {
            if (in_array($column, $existing, true)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }
}
