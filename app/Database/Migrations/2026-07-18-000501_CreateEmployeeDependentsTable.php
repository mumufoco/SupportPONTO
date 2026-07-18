<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cadastro de dependentes de colaboradores (eSocial S-2200, IRRF, salário-família).
 * Ver app/Enums/DependentKinshipType.php para os graus de parentesco suportados.
 */
class CreateEmployeeDependentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'employee_id' => ['type' => 'INTEGER', 'unsigned' => true, 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'cpf' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false, 'comment' => 'Criptografado em repouso (EncryptionService), mesma estratégia de employees.cpf'],
            'cpf_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => false, 'comment' => 'SHA-256 do CPF em dígitos, para unicidade/busca'],
            'birth_date' => ['type' => 'DATE', 'null' => false],
            'kinship_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => false, 'comment' => 'ver App\\Enums\\DependentKinshipType'],
            'irrf_dependent' => ['type' => 'BOOLEAN', 'null' => false, 'default' => true],
            'family_allowance_dependent' => ['type' => 'BOOLEAN', 'null' => false, 'default' => false],
            'has_disability' => ['type' => 'BOOLEAN', 'null' => false, 'default' => false],
            'active' => ['type' => 'BOOLEAN', 'null' => false, 'default' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => false],
            'updated_at' => ['type' => 'TIMESTAMP', 'null' => true],
            'deleted_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['employee_id', 'cpf_hash'], false, true);
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('employee_dependents');
    }

    public function down(): void
    {
        $this->forge->dropTable('employee_dependents', true);
    }
}
