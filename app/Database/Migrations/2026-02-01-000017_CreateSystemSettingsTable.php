<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSystemSettingsTable extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('settings')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'key' => [
                    'type' => 'VARCHAR',
                    'constraint' => '100',
                ],
                'value' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'type' => [
                    'type' => 'VARCHAR',
                    'constraint' => '20',
                    'default' => 'string',
                ],
                'group' => [
                    'type' => 'VARCHAR',
                    'constraint' => '30',
                    'default' => 'system',
                ],
                'is_encrypted' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                ],
                'description' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('key');
            $this->forge->addKey('group');
            $this->forge->createTable('settings', true);
        }

        $this->insertDefaultSettings();
    }

    public function down()
    {
        // Mantém a tabela canônica intacta; a reversão automática não é segura.
    }

    protected function insertDefaultSettings(): void
    {
        $now = date('Y-m-d H:i:s');
        $defaultSettings = [
            ['key' => 'company_name', 'value' => 'Sistema de Ponto Eletrônico', 'type' => 'string', 'group' => 'appearance', 'description' => 'Nome da empresa'],
            ['key' => 'primary_color', 'value' => '#3B82F6', 'type' => 'string', 'group' => 'appearance', 'description' => 'Cor primária do sistema'],
            ['key' => 'theme_mode', 'value' => 'light', 'type' => 'string', 'group' => 'appearance', 'description' => 'Modo do tema (light/dark/auto)'],
            ['key' => 'session_timeout', 'value' => '3600', 'type' => 'integer', 'group' => 'authentication', 'description' => 'Tempo de sessão em segundos'],
            ['key' => 'enable_2fa', 'value' => '0', 'type' => 'boolean', 'group' => 'authentication', 'description' => 'Habilitar autenticação de dois fatores'],
            ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer', 'group' => 'authentication', 'description' => 'Máximo de tentativas de login'],
            ['key' => 'company_cnpj', 'value' => '', 'type' => 'string', 'group' => 'system', 'description' => 'CNPJ da empresa'],
            ['key' => 'timezone', 'value' => 'America/Sao_Paulo', 'type' => 'string', 'group' => 'system', 'description' => 'Fuso horário do sistema'],
            ['key' => 'language', 'value' => 'pt-BR', 'type' => 'string', 'group' => 'system', 'description' => 'Idioma do sistema'],
            ['key' => 'password_min_length', 'value' => '8', 'type' => 'integer', 'group' => 'security', 'description' => 'Tamanho mínimo da senha'],
            ['key' => 'password_require_special', 'value' => '1', 'type' => 'boolean', 'group' => 'security', 'description' => 'Exigir caracteres especiais na senha'],
            ['key' => 'enable_audit_log', 'value' => '1', 'type' => 'boolean', 'group' => 'security', 'description' => 'Habilitar log de auditoria'],
        ];

        foreach ($defaultSettings as $setting) {
            $exists = $this->db->table('settings')->where('key', $setting['key'])->countAllResults() > 0;
            if ($exists) {
                continue;
            }

            $this->db->table('settings')->insert(array_merge($setting, [
                'created_at' => $now,
                'updated_at' => $now,
                'is_encrypted' => false,
            ]));
        }
    }
}
