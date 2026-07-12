<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 8: strengthen time-punch integrity.
 *
 * - Removes legacy trigger/sequence NSR generation so the nsr_counter table is
 *   the single canonical source.
 * - Adds HMAC chain columns to time_punches.
 * - Adds uniqueness/indexes needed for NSR and per-employee chain validation.
 */
class StrengthenTimePunchIntegrity extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('time_punches')) {
            log_message('warning', 'StrengthenTimePunchIntegrity ignorada: tabela time_punches ausente.');
            return;
        }

        $this->disableLegacyNsrTrigger();
        $this->addIntegrityColumns();
        $this->createIndexes();
        $this->backfillIntegrityMetadata();
    }

    public function down(): void
    {
        if (! $this->db->tableExists('time_punches')) {
            return;
        }

        foreach (['idx_time_punches_chain_employee', 'idx_time_punches_integrity_key_id'] as $index) {
            $this->db->query("DROP INDEX IF EXISTS {$index}");
        }

        foreach (['integrity_signed_at','integrity_key_id','hash_version','hash_algorithm','chain_hash','previous_hash'] as $column) {
            if ($this->db->fieldExists($column, 'time_punches')) {
                $this->forge->dropColumn('time_punches', $column);
            }
        }
    }

    private function disableLegacyNsrTrigger(): void
    {
        $this->db->query('DROP TRIGGER IF EXISTS trigger_set_nsr ON time_punches');
        $this->db->query('DROP FUNCTION IF EXISTS set_nsr()');
        $this->db->query('DROP FUNCTION IF EXISTS generate_nsr()');
        $this->db->query('DROP SEQUENCE IF EXISTS nsr_sequence');
    }

    private function addIntegrityColumns(): void
    {
        $columns = [];

        if (! $this->db->fieldExists('previous_hash', 'time_punches')) {
            $columns['previous_hash'] = ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true];
        }
        if (! $this->db->fieldExists('chain_hash', 'time_punches')) {
            $columns['chain_hash'] = ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true];
        }
        if (! $this->db->fieldExists('hash_algorithm', 'time_punches')) {
            $columns['hash_algorithm'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true];
        }
        if (! $this->db->fieldExists('hash_version', 'time_punches')) {
            $columns['hash_version'] = ['type' => 'INT', 'null' => true];
        }
        if (! $this->db->fieldExists('integrity_key_id', 'time_punches')) {
            $columns['integrity_key_id'] = ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true];
        }
        if (! $this->db->fieldExists('integrity_signed_at', 'time_punches')) {
            $columns['integrity_signed_at'] = ['type' => 'DATETIME', 'null' => true];
        }

        if ($columns !== []) {
            $this->forge->addColumn('time_punches', $columns);
        }
    }

    private function createIndexes(): void
    {
        if ($this->db->fieldExists('nsr', 'time_punches')) {
            $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_time_punches_nsr_unique ON time_punches(nsr) WHERE nsr IS NOT NULL');
        }

        if ($this->db->fieldExists('chain_hash', 'time_punches')) {
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_time_punches_chain_employee ON time_punches(employee_id, punch_time, id, chain_hash)');
        }

        if ($this->db->fieldExists('integrity_key_id', 'time_punches')) {
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_time_punches_integrity_key_id ON time_punches(integrity_key_id)');
        }
    }

    private function backfillIntegrityMetadata(): void
    {
        if (! $this->db->fieldExists('hash_algorithm', 'time_punches')) {
            return;
        }

        $this->db->query("UPDATE time_punches SET hash_algorithm = COALESCE(hash_algorithm, 'SHA256-LEGACY'), hash_version = COALESCE(hash_version, 1) WHERE hash_algorithm IS NULL OR hash_version IS NULL");
    }
}
