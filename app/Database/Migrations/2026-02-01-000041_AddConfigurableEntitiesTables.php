<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConfigurableEntitiesTables extends Migration
{
    public function up()
    {
        // Criar tabela work_units
        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'active' => [
                'type'    => 'BOOLEAN',
                'default' => true,
                'null'    => false,
            ],
            // CORREÇÃO: Permitir null para timestamps
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name', false, true); // UNIQUE
        $this->forge->createTable('work_units', true);

        // Criar tabela departments
        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'active' => [
                'type'    => 'BOOLEAN',
                'default' => true,
                'null'    => false,
            ],
            // CORREÇÃO: Permitir null para timestamps
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name', false, true); // UNIQUE
        $this->forge->createTable('departments', true);

        // Criar tabela positions
        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'department_id' => [
                'type' => 'INTEGER',
                'null' => true,
            ],
            'active' => [
                'type'    => 'BOOLEAN',
                'default' => true,
                'null'    => false,
            ],
            // CORREÇÃO: Permitir null para timestamps
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['name', 'department_id'], false, true); // UNIQUE composta
        $this->forge->addForeignKey('department_id', 'departments', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('positions', true);

        // Criar tabela roles
        $this->forge->addField([
            'id' => [
                'type'           => 'SERIAL',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'permissions' => [
                'type'    => 'JSONB',
                'default' => '[]',
                'null'    => false,
            ],
            'active' => [
                'type'    => 'BOOLEAN',
                'default' => true,
                'null'    => false,
            ],
            // CORREÇÃO: Permitir null para timestamps
            'created_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'TIMESTAMP',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('name', false, true); // UNIQUE
        $this->forge->createTable('roles', true);

        // Adicionar índices para performance
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_work_units_active ON work_units(active)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_departments_active ON departments(active)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_positions_department_id ON positions(department_id)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_positions_active ON positions(active)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_roles_active ON roles(active)');

        // Inserir dados padrão
        $workUnits = [
            ['name' => 'Sede Principal', 'description' => 'Unidade central da empresa', 'active' => true],
            ['name' => 'Filial São Paulo', 'description' => 'Filial na cidade de São Paulo', 'active' => true],
            ['name' => 'Filial Rio de Janeiro', 'description' => 'Filial na cidade do Rio de Janeiro', 'active' => true],
            ['name' => 'Filial Belo Horizonte', 'description' => 'Filial na cidade de Belo Horizonte', 'active' => true],
        ];

        $departments = [
            ['name' => 'TI', 'description' => 'Tecnologia da Informação', 'active' => true],
            ['name' => 'RH', 'description' => 'Recursos Humanos', 'active' => true],
            ['name' => 'Vendas', 'description' => 'Departamento de Vendas', 'active' => true],
            ['name' => 'Financeiro', 'description' => 'Departamento Financeiro', 'active' => true],
            ['name' => 'Operações', 'description' => 'Departamento de Operações', 'active' => true],
            ['name' => 'Administrativo', 'description' => 'Departamento Administrativo', 'active' => true],
        ];

        $roles = [
            [
                'name' => 'funcionario',
                'description' => 'Colaborador padrão',
                'permissions' => json_encode(['read_own_data', 'punch_clock']),
                'active' => true
            ],
            [
                'name' => 'gestor',
                'description' => 'Gestor de equipe',
                'permissions' => json_encode(['read_own_data', 'punch_clock', 'manage_team', 'read_team_data']),
                'active' => true
            ],
            [
                'name' => 'admin',
                'description' => 'Administrador do sistema',
                'permissions' => json_encode(['read_own_data', 'punch_clock', 'manage_team', 'read_team_data', 'manage_system', 'manage_users']),
                'active' => true
            ],
        ];

        // Inserir dados
        $this->db->table('work_units')->insertBatch($workUnits);
        $this->db->table('departments')->insertBatch($departments);
        $this->db->table('roles')->insertBatch($roles);

        // Inserir positions após departments
        $departmentsData = $this->db->table('departments')->get()->getResultArray();
        $departmentsMap = array_column($departmentsData, 'id', 'name');

        $positions = [
            [
                'name' => 'Analista de Sistemas',
                'description' => 'Analista de desenvolvimento de sistemas',
                'department_id' => $departmentsMap['TI'] ?? null,
                'active' => true
            ],
            [
                'name' => 'Gerente de TI',
                'description' => 'Gerente do departamento de TI',
                'department_id' => $departmentsMap['TI'] ?? null,
                'active' => true
            ],
            [
                'name' => 'Analista de RH',
                'description' => 'Analista de recursos humanos',
                'department_id' => $departmentsMap['RH'] ?? null,
                'active' => true
            ],
            [
                'name' => 'Gerente de RH',
                'description' => 'Gerente do departamento de RH',
                'department_id' => $departmentsMap['RH'] ?? null,
                'active' => true
            ],
        ];

        $this->db->table('positions')->insertBatch($positions);
    }

    public function down()
    {
        // Remover índices
        $this->db->query('DROP INDEX IF EXISTS idx_work_units_active');
        $this->db->query('DROP INDEX IF EXISTS idx_departments_active');
        $this->db->query('DROP INDEX IF EXISTS idx_positions_department_id');
        $this->db->query('DROP INDEX IF EXISTS idx_positions_active');
        $this->db->query('DROP INDEX IF EXISTS idx_roles_active');

        // Remover tabelas
        $this->forge->dropTable('positions', true);
        $this->forge->dropTable('roles', true);
        $this->forge->dropTable('departments', true);
        $this->forge->dropTable('work_units', true);
    }
}
