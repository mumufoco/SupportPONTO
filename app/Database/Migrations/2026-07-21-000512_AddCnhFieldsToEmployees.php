<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CNH -- Aba "Documentacao Geral" do cadastro de colaborador. `possui_cnh` e
 * o toggle Sim/Nao que decide, no formulario, se os demais campos aparecem e
 * ficam obrigatorios (ver App\Validation\CustomRules::required_if_true()).
 */
class AddCnhFieldsToEmployees extends Migration
{
    private string $table = 'employees';

    private array $columns = [
        'possui_cnh' => ['type' => 'BOOLEAN', 'null' => false, 'default' => false],
        'cnh_numero' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        'cnh_categoria' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
        'cnh_data_emissao' => ['type' => 'DATE', 'null' => true],
        'cnh_validade' => ['type' => 'DATE', 'null' => true],
        'cnh_orgao_emissor' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        'cnh_uf' => ['type' => 'CHAR', 'constraint' => 2, 'null' => true],
    ];

    public function up(): void
    {
        $existing = $this->db->getFieldNames($this->table);

        foreach ($this->columns as $name => $definition) {
            if (! in_array($name, $existing, true)) {
                $this->forge->addColumn($this->table, [$name => $definition]);
            }
        }
    }

    public function down(): void
    {
        $existing = $this->db->getFieldNames($this->table);

        foreach (array_keys($this->columns) as $name) {
            if (in_array($name, $existing, true)) {
                $this->forge->dropColumn($this->table, $name);
            }
        }
    }
}
