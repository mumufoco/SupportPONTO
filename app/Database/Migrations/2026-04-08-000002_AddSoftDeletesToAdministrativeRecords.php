<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSoftDeletesToAdministrativeRecords extends Migration
{
    public function up()
    {
        $this->addSoftDeleteColumnIfMissing('justifications');
        $this->addSoftDeleteColumnIfMissing('warnings');

        $this->createIndexIfMissing('justifications', 'idx_justifications_deleted_at', 'deleted_at');
        $this->createIndexIfMissing('warnings', 'idx_warnings_deleted_at', 'deleted_at');
        $this->createIndexIfMissing('warnings', 'idx_warnings_status_occurrence_date', 'status, occurrence_date');
    }

    public function down()
    {
        $this->dropIndexIfExists('justifications', 'idx_justifications_deleted_at');
        $this->dropIndexIfExists('warnings', 'idx_warnings_deleted_at');
        $this->dropIndexIfExists('warnings', 'idx_warnings_status_occurrence_date');

        $this->dropSoftDeleteColumnIfExists('justifications');
        $this->dropSoftDeleteColumnIfExists('warnings');
    }

    private function addSoftDeleteColumnIfMissing(string $table): void
    {
        if (! $this->db->tableExists($table) || $this->db->fieldExists('deleted_at', $table)) {
            return;
        }

        $this->forge->addColumn($table, [
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'updated_at',
            ],
        ]);
    }

    private function dropSoftDeleteColumnIfExists(string $table): void
    {
        if (! $this->db->tableExists($table) || ! $this->db->fieldExists('deleted_at', $table)) {
            return;
        }

        $this->forge->dropColumn($table, 'deleted_at');
    }

    private function createIndexIfMissing(string $table, string $indexName, string $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $this->db->query("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})");
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $this->db->query("DROP INDEX IF EXISTS {$indexName}");
    }
}
