<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabela de referência da CBO (Classificação Brasileira de Ocupações, MTE) --
 * usada para o seletor de "CBO mais indicado" no cadastro de Cargos. Dado
 * oficial (2445 códigos de ocupação), não editável por admin nesta tela; a
 * carga em massa fica no CboOccupationSeeder (não em insertBatch aqui), pela
 * mesma lição de contract_types: insertBatch com muitas linhas é frágil e
 * qualquer chave heterogênea quebra o INSERT inteiro silenciosamente.
 */
class CreateCboOccupationsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('cbo_occupations')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'null'       => false,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
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
        $this->forge->addKey('code', false, true); // UNIQUE
        $this->forge->createTable('cbo_occupations', true);

        $this->db->query('CREATE INDEX idx_cbo_occupations_title ON cbo_occupations (title)');
    }

    public function down(): void
    {
        $this->forge->dropTable('cbo_occupations', true);
    }
}
