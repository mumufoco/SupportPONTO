<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMissingColumnsToTimePunches extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('time_punches')) {
            log_message('warning', 'Migration AddMissingColumnsToTimePunches ignorada: tabela time_punches ainda não existe.');
            return;
        }

        $columns = [
            'hash' => [
                'type' => 'VARCHAR',
                'constraint' => 64,
                'null' => true,
            ],
            'within_geofence' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => true,
            ],
            'method' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'default' => 'manual',
                'null' => true,
            ],
        ];

        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'time_punches')) {
                $this->forge->addColumn('time_punches', [$name => $definition]);
            }
        }

        $this->db->query("CREATE INDEX IF NOT EXISTS idx_time_punches_hash ON time_punches(hash)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_time_punches_within_geofence ON time_punches(within_geofence)");
        $this->db->query("CREATE INDEX IF NOT EXISTS idx_time_punches_method ON time_punches(method)");
    }

    public function down()
    {
        $this->db->query("DROP INDEX IF EXISTS idx_time_punches_method");
        $this->db->query("DROP INDEX IF EXISTS idx_time_punches_within_geofence");
        $this->db->query("DROP INDEX IF EXISTS idx_time_punches_hash");

        foreach (['method', 'within_geofence', 'hash'] as $column) {
            if ($this->db->tableExists('time_punches') && $this->db->fieldExists($column, 'time_punches')) {
                $this->forge->dropColumn('time_punches', $column);
            }
        }
    }
}
