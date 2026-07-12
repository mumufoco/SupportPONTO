<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pacote 432 — estabiliza o módulo de colaboradores.
 *
 * Adiciona os IDs de catálogo usados pelos formulários atuais sem remover os
 * campos textuais legados/canônicos. Assim, o cadastro preserva rastreabilidade
 * do item selecionado e continua compatível com relatórios antigos que leem
 * department/position/work_unit como texto.
 */
class StabilizeEmployeeModuleSchema extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('employees')) {
            return;
        }

        $this->addMissingColumns('employees', [
            'work_unit_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'department_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'position_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
        ]);

        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_work_unit_id ON employees(work_unit_id)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_department_id ON employees(department_id)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_position_id ON employees(position_id)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_active_department ON employees(active, department)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_employees_active_department_id ON employees(active, department_id)');
    }

    public function down(): void
    {
        // Não remove colunas em rollback para não perder vínculos de catálogo já gravados.
    }

    /** @param array<string,array<string,mixed>> $columns */
    private function addMissingColumns(string $table, array $columns): void
    {
        $existing = $this->db->getFieldNames($table);
        foreach ($columns as $name => $definition) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $this->forge->addColumn($table, [$name => $definition]);
            $existing[] = $name;
        }
    }

    private function safeQuery(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            log_message('warning', '[Package432] Employee schema SQL skipped: ' . $e->getMessage());
        }
    }
}
