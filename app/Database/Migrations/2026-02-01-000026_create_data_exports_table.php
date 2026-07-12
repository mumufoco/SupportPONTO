<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDataExportsTable extends Migration
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
            'export_id' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'unique'     => true,
                'comment'    => 'Identificador único da exportação',
            ],
            'employee_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'FK para employees',
            ],
            'requested_by' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'E-mail de quem solicitou (pode ser o próprio funcionário ou admin)',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'pending',
                'comment'    => 'Status da exportação',
            ],
            'file_size' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'Tamanho do arquivo ZIP em bytes',
            ],
            'download_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
                'comment'    => 'Quantas vezes o arquivo foi baixado',
            ],
            'last_downloaded_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Data/hora do último download',
            ],
            'expires_at' => [
                'type'    => 'DATETIME',
                'comment' => 'Data/hora de expiração (48h após criação)',
            ],
            'error_message' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Mensagem de erro caso status = failed',
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
        // export_id already has unique index from field definition
        $this->forge->addKey('employee_id');
        $this->forge->addKey('status');
        $this->forge->addKey('expires_at');
        $this->forge->addKey('created_at');

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('data_exports');
    }

    public function down()
    {
        $this->forge->dropTable('data_exports');
    }
}
