<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPisAndIsActiveToEmployees extends Migration
{
    public function up()
    {
        // Migração histórica. O status canônico do funcionário passou a ser employees.active
        // na migração 2026-03-26-0002_UnifyActiveColumnEmployees.
        // Mantemos esta migration idempotente para ambientes legados.
        $fields = $this->db->getFieldNames('employees');

        if (!in_array('pis', $fields, true)) {
            $this->db->query("ALTER TABLE employees ADD COLUMN pis VARCHAR(20) NULL");
            $this->db->query("COMMENT ON COLUMN employees.pis IS 'Número PIS/PASEP - Obrigatório pela Portaria MTE'");
        }

        if (!in_array('is_active', $fields, true) && !in_array('active', $fields, true)) {
            $this->db->query("ALTER TABLE employees ADD COLUMN is_active BOOLEAN DEFAULT TRUE NOT NULL");
            $this->db->query("COMMENT ON COLUMN employees.is_active IS 'Status ativo histórico do funcionário (substituído por employees.active)'");
        }
    }

    public function down()
    {
        $this->db->query("ALTER TABLE employees DROP COLUMN IF EXISTS pis");
        $this->db->query("ALTER TABLE employees DROP COLUMN IF EXISTS is_active");
    }
}
