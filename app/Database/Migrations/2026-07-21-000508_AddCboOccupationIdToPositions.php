<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Vincula opcionalmente um cargo (positions) ao CBO mais indicado
 * (cbo_occupations). ON DELETE SET NULL: CBO é dado de referência que nunca
 * deveria ser removido, mas se algum dia for, o cargo não pode ser arrastado
 * junto -- só perde a referência.
 */
class AddCboOccupationIdToPositions extends Migration
{
    public function up(): void
    {
        if (! in_array('cbo_occupation_id', $this->db->getFieldNames('positions'), true)) {
            $this->forge->addColumn('positions', [
                'cbo_occupation_id' => [
                    'type'  => 'INTEGER',
                    'null'  => true,
                    'after' => 'department_id',
                ],
            ]);
        }

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_positions_cbo_occupation_id ON positions (cbo_occupation_id)');

        $constraintExists = $this->db->query(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'fk_positions_cbo_occupation_id'"
        )->getRow();

        if (! $constraintExists) {
            $this->db->query(
                'ALTER TABLE positions ADD CONSTRAINT fk_positions_cbo_occupation_id
                    FOREIGN KEY (cbo_occupation_id) REFERENCES cbo_occupations(id)
                    ON DELETE SET NULL ON UPDATE CASCADE'
            );
        }
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE positions DROP CONSTRAINT IF EXISTS fk_positions_cbo_occupation_id');
        $this->db->query('DROP INDEX IF EXISTS idx_positions_cbo_occupation_id');

        if (in_array('cbo_occupation_id', $this->db->getFieldNames('positions'), true)) {
            $this->forge->dropColumn('positions', 'cbo_occupation_id');
        }
    }
}
