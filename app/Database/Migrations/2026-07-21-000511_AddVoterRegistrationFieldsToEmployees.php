<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Titulo de Eleitor -- Aba "Documentacao Geral" do cadastro de colaborador.
 * Todos os campos sao opcionais (nao foram pedidos como obrigatorios).
 */
class AddVoterRegistrationFieldsToEmployees extends Migration
{
    private string $table = 'employees';

    private array $columns = [
        'titulo_eleitor_numero' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true],
        'titulo_eleitor_zona' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
        'titulo_eleitor_secao' => ['type' => 'VARCHAR', 'constraint' => 5, 'null' => true],
        'titulo_eleitor_uf' => ['type' => 'CHAR', 'constraint' => 2, 'null' => true],
        'titulo_eleitor_municipio' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
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
