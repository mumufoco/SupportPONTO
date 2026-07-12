<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Services\AuthorizationService;

if (!function_exists('sp_normalize_role_value')) {
    function sp_normalize_role_value(?string $role): string
    {
        try {
            return Role::normalize((string) ($role ?? Role::Funcionario->value))->value;
        } catch (\ValueError) {
            return Role::Funcionario->value;
        }
    }
}

if (!function_exists('sp_dashboard_url_for_role')) {
    function sp_dashboard_url_for_role(?string $role): string
    {
        return match (sp_normalize_role_value($role)) {
            Role::Admin->value => '/dashboard/admin',
            Role::Gestor->value, Role::RH->value => '/dashboard/manager',
            Role::DPO->value => '/dashboard/dpo',
            Role::Auditor->value => '/audit',
            default => '/dashboard/employee',
        };
    }
}

if (!function_exists('sp_session_role')) {
    function sp_session_role(): string
    {
        helper('session_context');

        return sp_normalize_role_value((string) (sp_session_user()['role'] ?? Role::Funcionario->value));
    }
}

if (!function_exists('sp_session_dashboard_url')) {
    function sp_session_dashboard_url(): string
    {
        return sp_dashboard_url_for_role(sp_session_role());
    }
}

if (!function_exists('sp_can_manage_area')) {
    function sp_can_manage_area(?string $role = null): bool
    {
        return in_array(sp_normalize_role_value($role ?? sp_session_role()), [Role::Admin->value, Role::Gestor->value, Role::RH->value], true);
    }
}

if (!function_exists('sp_can_review_pending_punches')) {
    function sp_can_review_pending_punches(?string $role = null): bool
    {
        return sp_can_manage_area($role);
    }
}

if (!function_exists('sp_can_audit_area')) {
    function sp_can_audit_area(?string $role = null): bool
    {
        return in_array(sp_normalize_role_value($role ?? sp_session_role()), [Role::Admin->value, Role::Gestor->value, Role::DPO->value, Role::Auditor->value], true);
    }
}

if (!function_exists('sp_dashboard_shortcuts_for_role')) {
    function sp_dashboard_shortcuts_for_role(?string $role = null): array
    {
        $normalizedRole = sp_normalize_role_value($role ?? sp_session_role());

        $quickActions = [
            Role::Admin->value => [
                ['label' => 'Bater ponto', 'description' => 'Abrir terminal de registro', 'icon' => 'bi bi-clock-history', 'url' => sp_timesheet_punch_url()],
                ['label' => 'Funcionários', 'description' => 'Gerenciar equipe e cadastros', 'icon' => 'bi bi-people-fill', 'url' => site_url('employees')],
                ['label' => 'Relatórios', 'description' => 'Gerar relatórios operacionais', 'icon' => 'bi bi-bar-chart-fill', 'url' => sp_reports_index_url()],
                ['label' => 'Configurações', 'description' => 'Acessar parâmetros do sistema', 'icon' => 'bi bi-sliders', 'url' => sp_settings_center_url()],
            ],
            Role::Gestor->value => [
                ['label' => 'Minha equipe', 'description' => 'Visualizar equipe e pendências', 'icon' => 'bi bi-people-fill', 'url' => site_url('employees')],
                ['label' => 'Justificativas', 'description' => 'Analisar solicitações', 'icon' => 'bi bi-file-earmark-text', 'url' => sp_justifications_index_url()],
                ['label' => 'Relatórios', 'description' => 'Consultar indicadores da equipe', 'icon' => 'bi bi-bar-chart-fill', 'url' => sp_reports_index_url()],
                ['label' => 'Pendências de ponto', 'description' => 'Aprovar ou rejeitar registros pendentes', 'icon' => 'bi bi-clipboard-check-fill', 'url' => site_url('manager/pending-punches')],
            ],
            Role::RH->value => [
                ['label' => 'Colaboradores', 'description' => 'Gerenciar cadastros e movimentações', 'icon' => 'bi bi-people-fill', 'url' => site_url('employees')],
                ['label' => 'Advertências', 'description' => 'Acompanhar ocorrências disciplinares', 'icon' => 'bi bi-exclamation-triangle-fill', 'url' => sp_warning_index_url()],
                ['label' => 'Relatórios', 'description' => 'Consultar indicadores operacionais', 'icon' => 'bi bi-bar-chart-fill', 'url' => sp_reports_index_url()],
                ['label' => 'Pendências de ponto', 'description' => 'Revisar pendências do time', 'icon' => 'bi bi-clipboard-check-fill', 'url' => site_url('manager/pending-punches')],
            ],
            Role::DPO->value => [
                ['label' => 'Auditoria', 'description' => 'Acompanhar trilhas e conformidade', 'icon' => 'bi bi-shield-lock-fill', 'url' => site_url('audit')],
                ['label' => 'Espelho de ponto', 'description' => 'Consultar registros pessoais', 'icon' => 'bi bi-calendar3', 'url' => sp_timesheet_history_url()],
                ['label' => 'Meu perfil', 'description' => 'Atualizar dados e senha', 'icon' => 'bi bi-person-circle', 'url' => sp_profile_url()],
            ],
            Role::Auditor->value => [
                ['label' => 'Auditoria', 'description' => 'Analisar trilhas, eventos e exportações', 'icon' => 'bi bi-shield-check', 'url' => site_url('audit')],
                ['label' => 'Compliance', 'description' => 'Validar matriz de permissões e conformidade', 'icon' => 'bi bi-clipboard2-check-fill', 'url' => site_url('compliance/permissions-matrix')],
                ['label' => 'Relatórios de auditoria', 'description' => 'Consultar relatórios regulatórios', 'icon' => 'bi bi-file-earmark-bar-graph', 'url' => site_url('compliance/relatorios')],
                ['label' => 'Meu perfil', 'description' => 'Atualizar dados e senha', 'icon' => 'bi bi-person-circle', 'url' => sp_profile_url()],
            ],
            Role::Funcionario->value => [
                ['label' => 'Bater ponto', 'description' => 'Registrar entrada, saída e pausas', 'icon' => 'bi bi-clock-fill', 'url' => sp_timesheet_punch_url()],
                ['label' => 'Espelho de ponto', 'description' => 'Consultar registros pessoais', 'icon' => 'bi bi-calendar3', 'url' => sp_timesheet_history_url()],
                ['label' => 'Justificativas', 'description' => 'Enviar ou acompanhar justificativas', 'icon' => 'bi bi-file-earmark-text', 'url' => sp_justifications_index_url()],
                ['label' => 'Meu perfil', 'description' => 'Atualizar dados e senha', 'icon' => 'bi bi-person-circle', 'url' => sp_profile_url()],
            ],
        ];

        return $quickActions[$normalizedRole] ?? $quickActions[Role::Funcionario->value];
    }
}

