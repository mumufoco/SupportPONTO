<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Anexos de documentos do colaborador (RG, CPF, CNH, certificados, outros)
 * -- Aba "Upload de Documentos" do cadastro. Ver App\Enums\DocumentType.
 * Modelada em cima de employee_dependents (tabela filha 1:N, soft delete).
 */
class CreateEmployeeDocumentsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('employee_documents')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => false],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false, 'comment' => 'ver App\\Enums\\DocumentType'],
            'original_filename' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'stored_path' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'file_size' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'uploaded_by' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addKey(['employee_id', 'document_type']);
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('uploaded_by', 'employees', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('employee_documents');
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_documents', true);
    }
}
