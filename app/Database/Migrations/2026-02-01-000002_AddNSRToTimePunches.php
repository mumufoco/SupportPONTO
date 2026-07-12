<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNSRToTimePunches extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('time_punches')) {
            log_message('warning', 'Migration AddNSRToTimePunches ignorada: tabela time_punches ainda não existe.');
            return;
        }

        if (!$this->db->fieldExists('nsr', 'time_punches')) {
            $this->forge->addColumn('time_punches', [
                'nsr' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'unique' => true,
                    'null' => true,
                ],
            ]);
        }

        // Fase 8: a geração por trigger/sequence foi descontinuada.
        // A única fonte canônica de NSR é app\Services\Timesheet\NsrGeneratorService
        // usando a tabela nsr_counter com incremento atômico.
        $this->db->query('DROP TRIGGER IF EXISTS trigger_set_nsr ON time_punches');
        $this->db->query('DROP FUNCTION IF EXISTS set_nsr()');
        $this->db->query('DROP FUNCTION IF EXISTS generate_nsr()');
        $this->db->query('DROP SEQUENCE IF EXISTS nsr_sequence');
        $this->db->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_time_punches_nsr_unique ON time_punches(nsr) WHERE nsr IS NOT NULL");
    }

    public function down()
    {
        $this->db->query("DROP TRIGGER IF EXISTS trigger_set_nsr ON time_punches");
        $this->db->query("DROP FUNCTION IF EXISTS set_nsr()");
        $this->db->query("DROP FUNCTION IF EXISTS generate_nsr()");
        $this->db->query("DROP SEQUENCE IF EXISTS nsr_sequence");

        if ($this->db->tableExists('time_punches') && $this->db->fieldExists('nsr', 'time_punches')) {
            $this->forge->dropColumn('time_punches', 'nsr');
        }
    }
}
