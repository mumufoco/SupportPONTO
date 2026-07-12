<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Pacote 433 — estabilização estrutural do módulo de ponto eletrônico.
 */
class StabilizeTimesheetModuleSchema extends Migration
{
    public function up(): void
    {
        $this->alignTimePunches();
        $this->alignTimesheetConsolidated();
        $this->alignPendingPunches();
    }

    public function down(): void
    {
        // Migration idempotente de alinhamento. Não remove colunas para evitar perda de dados de ponto.
    }

    private function alignTimePunches(): void
    {
        $this->addMissingColumns('time_punches', [
            'location_lat' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'location_lng' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'location_accuracy' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
            'within_geofence' => ['type' => 'BOOLEAN', 'default' => true, 'null' => false],
            'geofence_name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'face_similarity' => ['type' => 'DECIMAL', 'constraint' => '7,4', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'approved', 'null' => false],
            'approved_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'rejected_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'rejected_at' => ['type' => 'DATETIME', 'null' => true],
            'rejection_reason' => ['type' => 'TEXT', 'null' => true],
        ]);

        if ($this->db->tableExists('time_punches')) {
            $fields = $this->db->getFieldNames('time_punches');
            if (in_array('latitude', $fields, true) && in_array('location_lat', $fields, true)) {
                $this->safeQuery('UPDATE time_punches SET location_lat = COALESCE(location_lat, latitude) WHERE latitude IS NOT NULL');
            }
            if (in_array('longitude', $fields, true) && in_array('location_lng', $fields, true)) {
                $this->safeQuery('UPDATE time_punches SET location_lng = COALESCE(location_lng, longitude) WHERE longitude IS NOT NULL');
            }
        }

        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_time_punches_employee_day ON time_punches(employee_id, DATE(punch_time))');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_time_punches_employee_type_time ON time_punches(employee_id, punch_type, punch_time)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_time_punches_status_time ON time_punches(status, punch_time DESC)');
        $this->safeQuery('CREATE UNIQUE INDEX IF NOT EXISTS idx_time_punches_nsr_unique ON time_punches(nsr) WHERE nsr IS NOT NULL');
    }

    private function alignTimesheetConsolidated(): void
    {
        $this->addMissingColumns('timesheet_consolidated', [
            'needs_recalculation' => ['type' => 'BOOLEAN', 'default' => false, 'null' => false],
            'approved_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->safeQuery('CREATE UNIQUE INDEX IF NOT EXISTS idx_timesheet_consolidated_employee_date_unique ON timesheet_consolidated(employee_id, date)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_timesheet_consolidated_recalc ON timesheet_consolidated(needs_recalculation, date)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_timesheet_consolidated_employee_period ON timesheet_consolidated(employee_id, date DESC)');
    }

    private function alignPendingPunches(): void
    {
        $this->addMissingColumns('pending_punches', [
            'final_punch_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'processed_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_pending_punches_employee_status ON pending_punches(employee_id, status)');
        $this->safeQuery('CREATE INDEX IF NOT EXISTS idx_pending_punches_final_punch ON pending_punches(final_punch_id)');
    }

    /** @param array<string,array<string,mixed>> $columns */
    private function addMissingColumns(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $existing = $this->db->getFieldNames($table);
        foreach ($columns as $name => $definition) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            $this->forge->addColumn($table, [$name => $definition]);
            $existing[] = $name;
        }
    }

    private function safeQuery(string $sql): void
    {
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            log_message('warning', '[Package433] Timesheet schema SQL skipped: ' . $e->getMessage());
        }
    }
}
