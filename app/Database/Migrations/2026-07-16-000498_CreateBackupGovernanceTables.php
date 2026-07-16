<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Governanca de backup, no mesmo espirito da tela equivalente do SupportCHECK
 * (BackupCheck/RestoreTestRecord): persiste um HISTORICO de checagens de saude
 * do backup (nao so o estado ao vivo) e um registro de quando alguem de fato
 * testou uma restauracao de verdade -- ter um backup no disco nao prova que
 * ele restaura; so um teste de restauracao registrado prova isso.
 *
 * Diferente do CHECK (multi-empresa, usa company_id), o SupportPONTO e
 * single-tenant -- sem coluna de empresa.
 */
class CreateBackupGovernanceTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'last_backup_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'backup_size_bytes' => [
                'type'     => 'BIGINT',
                'default'  => 0,
            ],
            'destination' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'integrity_ok' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'critical_files' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'risks' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'checked_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('checked_at');
        $this->forge->createTable('backup_checks');

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'tested_by' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'tested_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('tested_at');
        $this->forge->addForeignKey('tested_by', 'employees', 'id', '', 'SET NULL');
        $this->forge->createTable('restore_test_records');
    }

    public function down()
    {
        $this->forge->dropTable('restore_test_records', true);
        $this->forge->dropTable('backup_checks', true);
    }
}
