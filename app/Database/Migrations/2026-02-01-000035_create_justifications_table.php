<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateJustificationsTable extends Migration
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
            'justification_date' => [
                'type'    => 'DATE',
                'comment' => 'Data da ausência/atraso',
            ],
            'justification_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'comment'    => 'Tipo de justificativa',
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'default'    => 'outro',
                'comment'    => 'Categoria da justificativa',
            ],
            'reason' => [
                'type'    => 'TEXT',
                'comment' => 'Motivo detalhado (min 50 chars)',
            ],
            'attachments' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Array de caminhos dos arquivos anexados',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'pendente',
                'comment'    => 'Status da justificativa',
            ],
            'approved_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK para employees (gestor que aprovou)',
            ],
            'approved_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'comment' => 'Data e hora da aprovação',
            ],
            'rejection_reason' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Motivo da rejeição (obrigatório se rejeitado)',
            ],
            'submitted_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK para employees (quem enviou, pode ser diferente do employee_id)',
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
        $this->forge->addKey(['employee_id', 'justification_date']);
        $this->forge->addKey('status');
        $this->forge->addKey(['justification_type', 'status']);

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('approved_by', 'employees', 'id', 'SET NULL', 'CASCADE');
        $this->forge->addForeignKey('submitted_by', 'employees', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('justifications');
    }

    public function down()
    {
        $this->forge->dropTable('justifications');
    }
}
