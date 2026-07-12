<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RbacRolesAndPermissions extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('roles')) {
            return;
        }

        $roles = [
            'admin' => [
                'description' => 'Administrador do sistema',
                'permissions' => ['*'],
            ],
            'rh' => [
                'description' => 'Recursos Humanos',
                'permissions' => ['employees.manage', 'employees.approve', 'reports.view', 'reports.export', 'warnings.manage', 'justifications.approve', 'biometric.manage'],
            ],
            'gestor' => [
                'description' => 'Gestor de equipe',
                'permissions' => ['employees.manage.team', 'reports.view', 'reports.export', 'warnings.manage.team', 'justifications.approve.team', 'biometric.manage.team', 'audit.view.limited', 'audit.view.details'],
            ],
            'funcionario' => [
                'description' => 'Colaborador padrão',
                'permissions' => ['profile.self', 'timesheet.self', 'warnings.view.self', 'justifications.self', 'biometric.self'],
            ],
            'auditor' => [
                'description' => 'Auditor de conformidade',
                'permissions' => ['audit.view', 'audit.export', 'reports.audit', 'audit.view.details', 'compliance.view'],
            ],
            'dpo' => [
                'description' => 'Encarregado de Dados (DPO)',
                'permissions' => ['lgpd.view', 'lgpd.manage', 'audit.view', 'audit.export', 'reports.audit', 'audit.view.details', 'compliance.view'],
            ],
        ];

        $now = date('Y-m-d H:i:s');

        foreach ($roles as $name => $payload) {
            $existing = $this->db->table('roles')->where('name', $name)->get()->getRowArray();
            $data = [
                'name' => $name,
                'description' => $payload['description'],
                'permissions' => json_encode($payload['permissions'], JSON_UNESCAPED_UNICODE),
                'active' => true,
                'updated_at' => $now,
            ];

            if ($existing) {
                $this->db->table('roles')->where('id', $existing['id'])->update($data);
            } else {
                $data['created_at'] = $now;
                $this->db->table('roles')->insert($data);
            }
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('roles')) {
            return;
        }

        $this->db->table('roles')->whereIn('name', ['auditor', 'dpo', 'rh'])->delete();
    }
}
