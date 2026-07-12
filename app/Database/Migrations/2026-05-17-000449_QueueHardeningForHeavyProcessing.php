<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class QueueHardeningForHeavyProcessing extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('async_jobs')) {
            return;
        }

        $fields = [];

        if (! $this->db->fieldExists('locked_by', 'async_jobs')) {
            $fields['locked_by'] = [
                'type' => 'VARCHAR',
                'constraint' => 120,
                'null' => true,
                'after' => 'trace_id',
            ];
        }

        if (! $this->db->fieldExists('locked_at', 'async_jobs')) {
            $fields['locked_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'locked_by',
            ];
        }

        if (! $this->db->fieldExists('output_expires_at', 'async_jobs')) {
            $fields['output_expires_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'result_file_path',
            ];
        }

        if (! $this->db->fieldExists('purged_at', 'async_jobs')) {
            $fields['purged_at'] = [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'output_expires_at',
            ];
        }

        if ($fields !== []) {
            $this->forge->addColumn('async_jobs', $fields);
        }

        $this->addIndexIfMissing('idx_async_jobs_worker_claim', ['queue', 'status', 'available_at', 'priority', 'created_at']);
        $this->addIndexIfMissing('idx_async_jobs_stale_recovery', ['status', 'started_at', 'locked_at']);
        $this->addIndexIfMissing('idx_async_jobs_output_expiry', ['status', 'output_expires_at', 'purged_at']);
        $this->addIndexIfMissing('idx_async_jobs_type_status', ['job_type', 'status']);
    }

    public function down()
    {
        if (! $this->db->tableExists('async_jobs')) {
            return;
        }

        $this->dropIndexIfExists('idx_async_jobs_worker_claim');
        $this->dropIndexIfExists('idx_async_jobs_stale_recovery');
        $this->dropIndexIfExists('idx_async_jobs_output_expiry');
        $this->dropIndexIfExists('idx_async_jobs_type_status');

        foreach (['purged_at', 'output_expires_at', 'locked_at', 'locked_by'] as $field) {
            if ($this->db->fieldExists($field, 'async_jobs')) {
                $this->forge->dropColumn('async_jobs', $field);
            }
        }
    }

    /**
     * @param list<string> $fields
     */
    private function addIndexIfMissing(string $name, array $fields): void
    {
        if ($this->indexExists($name)) {
            return;
        }

        $this->forge->addKey($fields, false, false, $name);
        $this->forge->processIndexes('async_jobs');
    }

    private function dropIndexIfExists(string $name): void
    {
        if (! $this->indexExists($name)) {
            return;
        }

        try {
            $this->db->query('DROP INDEX IF EXISTS ' . $this->db->protectIdentifiers($name));
        } catch (\Throwable $exception) {
            log_message('warning', 'Falha ao remover índice de fila {index}: {error}', [
                'index' => $name,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function indexExists(string $name): bool
    {
        $prefix = $this->db->getPrefix();
        $indexName = $prefix . $name;

        $row = $this->db->query(
            "SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ? LIMIT 1",
            [$indexName]
        )->getRowArray();

        return $row !== null;
    }
}
