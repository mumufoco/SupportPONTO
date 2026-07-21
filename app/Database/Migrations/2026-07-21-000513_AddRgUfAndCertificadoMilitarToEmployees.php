<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * UF emissora do RG e Certificado Militar (RA) -- campos "Dados Gerais" da
 * Aba "Documentacao Geral". rg_uf fica separado dos demais campos de RG
 * (que ja vivem na Aba 1) porque o usuario pediu literalmente esse campo
 * dentro da Aba 3 -- ver nota no partial _documentation_data.php.
 */
class AddRgUfAndCertificadoMilitarToEmployees extends Migration
{
    private string $table = 'employees';

    private array $columns = [
        'rg_uf' => ['type' => 'CHAR', 'constraint' => 2, 'null' => true],
        'certificado_militar' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
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
