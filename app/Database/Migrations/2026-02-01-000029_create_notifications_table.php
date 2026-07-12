<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTable extends Migration
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
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'FK para employees (destinatário)',
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'comment'    => 'Tipo de notificação',
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'comment'    => 'Título da notificação',
            ],
            'message' => [
                'type'    => 'TEXT',
                'comment' => 'Conteúdo da notificação',
            ],
            'link' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Link relacionado à notificação',
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
                'comment'    => 'Ícone para exibição',
            ],
            'priority' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'normal',
                'comment'    => 'Prioridade da notificação',
            ],
            'read' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'Notificação lida',
            ],
            'read_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Data e hora da leitura',
            ],
            'sent_email' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'E-mail enviado',
            ],
            'sent_push' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'Push notification enviada',
            ],
            'sent_sms' => [
                'type'       => 'BOOLEAN',
                'default'    => false,
                'comment'    => 'SMS enviado',
            ],
            'related_entity_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'comment'    => 'Tipo da entidade relacionada',
            ],
            'related_entity_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'ID da entidade relacionada',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Data de expiração da notificação',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'read', 'created_at']);
        $this->forge->addKey(['user_id', 'type']);
        $this->forge->addKey('created_at');
        $this->forge->addKey('priority');

        $this->forge->addForeignKey('user_id', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('notifications');
    }

    public function down()
    {
        $this->forge->dropTable('notifications');
    }
}
