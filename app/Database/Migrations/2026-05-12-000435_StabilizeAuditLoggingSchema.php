<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class StabilizeAuditLoggingSchema extends Migration
{
    private string $table = 'audit_logs';

    public function up(): void
    {
        if (! $this->db->tableExists($this->table)) {
            return;
        }

        $this->addMissingColumns('audit_logs', [
            'entity_type' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'entity_id' => [
                'type' => 'BIGINT',
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
            'row_checksum' => [
                'type' => 'CHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'actor_type' => [
                'type' => 'VARCHAR',
                'constraint' => 30,
                'default' => 'user',
                'null' => true,
            ],
            'request_id' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'source' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'web',
                'null' => true,
            ],
            'context_data' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        try {
            $this->db->query("UPDATE {$this->table} SET entity_type = table_name WHERE entity_type IS NULL AND table_name IS NOT NULL");
            $this->db->query("UPDATE {$this->table} SET entity_id = record_id WHERE entity_id IS NULL AND record_id IS NOT NULL");
            $this->db->query("UPDATE {$this->table} SET level = severity WHERE level IS NULL AND severity IS NOT NULL");
        } catch (\Throwable $ignored) {
            // Compatibilidade com bancos que não possuem a coluna legada severity.
        }

        $this->createIndexIfPossible('idx_audit_logs_request_id', "{$this->table}(request_id)");
        $this->createIndexIfPossible('idx_audit_logs_source_created', "{$this->table}(source, created_at DESC)");
        $this->createIndexIfPossible('idx_audit_logs_level_created', "{$this->table}(level, created_at DESC)");
        $this->createIndexIfPossible('idx_audit_logs_actor_created', "{$this->table}(actor_type, user_id, created_at DESC)");
    }

    public function down(): void
    {
        // Evolutiva e segura: não remove colunas de rastreabilidade em rollback automático.
    }

    /**
     * @param array<string,array<string,mixed>> $columns
     */
    private function addMissingColumns(string $table, array $columns): void
    {
        $existing = $this->db->getFieldNames($table);
        $missing = [];

        foreach ($columns as $column => $definition) {
            if (! in_array($column, $existing, true)) {
                $missing[$column] = $definition;
            }
        }

        if ($missing !== []) {
            $this->forge->addColumn($table, $missing);
        }
    }

    private function createIndexIfPossible(string $name, string $definition): void
    {
        try {
            $this->db->query("CREATE INDEX IF NOT EXISTS {$name} ON {$definition}");
        } catch (\Throwable $ignored) {
            // Índices equivalentes ou bancos sem suporte ao IF NOT EXISTS não devem quebrar instalação.
        }
    }
}
