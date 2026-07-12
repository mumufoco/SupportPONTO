<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('employees')) {
            log_message('info', 'Migration employees ignorada: tabela já existe.');
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'comment'    => 'Nome completo do funcionário',
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'unique'     => true,
                'comment'    => 'E-mail único para login',
            ],
            'password' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'comment'    => 'Senha hash Argon2id',
            ],
            'cpf' => [
                'type'       => 'VARCHAR',
                'constraint' => '14',
                'unique'     => true,
                'comment'    => 'CPF formatado (XXX.XXX.XXX-XX)',
            ],
            'unique_code' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
                'unique'     => true,
                'comment'    => 'Código único para registro de ponto',
            ],
            'role' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'funcionario',
                'comment'    => 'Perfil de acesso: admin, gestor, funcionario',
            ],
            'department' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'comment'    => 'Departamento',
            ],
            'position' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
                'comment'    => 'Cargo',
            ],
            'expected_hours_daily' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => '8.00',
                'comment'    => 'Jornada diária esperada em horas',
            ],
            'work_schedule_start' => [
                'type'       => 'TIME',
                'null'       => true,
                'comment'    => 'Horário de início do expediente',
            ],
            'work_schedule_end' => [
                'type'       => 'TIME',
                'null'       => true,
                'comment'    => 'Horário de fim do expediente',
            ],
            'active' => [
                'type'       => 'BOOLEAN',
                'default'    => true,
                'comment'    => 'Funcionário ativo no sistema',
            ],
            'extra_hours_balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => '0.00',
                'comment'    => 'Saldo de horas extras (positivo)',
            ],
            'owed_hours_balance' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => '0.00',
                'comment'    => 'Saldo de horas devidas (negativo)',
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
        // email, cpf, and unique_code already have unique indexes from field definitions
        $this->forge->addKey(['role', 'active']);
        $this->forge->addKey('department');

        $this->forge->createTable('employees', true);
    }

    public function down()
    {
        $this->forge->dropTable('employees', true);
    }
}
