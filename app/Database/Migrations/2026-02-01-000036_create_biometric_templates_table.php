<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBiometricTemplatesTable extends Migration
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
            'biometric_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'comment'    => 'Tipo de biometria',
            ],
            'template_data' => [
                'type'    => 'BYTEA',
                'null'    => true,
                'comment' => 'Template biométrico criptografado (para fingerprint)',
            ],
            'template_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => '64',
                'null'       => true,
                'comment'    => 'Hash SHA-256 da foto/template',
            ],
            'image_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => '64',
                'null'       => true,
                'comment'    => 'Hash SHA-256 da imagem facial (usado pela API DeepFace)',
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Caminho da foto facial (storage/faces/employee_id/)',
            ],
            'enrollment_quality' => [
                'type'       => 'INT',
                'constraint' => 3,
                'null'       => true,
                'comment'    => 'Qualidade do cadastro (0-100)',
            ],
            'model_used' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
                'comment'    => 'Modelo DeepFace usado (VGG-Face, Facenet, etc)',
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'comment'    => 'Template ativo',
            ],
            'enrolled_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'FK para employees (quem cadastrou)',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'biometric_type']);
        $this->forge->addKey('active');

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('enrolled_by', 'employees', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('biometric_templates');
    }

    public function down()
    {
        $this->forge->dropTable('biometric_templates');
    }
}
