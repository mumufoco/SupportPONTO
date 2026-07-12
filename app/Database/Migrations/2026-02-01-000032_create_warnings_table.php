<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWarningsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('warnings')) {
            log_message('info', 'Migration warnings ignorada: tabela já existe.');
            return;
        }

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
                'comment'    => 'FK para employees (advertido)',
            ],
            'warning_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'comment'    => 'Tipo de advertência',
            ],
            'occurrence_date' => [
                'type'    => 'DATE',
                'comment' => 'Data da ocorrência',
            ],
            'reason' => [
                'type'    => 'TEXT',
                'comment' => 'Motivo detalhado da advertência',
            ],
            'evidence_files' => [
                'type'    => 'JSON',
                'null'    => true,
                'comment' => 'Array de caminhos dos arquivos de evidência',
            ],
            'issued_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'comment'    => 'FK para employees (gestor/admin que emitiu)',
            ],
            'pdf_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Caminho do PDF da advertência',
            ],
            'employee_signature' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Assinatura digital do funcionário (base64 ou certificado)',
            ],
            'employee_signed_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Data e hora da assinatura do funcionário',
            ],
            'witness_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Nome da testemunha (se recusa de assinatura)',
            ],
            'witness_cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => '14',
                'null'       => true,
                'comment'    => 'CPF da testemunha',
            ],
            'witness_signature' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Assinatura da testemunha',
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'default'    => 'pendente-assinatura',
                'comment'    => 'Status da advertência',
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
        $this->forge->addKey(['employee_id', 'occurrence_date']);
        $this->forge->addKey(['employee_id', 'warning_type']);
        $this->forge->addKey('status');

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('issued_by', 'employees', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('warnings', true);
    }

    public function down()
    {
        $this->forge->dropTable('warnings', true);
    }
}
