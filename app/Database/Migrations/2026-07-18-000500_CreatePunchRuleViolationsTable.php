<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Detecção automática de irregularidades trabalhistas (CLT) a partir das
 * marcações de ponto. Ver app/Services/Timesheet/PunchComplianceService.php
 * e app/Enums/PunchViolationType.php para as regras detectadas.
 */
class CreatePunchRuleViolationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => false],
            'violation_type' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false, 'comment' => 'ver App\\Enums\\PunchViolationType'],
            'reference_date' => ['type' => 'DATE', 'null' => false],
            'details' => ['type' => 'JSONB', 'null' => true, 'comment' => 'Valores calculados que embasaram a detecção'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'pendente', 'comment' => 'pendente|tratada'],
            'reviewed_by' => ['type' => 'INTEGER', 'null' => true, 'comment' => 'employee_id do gestor/RH que tratou'],
            'reviewed_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'review_notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'violation_type', 'reference_date'], false, true);
        $this->forge->addKey('status');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('punch_rule_violations');
    }

    public function down(): void
    {
        $this->forge->dropTable('punch_rule_violations', true);
    }
}
