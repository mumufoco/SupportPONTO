<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Compatibilidade histórica do Pacote 435.
 *
 * Versões antigas tentavam criar uma segunda tabela chamada `audit` enquanto o
 * contrato real da aplicação usa `audit_logs`. Em instalação limpa isso gerava
 * duas trilhas paralelas e confundia manutenção/retensão. A migration agora
 * garante apenas que `audit_logs` exista quando necessário e não cria mais a
 * tabela legada `audit` em bases novas.
 */
class FixAuditTableStructure extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('audit_logs')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'SERIAL',
                'auto_increment' => true,
            ],
            'user_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'action' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'table_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'record_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'entity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'entity_id' => [
                'type' => 'BIGINT',
                'null' => true,
            ],
            'old_values' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'new_values' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type' => 'VARCHAR',
                'constraint' => 45,
                'null' => true,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'url' => [
                'type' => 'VARCHAR',
                'constraint' => 500,
                'null' => true,
            ],
            'method' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
            ],
            'level' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'info',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->createTable('audit_logs', true);

        $this->createIndexIfPossible('idx_audit_logs_user_action', 'audit_logs(user_id, action)');
        $this->createIndexIfPossible('idx_audit_logs_table_record', 'audit_logs(table_name, record_id)');
        $this->createIndexIfPossible('idx_audit_logs_created_at', 'audit_logs(created_at)');
    }

    public function down(): void
    {
        // Migration de compatibilidade: não remove trilha de auditoria em rollback automático.
    }

    private function createIndexIfPossible(string $name, string $definition): void
    {
        try {
            $this->db->query("CREATE INDEX IF NOT EXISTS {$name} ON {$definition}");
        } catch (\Throwable $ignored) {
            // Bancos legados podem já possuir índices equivalentes.
        }
    }
}