if (!function_exists('sp_topbar_navigation')) {
    /**
     * @param array{id:?int,name:string,email:?string,role:string,department:mixed,active:bool} $employee
     * @return array{quick_actions: array<int, array<string,string>>, dropdown_links: array<int, array<string,string>>}
     */
    function sp_topbar_navigation(array $employee): array
    {
        $authz = new AuthorizationService();
        $role = sp_normalize_role_value((string) ($employee['role'] ?? Role::Funcionario->value));

        $quickActions = [];
        if ($role !== Role::DPO->value) {
            $quickActions[] = ['label' => 'Bater ponto', 'icon' => 'bi bi-clock-fill', 'url' => sp_timesheet_punch_url()];
        }

        if ($authz->canManageEmployee($employee, $employee) || $authz->can($employee, 'employees.manage') || $authz->can($employee, 'employees.manage.team')) {
            $quickActions[] = ['label' => 'Equipe', 'icon' => 'bi bi-people-fill', 'url' => site_url('employees')];
        }

        if ($authz->can($employee, 'justifications.self') || $authz->can($employee, 'justifications.approve') || $authz->can($employee, 'justifications.approve.team')) {
            $quickActions[] = ['label' => 'Justificativas', 'icon' => 'bi bi-file-earmark-text', 'url' => sp_justifications_index_url()];
        }

        if ($authz->canViewOperationalReports($employee)) {
            $quickActions[] = ['label' => 'Relatórios', 'icon' => 'bi bi-bar-chart-fill', 'url' => sp_reports_index_url()];
        }

        if ($role === Role::Admin->value) {
            $quickActions[] = ['label' => 'Configurações', 'icon' => 'bi bi-sliders', 'url' => sp_settings_center_url()];
        }

        if ($role === Role::DPO->value || $authz->canViewAuditLimited($employee)) {
            $quickActions[] = ['label' => 'Auditoria', 'icon' => 'bi bi-shield-lock-fill', 'url' => site_url('audit')];
        }

        $dropdownLinks = [
            ['label' => 'Meu perfil', 'icon' => 'bi bi-person', 'url' => sp_profile_url()],
        ];

        if ($role === Role::Admin->value) {
            $dropdownLinks[] = ['label' => 'Configurações', 'icon' => 'bi bi-gear', 'url' => sp_settings_center_url()];
        }

        if ($authz->canViewOperationalReports($employee)) {
            $dropdownLinks[] = ['label' => 'Relatórios', 'icon' => 'bi bi-bar-chart', 'url' => sp_reports_index_url()];
        }

        if ($role === Role::DPO->value || $authz->canViewAuditLimited($employee)) {
            $dropdownLinks[] = ['label' => 'Auditoria', 'icon' => 'bi bi-shield-lock', 'url' => site_url('audit')];
        }

        return [
            'quick_actions' => $quickActions,
            'dropdown_links' => $dropdownLinks,
        ];
    }
}
