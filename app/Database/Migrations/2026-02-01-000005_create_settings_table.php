<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration to create the canonical settings table for PostgreSQL.
 */
class CreateSettingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('settings')) {
            log_message('info', 'Settings table already exists, skipping creation');
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'key' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => false,
                'comment'    => 'Chave única da configuração',
            ],
            'value' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Valor da configuração (pode ser JSON)',
            ],
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'string',
                'comment'    => 'Tipo do valor persistido',
            ],
            'group' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'general',
                'comment'    => 'Grupo lógico da configuração',
            ],
            'description' => [
                'type'    => 'TEXT',
                'null'    => true,
            ],
            'editable' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'is_encrypted' => [
                'type'    => 'BOOLEAN',
                'default' => false,
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
        $this->forge->addUniqueKey('key', 'settings_key_unique');
        $this->forge->addKey(['group', 'key'], false, false, 'idx_settings_group_key');
        $this->forge->createTable('settings', true);

        log_message('info', 'Canonical settings table created successfully');
    }

    public function down()
    {
        $this->forge->dropTable('settings', true);
    }
}
