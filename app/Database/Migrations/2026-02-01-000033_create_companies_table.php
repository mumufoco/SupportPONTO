<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompaniesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('companies')) {
            log_message('info', 'Migration companies ignorada: tabela já existe.');
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'comment'    => 'Razão social da empresa',
            ],
            'trade_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Nome fantasia',
            ],
            'cnpj' => [
                'type'       => 'VARCHAR',
                'constraint' => '18',
                'unique'     => true,
                'comment'    => 'CNPJ formatado (XX.XXX.XXX/XXXX-XX)',
            ],
            'state_registration' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
                'comment'    => 'Inscrição Estadual',
            ],
            'municipal_registration' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
                'comment'    => 'Inscrição Municipal',
            ],
            'address' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Endereço completo',
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'comment'    => 'Cidade',
            ],
            'state' => [
                'type'       => 'VARCHAR',
                'constraint' => '2',
                'null'       => true,
                'comment'    => 'UF (Estado)',
            ],
            'zip_code' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'null'       => true,
                'comment'    => 'CEP',
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
                'comment'    => 'Telefone principal',
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Email principal',
            ],
            'website' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Website',
            ],
            'logo_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Caminho do arquivo de logo',
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'comment'    => 'Empresa ativa',
            ],
            'settings' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Configurações específicas da empresa',
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
        // cnpj already has unique index from field definition
        $this->forge->addKey('active');

        $this->forge->createTable('companies', true);
    }

    public function down()
    {
        $this->forge->dropTable('companies', true);
    }
}
