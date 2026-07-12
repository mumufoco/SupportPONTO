<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSettingsListingIndexes extends Migration
{
    private function addIndexIfTableExists(string $table, string $indexName, string $columns): void
    {
        if ($this->db->tableExists($table)) {
            $this->db->query("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columns})");
        }
    }

    public function up(): void
    {
        $this->addIndexIfTableExists('work_shifts', 'idx_work_shifts_active_type_start_time', 'active, type, start_time');
        $this->addIndexIfTableExists('holidays', 'idx_holidays_active_date', 'active, date');
        $this->addIndexIfTableExists('geofences', 'idx_geofences_active_created_at', 'active, created_at DESC');
    }

    public function down(): void
    {
        foreach ([
            'idx_geofences_active_created_at',
            'idx_holidays_active_date',
            'idx_work_shifts_active_type_start_time',
        ] as $index) {
            $this->db->query("DROP INDEX IF EXISTS {$index}");
        }
    }
}
