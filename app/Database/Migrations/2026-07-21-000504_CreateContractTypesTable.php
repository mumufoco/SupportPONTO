<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catálogo "Tipo de Contrato" (CLT, PJ, Estágio, Terceirizado etc.), personalizável
 * pelo admin -- mesmo padrão de departments/positions/roles. Antes disso o campo
 * `employees.tipo_contrato` só aceitava um dos 4 valores fixos hardcoded na view
 * de cadastro, sem nenhuma tela de gestão.
 */
class CreateContractTypesTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('contract_types')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'active' => [
                'type'    => 'BOOLEAN',
                'default' => true,
                'null'    => false,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name', false, true); // UNIQUE
        $this->forge->createTable('contract_types', true);

        // Nomes seguem exatamente os 4 valores fixos que a view antiga gravava em
        // employees.tipo_contrato (que continua sendo um varchar por nome, não FK) --
        // preserva a seleção correta ao editar colaboradores já cadastrados. O admin
        // pode renomear/ajustar a exibição depois pela própria tela de gestão.
        $now = date('Y-m-d H:i:s');
        $this->db->table('contract_types')->insertBatch([
            ['name' => 'CLT', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'temporario', 'description' => 'Temporário', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'estagio', 'description' => 'Estágio', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'terceirizado', 'description' => 'Terceirizado', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropTable('contract_types', true);
    }
}
