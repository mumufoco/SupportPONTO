<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Suporte a 2 testemunhas por advertência (orientação jurídica) - substitui as colunas
 * unicas witness_name/witness_cpf/witness_signature em warnings por uma tabela filha,
 * migrando o historico ja existente antes de remover as colunas antigas.
 */
class CreateWarningWitnessesTable extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('warning_witnesses')) {
            $this->forge->addField([
                'id'                => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'warning_id'        => ['type' => 'BIGINT', 'unsigned' => true, 'null' => false],
                'witness_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
                'witness_cpf'       => ['type' => 'VARCHAR', 'constraint' => 14, 'null' => false],
                'witness_signature' => ['type' => 'TEXT', 'null' => false, 'comment' => 'Declaração/depoimento textual da testemunha'],
                'created_at'        => ['type' => 'TIMESTAMP', 'null' => true],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('warning_id');
            $this->forge->addForeignKey('warning_id', 'warnings', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('warning_witnesses');
        }

        if ($this->db->tableExists('warnings') && $this->db->fieldExists('witness_name', 'warnings')) {
            $this->db->query(
                "INSERT INTO warning_witnesses (warning_id, witness_name, witness_cpf, witness_signature, created_at)
                 SELECT id, witness_name, witness_cpf, witness_signature, COALESCE(updated_at, created_at)
                 FROM warnings
                 WHERE witness_name IS NOT NULL AND witness_name <> ''"
            );

            foreach (['witness_name', 'witness_cpf', 'witness_signature'] as $column) {
                if ($this->db->fieldExists($column, 'warnings')) {
                    $this->forge->dropColumn('warnings', $column);
                }
            }
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('warnings') && ! $this->db->fieldExists('witness_name', 'warnings')) {
            $this->forge->addColumn('warnings', [
                'witness_name'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'witness_cpf'       => ['type' => 'VARCHAR', 'constraint' => 14, 'null' => true],
                'witness_signature' => ['type' => 'TEXT', 'null' => true],
            ]);
        }

        $this->forge->dropTable('warning_witnesses', true);
    }
}
