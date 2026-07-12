<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Add warning_type to warnings table
 * 
 * Esta migração adiciona/verifica a coluna warning_type na tabela warnings
 * para garantir compatibilidade com a estrutura esperada.
 */
class AddWarningTypeToWarnings extends Migration
{
    public function up()
    {
        // Check if table exists
        if (!$this->db->tableExists('warnings')) {
            log_message('warning', 'Migration: warnings table does not exist, skipping warning_type addition');
            return;
        }

        // Check if column already exists
        if ($this->db->fieldExists('warning_type', 'warnings')) {
            log_message('info', 'Migration: warning_type column already exists in warnings table');
            return;
        }

        // Add warning_type column
        $fields = [
            'warning_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'verbal',
                'null'       => false,
                'comment'    => 'Tipo de advertência: verbal, escrita ou suspensão',
            ],
        ];

        $this->forge->addColumn('warnings', $fields);
        log_message('info', 'Migration: warning_type column added to warnings table');
    }

    public function down()
    {
        if (!$this->db->tableExists('warnings')) {
            return;
        }

        if ($this->db->fieldExists('warning_type', 'warnings')) {
            $this->forge->dropColumn('warnings', 'warning_type');
            log_message('info', 'Migration: warning_type column dropped from warnings table');
        }
    }
}
