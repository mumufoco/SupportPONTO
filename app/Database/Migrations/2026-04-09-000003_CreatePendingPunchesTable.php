<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * v1.1.279 — Justificativa por Falha Automática
 *
 * Cria a tabela pending_punches para armazenar registros de ponto
 * pendentes de aprovação quando todos os métodos automáticos falharam
 * por razão técnica. Conformidade com Portaria MTE 671/2021 Art. 74 CLT.
 */
class CreatePendingPunchesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => false],
            'intended_punch_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'comment' => 'entrada|saida|intervalo_inicio|intervalo_fim'],
            'intended_time' => ['type' => 'TIMESTAMP', 'null' => false, 'comment' => 'Horário pretendido pelo colaborador'],
            'nsr_provisional' => ['type' => 'BIGINT', 'null' => true, 'comment' => 'NSR provisório gerado no momento do envio'],
            'justification_text' => ['type' => 'TEXT', 'null' => false],
            'situation_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => false, 'default' => 'equipment_failure'],
            'evidence_package' => ['type' => 'JSONB', 'null' => true, 'comment' => 'Log técnico das tentativas automáticas + contexto do sistema'],
            'methods_attempted' => ['type' => 'JSONB', 'null' => true, 'comment' => 'Array com cada método tentado e seu código de erro'],
            'technical_failures_count' => ['type' => 'SMALLINT', 'default' => 0],
            'terminal_id' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'TEXT', 'null' => true],
            'geolocation_lat' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'geolocation_lng' => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => false, 'default' => 'pending', 'comment' => 'pending|approved|rejected|expired'],
            'reviewed_by' => ['type' => 'INTEGER', 'null' => true, 'comment' => 'employee_id do gestor/RH que revisou'],
            'reviewed_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'review_notes' => ['type' => 'TEXT', 'null' => true],
            'final_punch_id' => ['type' => 'BIGINT', 'null' => true, 'comment' => 'FK para time_punches após aprovação'],
            'expires_at' => ['type' => 'TIMESTAMP', 'null' => true, 'comment' => 'Prazo para aprovação (padrão: 24h após criação)'],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('status');
        $this->forge->addKey(['status', 'expires_at']);
        $this->forge->addKey(['employee_id', 'intended_time']);
        $this->forge->createTable('pending_punches');
    }

    public function down(): void
    {
        $this->forge->dropTable('pending_punches', true);
    }
}
