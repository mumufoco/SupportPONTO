<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * v1.1.279 — Rastreamento de armazenamento criptografado de faces
 *
 * Adiciona coluna para rastrear o caminho do arquivo criptografado
 * nos templates biométricos, separando do caminho do arquivo temporário.
 */
class AddEncryptedFaceStorageTracking extends Migration
{
    public function up(): void
    {
        // Adicionar coluna de rastreamento de criptografia na tabela de templates biométricos
        if ($this->db->tableExists('biometric_templates')) {
            $fields = $this->db->getFieldNames('biometric_templates');
            if (!in_array('encrypted_storage_path', $fields, true)) {
                $this->forge->addColumn('biometric_templates', [
                    'encrypted_storage_path' => [
                        'type'    => 'VARCHAR',
                        'constraint' => 500,
                        'null'    => true,
                        'comment' => 'Caminho do arquivo .enc no armazenamento criptografado PHP',
                        'after'   => 'template_data',
                    ],
                    'encryption_version' => [
                        'type'    => 'SMALLINT',
                        'default' => 1,
                        'null'    => true,
                        'comment' => 'Versão da chave de criptografia usada',
                        'after'   => 'encrypted_storage_path',
                    ],
                ]);
            }
        }

        // Tabela de auditoria de operações de criptografia biométrica
        if (!$this->db->tableExists('biometric_encryption_audit')) {
            $this->forge->addField([
                'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'employee_id'   => ['type' => 'INTEGER', 'unsigned' => true],
                'operation'     => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'encrypt|decrypt|purge|verify'],
                'file_count'    => ['type' => 'SMALLINT', 'default' => 1],
                'performed_by'  => ['type' => 'INTEGER', 'null' => true],
                'performed_at'  => ['type' => 'TIMESTAMP'],
                'notes'         => ['type' => 'TEXT', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('employee_id');
            $this->forge->createTable('biometric_encryption_audit');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('biometric_templates')) {
            $this->forge->dropColumn('biometric_templates', 'encrypted_storage_path');
            $this->forge->dropColumn('biometric_templates', 'encryption_version');
        }
        $this->forge->dropTable('biometric_encryption_audit', true);
    }
}
