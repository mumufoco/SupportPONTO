<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceSettingsTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('settings')) {
            return;
        }

        $columns = $this->db->getFieldNames('settings');

        $this->addMissingColumn($columns, 'key', [
            'type'       => 'VARCHAR',
            'constraint' => 100,
            'null'       => true,
            'comment'    => 'Chave única da configuração',
        ]);

        $this->addMissingColumn($columns, 'value', [
            'type'    => 'TEXT',
            'null'    => true,
            'comment' => 'Valor da configuração (pode ser JSON)',
        ]);

        $this->addMissingColumn($columns, 'type', [
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'default'    => 'string',
            'comment'    => 'Tipo do valor',
        ]);

        $this->addMissingColumn($columns, 'group', [
            'type'       => 'VARCHAR',
            'constraint' => 50,
            'default'    => 'general',
            'comment'    => 'Grupo da configuração',
        ]);

        $this->addMissingColumn($columns, 'description', [
            'type' => 'TEXT',
            'null' => true,
        ]);

        $this->addMissingColumn($columns, 'editable', [
            'type'    => 'BOOLEAN',
            'default' => true,
        ]);

        $this->addMissingColumn($columns, 'is_encrypted', [
            'type'    => 'BOOLEAN',
            'default' => false,
        ]);

        $this->addMissingColumn($columns, 'created_at', [
            'type' => 'DATETIME',
            'null' => true,
        ]);

        $this->addMissingColumn($columns, 'updated_at', [
            'type' => 'DATETIME',
            'null' => true,
        ]);

        $columns = $this->db->getFieldNames('settings');

        if (in_array('setting_key', $columns, true)) {
            $this->db->query('UPDATE settings SET key = COALESCE(key, setting_key) WHERE setting_key IS NOT NULL');
        }

        if (in_array('setting_value', $columns, true)) {
            $this->db->query('UPDATE settings SET value = COALESCE(value, setting_value) WHERE setting_value IS NOT NULL');
        }

        if (in_array('setting_type', $columns, true)) {
            $this->db->query('UPDATE settings SET type = COALESCE(type, setting_type) WHERE setting_type IS NOT NULL');
        }

        if (in_array('setting_group', $columns, true)) {
            $this->db->query('UPDATE settings SET "group" = COALESCE("group", setting_group) WHERE setting_group IS NOT NULL');
        }

        $this->db->query("UPDATE settings SET type = COALESCE(NULLIF(type, ''), 'string')");
        $this->db->query("UPDATE settings SET \"group\" = COALESCE(NULLIF(\"group\", ''), 'general')");

        try {
            $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS settings_key_unique ON settings(key)');
            $this->db->query('CREATE INDEX IF NOT EXISTS idx_settings_group_key ON settings("group", key)');
        } catch (\Throwable $e) {
            log_message('warning', 'EnhanceSettingsTable index creation warning: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // Schema enhancement intentionally non-destructive.
    }

    private function addMissingColumn(array $columns, string $name, array $definition): void
    {
        if (! in_array($name, $columns, true)) {
            $this->forge->addColumn('settings', [$name => $definition]);
        }
    }
}