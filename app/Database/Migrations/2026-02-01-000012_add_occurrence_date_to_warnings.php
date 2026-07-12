<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Add occurrence_date to warnings table
 * 
 * Esta migração adiciona a coluna occurrence_date na tabela warnings
 * se ela ainda não existir, garantindo compatibilidade.
 */
class AddOccurrenceDateToWarnings extends Migration
{
    public function up()
    {
        // Check if table exists
        if (!$this->db->tableExists('warnings')) {
            log_message('warning', 'Migration: warnings table does not exist, skipping occurrence_date addition');
            return;
        }

        // Check if column already exists
        if ($this->db->fieldExists('occurrence_date', 'warnings')) {
            log_message('info', 'Migration: occurrence_date column already exists in warnings table');
            return;
        }

        // Add occurrence_date column
        $fields = [
            'occurrence_date' => [
                'type'    => 'DATE',
                'null'    => true,
                'comment' => 'Data da ocorrência da advertência',
                'after'   => 'warning_type',
            ],
        ];

        $this->forge->addColumn('warnings', $fields);
        log_message('info', 'Migration: occurrence_date column added to warnings table');

        // If there are existing records without occurrence_date, set it to created_at date
        if ($this->db->tableExists('warnings')) {
            $this->db->query("
                UPDATE warnings 
                SET occurrence_date = DATE(created_at) 
                WHERE occurrence_date IS NULL AND created_at IS NOT NULL
            ");
            log_message('info', 'Migration: Updated existing warnings with occurrence_date from created_at');
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('warnings')) {
            return;
        }

        if ($this->db->fieldExists('occurrence_date', 'warnings')) {
            $this->forge->dropColumn('warnings', 'occurrence_date');
            log_message('info', 'Migration: occurrence_date column dropped from warnings table');
        }
    }
}
