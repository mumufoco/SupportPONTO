<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserConsentsTable extends Migration
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
            'consent_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'comment'    => 'Tipo de consentimento',
            ],
            'purpose' => [
                'type'    => 'TEXT',
                'comment' => 'Finalidade do processamento dos dados',
            ],
            'legal_basis' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'comment'    => 'Base legal LGPD (ex: Art. 11, II)',
            ],
            'granted' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'Consentimento concedido',
            ],
            'granted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Data e hora da concessão',
            ],
            'revoked_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Data e hora da revogação',
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
                'null'       => true,
                'comment'    => 'IP de origem do consentimento',
            ],
            'user_agent' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'User agent do navegador',
            ],
            'consent_text' => [
                'type'    => 'TEXT',
                'comment' => 'Texto completo do termo apresentado',
            ],
            'version' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'default'    => '1.0',
                'comment'    => 'Versão do termo de consentimento',
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
        $this->forge->addKey(['employee_id', 'consent_type']);
        $this->forge->addKey('granted');
        $this->forge->addKey(['consent_type', 'granted']);
        $this->forge->addKey('granted_at');

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('user_consents');
    }

    public function down()
    {
        $this->forge->dropTable('user_consents');
    }
}
