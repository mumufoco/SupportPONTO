<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class AuthGroupsSeeder extends Seeder
{
    public function run()
    {
        echo "Creating auth groups for CodeIgniter Shield...\n";

        foreach (['auth_groups', 'auth_permissions', 'auth_groups_permissions'] as $table) {
            if (! $this->db->tableExists($table)) {
                throw new RuntimeException(
                    sprintf(
                        "Tabela obrigatória '%s' não existe. Execute as migrations do Shield e do aplicativo com 'php spark migrate --all' antes de rodar o AuthGroupsSeeder.",
                        $table
                    )
                );
            }
        }

        $groups = [
            [
                'name' => 'admin',
                'description' => 'Administrador - Acesso Total ao Sistema',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'gestor',
                'description' => 'Gestor - Gerencia Equipe e Aprovações',
                'created_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'funcionario',
                'description' => 'Funcionário - Registro de Ponto',
                'created_at' => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($groups as $group) {
            $existing = $this->db->table('auth_groups')
                ->where('name', $group['name'])
                ->get()
                ->getRow();

            if (! $existing) {
                if (! $this->db->table('auth_groups')->insert($group)) {
                    throw new RuntimeException('Falha ao criar grupo de autenticação: ' . $group['name']);
                }
                echo "✓ Created group: {$group['name']}\n";
            } else {
                echo "- Group already exists: {$group['name']}\n";
            }
        }

        $permissions = [
            ['name' => 'admin.*', 'description' => 'All admin permissions'],
            ['name' => 'manage.employees', 'description' => 'Manage employees'],
            ['name' => 'approve.justifications', 'description' => 'Approve justifications'],
            ['name' => 'view.reports', 'description' => 'View reports'],
            ['name' => 'manage.team', 'description' => 'Manage team'],
            ['name' => 'clock.inout', 'description' => 'Clock in/out'],
            ['name' => 'view.own.data', 'description' => 'View own data'],
            ['name' => 'submit.justification', 'description' => 'Submit justification'],
        ];

        foreach ($permissions as $permission) {
            $existing = $this->db->table('auth_permissions')
                ->where('name', $permission['name'])
                ->get()
                ->getRow();

            if (! $existing) {
                $permission['created_at'] = date('Y-m-d H:i:s');
                if (! $this->db->table('auth_permissions')->insert($permission)) {
                    throw new RuntimeException('Falha ao criar permissão: ' . $permission['name']);
                }
                echo "✓ Created permission: {$permission['name']}\n";
            }
        }

        $groupPermissions = [
            'admin' => ['admin.*'],
            'gestor' => ['manage.employees', 'approve.justifications', 'view.reports', 'manage.team', 'clock.inout', 'view.own.data'],
            'funcionario' => ['clock.inout', 'view.own.data', 'submit.justification'],
        ];

        foreach ($groupPermissions as $groupName => $permissionNames) {
            $group = $this->db->table('auth_groups')
                ->where('name', $groupName)
                ->get()
                ->getRow();

            if (! $group) {
                throw new RuntimeException('Grupo obrigatório não encontrado durante a associação de permissões: ' . $groupName);
            }

            foreach ($permissionNames as $permName) {
                $permission = $this->db->table('auth_permissions')
                    ->where('name', $permName)
                    ->get()
                    ->getRow();

                if (! $permission) {
                    throw new RuntimeException('Permissão obrigatória não encontrada durante a associação: ' . $permName);
                }

                $existing = $this->db->table('auth_groups_permissions')
                    ->where('group_id', $group->id)
                    ->where('permission_id', $permission->id)
                    ->get()
                    ->getRow();

                if (! $existing) {
                    if (! $this->db->table('auth_groups_permissions')->insert([
                        'group_id' => $group->id,
                        'permission_id' => $permission->id,
                        'created_at' => date('Y-m-d H:i:s'),
                    ])) {
                        throw new RuntimeException(sprintf("Falha ao associar a permissão '%s' ao grupo '%s'.", $permName, $groupName));
                    }
                    echo "✓ Associated permission '{$permName}' to group '{$groupName}'\n";
                }
            }
        }

        echo "\n✅ Auth groups seeder completed successfully!\n";
    }
}
