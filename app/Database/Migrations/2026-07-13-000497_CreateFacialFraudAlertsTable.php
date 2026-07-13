<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Histórico de alertas de possível fraude na segunda camada de verificação
 * facial (código/CPF/QR/biometria digital): quando a foto capturada não
 * corresponde ao cadastro biométrico do funcionário já identificado pelo
 * método principal, o ponto é registrado normalmente (não bloqueia o
 * funcionário), mas fica um registro aqui para revisão de gestor/RH —
 * histórico auditável, útil para eventual processo disciplinar.
 */
class CreateFacialFraudAlertsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'time_punch_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'method' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'comment'    => 'codigo|cpf|qrcode|biometria — método principal usado no registro',
            ],
            'similarity_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'null'       => true,
            ],
            'threshold_used' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'null'       => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'pending',
                'comment'    => 'pending|reviewed|dismissed',
            ],
            'reviewed_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'reviewed_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'review_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey('time_punch_id');
        $this->forge->addKey('status');
        $this->forge->addKey('created_at');
        // RESTRICT (não CASCADE) de propósito: esta tabela É o histórico para eventual
        // processo disciplinar (ver CRIT-08 na auditoria — mesmo raciocínio já aplicado
        // a time_punches/justifications/warnings/biometric_templates). Um DELETE físico
        // de employees não pode apagar silenciosamente esse rastro.
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('time_punch_id', 'time_punches', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('facial_fraud_alerts', true);

        $this->db->query('CREATE INDEX idx_facial_fraud_employee_date ON facial_fraud_alerts (employee_id, created_at)');
    }

    public function down()
    {
        $this->forge->dropTable('facial_fraud_alerts', true);
    }
}
