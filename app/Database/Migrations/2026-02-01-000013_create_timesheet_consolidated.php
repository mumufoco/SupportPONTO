<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Create timesheet_consolidated table
 * 
 * Esta migração cria a tabela timesheet_consolidated para armazenar
 * dados consolidados diários de ponto dos funcionários.
 */
class CreateTimesheetConsolidated extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'FK para employees',
            ],
            'date' => [
                'type'    => 'DATE',
                'comment' => 'Data do registro consolidado',
            ],
            'total_worked' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
                'comment'    => 'Total de horas trabalhadas no dia',
            ],
            'expected' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 8.00,
                'comment'    => 'Horas esperadas para o dia (jornada)',
            ],
            'extra' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
                'comment'    => 'Horas extras trabalhadas',
            ],
            'owed' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
                'comment'    => 'Horas devidas (faltaram trabalhar)',
            ],
            'interval_violation' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
                'comment'    => 'Horas de violação de intervalo',
            ],
            'justified' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'Possui justificativa aprovada?',
            ],
            'incomplete' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'Marcações incompletas/inconsistentes?',
            ],
            'justification_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK para justifications',
            ],
            'punches_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => 'Quantidade de registros de ponto no dia',
            ],
            'first_punch' => [
                'type'    => 'TIME',
                'null'    => true,
                'comment' => 'Horário da primeira marcação',
            ],
            'last_punch' => [
                'type'    => 'TIME',
                'null'    => true,
                'comment' => 'Horário da última marcação',
            ],
            'total_interval' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
                'comment'    => 'Total de horas de intervalo',
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Observações sobre o cálculo',
            ],
            'processed_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Quando foi processado',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'date']);
        $this->forge->addKey('date');
        $this->forge->addKey('incomplete');
        $this->forge->addKey('justified');

        // Unique constraint: one record per employee per date
        $this->forge->addUniqueKey(['employee_id', 'date'], 'uk_employee_date');

        $this->forge->createTable('timesheet_consolidated', true); // true = IF NOT EXISTS
        
        log_message('info', 'Migration: timesheet_consolidated table created successfully');
    }

    public function down()
    {
        $this->forge->dropTable('timesheet_consolidated', true);
        log_message('info', 'Migration: timesheet_consolidated table dropped');
    }
}
