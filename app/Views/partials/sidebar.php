<?php
use App\Enums\Role;

if (!isset($employee) || empty($employee)) {
    $employee = sp_session_user();
}

$currentUri = trim(uri_string(), '/');
$roleRaw = (string) ($employee['role'] ?? 'funcionario');

try {
    $normalizedRole = Role::normalize($roleRaw);
} catch (\ValueError) {
    $normalizedRole = Role::Funcionario;
}

$userRole = $normalizedRole->value;
$userRoleLabel = $normalizedRole->label();

$menuStructure = [
    'admin' => [
        ['type' => 'section', 'label' => 'Início'],
        [
            'label' => 'Dashboard',
            'icon' => 'dashboard',
            'url' => 'dashboard/admin',
            'match' => ['dashboard', 'admin/dashboard'],
        ],
        ['type' => 'section', 'label' => 'Operação'],
        [
            'label' => 'Ponto e jornada',
            'icon' => 'clock',
            'url' => 'timesheet/punch',
            'match' => ['timesheet', 'punch', 'justifications', 'manager/pending-punches'],
            'submenu' => [
                ['label' => 'Bater ponto',        'url' => 'timesheet/punch'],
                ['label' => 'Espelho de ponto',   'url' => 'timesheet/history'],
                ['label' => 'Banco de horas',     'url' => 'timesheet/balance'],
                ['label' => 'Justificativas',     'url' => 'justifications'],
                ['label' => 'Pendências de ponto','url' => 'manager/pending-punches'],
            ],
        ],
        ['type' => 'section', 'label' => 'Cadastros e pessoas'],
        [
            'label' => 'Colaboradores',
            'icon' => 'users',
            'url' => 'employees',
            // match inclui as rotas de cadastros auxiliares para o menu permanecer ativo
            'match' => [
                'employees',
                'settings/work-units',
                'settings/departments',
                'settings/positions',
            ],
            'submenu' => [
                ['label' => 'Listagem',       'url' => 'employees'],
                ['label' => 'Pendências',      'url' => 'employees/pending'],
                ['label' => 'Unidades',        'url' => route_to('settings.work-units')],
                ['label' => 'Departamentos',   'url' => route_to('settings.departments')],
                ['label' => 'Cargos',          'url' => route_to('settings.positions')],
            ],
        ],
        [
            'label' => 'Turno e Escala',
            'icon' => 'calendar',
            'url' => 'shifts',
            'match' => ['shifts', 'schedules'],
            'submenu' => [
                ['label' => 'Turnos', 'url' => 'shifts'],
                ['label' => 'Escalas', 'url' => 'schedules'],
            ],
        ],
        [
            'label' => 'Limites Virtuais',
            'icon' => 'map-pin',
            'url' => 'geofences',
            'match' => ['geofences', 'geofence'],
        ],
        ['type' => 'section', 'label' => 'Gestão'],
        [
            'label' => 'Relatórios',
            'icon' => 'bar-chart',
            'url' => 'reports',
            'match' => ['reports'],
        ],
        [
            'label' => 'Advertências',
            'icon' => 'alert',
            'url' => 'warnings',
            'match' => ['warnings'],
        ],
        [
            'label' => 'Auditoria e LGPD',
            'icon' => 'lock',
            'url' => 'audit',
            'match' => ['audit', 'compliance', 'lgpd', 'settings/consent-terms', 'biometric/consent-terms', 'admin/facial-fraud-alerts'],
            'submenu' => [
                ['label' => 'Auditoria', 'url' => 'audit'],
                ['label' => 'LGPD', 'url' => 'lgpd/consents'],
                ['label' => 'Diagnóstico MTE', 'url' => 'audit/compliance'],
                ['label' => 'Termos e Aceites', 'url' => 'biometric/consent-terms/list'],
                ['label' => 'Alertas de Fraude Facial', 'url' => 'admin/facial-fraud-alerts'],
            ],
        ],
        ['type' => 'section', 'label' => 'Sistema'],
        [
            'label' => 'Configurações',
            'icon' => 'settings',
            'url' => route_to('admin.settings.information'),
            'match'   => ['settings', 'admin/settings', 'configuracoes', 'admin/health', 'admin/metrics', 'settings/roles', 'settings/consent-terms'],
            'exclude' => ['settings/work-units', 'settings/departments', 'settings/positions'],
            'submenu' => [
                ['label' => 'Informações',        'url' => route_to('admin.settings.information')],
                ['label' => 'Personalização',     'url' => route_to('admin.settings.personalization')],
                ['label' => 'E-mail',             'url' => route_to('admin.settings.email')],
                ['label' => 'Templates de Termos', 'url' => 'settings/consent-terms'],
                ['label' => 'Feriados',           'url' => route_to('settings.holidays')],
                ['label' => 'Integrações',        'url' => route_to('admin.settings.integrations')],
                ['label' => 'Backup',             'url' => route_to('admin.settings.backup')],
                ['label' => 'PWA',                'url' => route_to('admin.settings.pwa')],
                ['label' => 'Autenticação',       'url' => route_to('admin.settings.authentication')],
                ['label' => '2FA',                'url' => route_to('admin.settings.two-factor')],
                ['label' => 'Segurança',          'url' => route_to('admin.settings.security')],
                ['label' => 'Certificado',        'url' => route_to('admin.settings.certificate')],
                ['label' => 'Níveis de acesso',   'url' => route_to('settings.roles')],
                ['label' => 'Saúde do sistema',   'url' => route_to('admin.health')],
                ['label' => 'Métricas',            'url' => route_to('admin.metrics')],
                ['label' => 'Diagnóstico',        'url' => route_to('admin.health.diagnostics')],
            ],
        ],
    ],
    'gestor' => [
        ['type' => 'section', 'label' => 'Início'],
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => 'dashboard/manager', 'match' => ['dashboard', 'dashboard/manager']],
        ['type' => 'section', 'label' => 'Equipe'],
        [
            'label' => 'Operação da equipe',
            'icon' => 'users',
            'url' => 'employees',
            'match' => ['employees', 'justifications', 'manager/pending-punches'],
            'submenu' => [
                ['label' => 'Equipe', 'url' => 'employees'],
                ['label' => 'Justificativas', 'url' => 'justifications'],
                ['label' => 'Pendências de ponto', 'url' => 'manager/pending-punches'],
            ],
        ],
        [
            'label' => 'Ponto',
            'icon' => 'clock',
            'url' => 'timesheet/punch',
            'match' => ['timesheet'],
            'submenu' => [
                ['label' => 'Bater ponto', 'url' => 'timesheet/punch'],
                ['label' => 'Espelho', 'url' => 'timesheet/history'],
                ['label' => 'Banco de horas', 'url' => 'timesheet/balance'],
            ],
        ],
        [
            'label' => 'Turno e Escala',
            'icon' => 'calendar',
            'url' => 'shifts',
            'match' => ['shifts', 'schedules'],
            'submenu' => [
                ['label' => 'Turnos', 'url' => 'shifts'],
                ['label' => 'Escalas', 'url' => 'schedules'],
            ],
        ],
        ['label' => 'Relatórios', 'icon' => 'bar-chart', 'url' => 'reports', 'match' => ['reports']],
        ['label' => 'Advertências', 'icon' => 'alert', 'url' => 'warnings', 'match' => ['warnings']],
        ['label' => 'Alertas de Fraude Facial', 'icon' => 'shield-alert', 'url' => 'admin/facial-fraud-alerts', 'match' => ['admin/facial-fraud-alerts']],
    ],
    'rh' => [
        ['type' => 'section', 'label' => 'Início'],
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => 'dashboard/manager', 'match' => ['dashboard', 'dashboard/manager']],
        ['type' => 'section', 'label' => 'Pessoas e operação'],
        [
            'label' => 'Colaboradores',
            'icon' => 'users',
            'url' => 'employees',
            'match' => ['employees', 'manager/pending-punches'],
            'submenu' => [
                ['label' => 'Colaboradores', 'url' => 'employees'],
                ['label' => 'Justificativas', 'url' => 'justifications'],
                ['label' => 'Pendências de ponto', 'url' => 'manager/pending-punches'],
                ['label' => 'Advertências', 'url' => 'warnings'],
            ],
        ],
        [
            'label' => 'Ponto',
            'icon' => 'clock',
            'url' => 'timesheet/punch',
            'match' => ['timesheet'],
            'submenu' => [
                ['label' => 'Bater ponto', 'url' => 'timesheet/punch'],
                ['label' => 'Espelho', 'url' => 'timesheet/history'],
                ['label' => 'Banco de horas', 'url' => 'timesheet/balance'],
            ],
        ],
        ['label' => 'Relatórios', 'icon' => 'bar-chart', 'url' => 'reports', 'match' => ['reports']],
        ['label' => 'Alertas de Fraude Facial', 'icon' => 'shield-alert', 'url' => 'admin/facial-fraud-alerts', 'match' => ['admin/facial-fraud-alerts']],
    ],
    'dpo' => [
        ['type' => 'section', 'label' => 'Conformidade'],
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => 'dashboard/dpo', 'match' => ['dashboard', 'dashboard/dpo']],
        [
            'label' => 'Auditoria e LGPD',
            'icon' => 'lock',
            'url' => 'audit',
            'match' => ['audit', 'compliance', 'lgpd', 'settings/consent-terms', 'biometric/consent-terms', 'admin/facial-fraud-alerts'],
            'submenu' => [
                ['label' => 'Auditoria', 'url' => 'audit'],
                ['label' => 'LGPD', 'url' => 'lgpd/consents'],
                ['label' => 'Diagnóstico MTE', 'url' => 'audit/compliance'],
                ['label' => 'Termos e Aceites', 'url' => 'biometric/consent-terms/list'],
                ['label' => 'Alertas de Fraude Facial', 'url' => 'admin/facial-fraud-alerts'],
            ],
        ],
    ],
    'auditor' => [
        ['type' => 'section', 'label' => 'Auditoria'],
        ['label' => 'Trilha de auditoria', 'icon' => 'search', 'url' => 'compliance/audit-advanced', 'match' => ['compliance/audit-advanced', 'compliance']],
        [
            'label' => 'Compliance',
            'icon' => 'clipboard-check',
            'url' => 'compliance/permissions-matrix',
            'match' => ['compliance'],
            'submenu' => [
                ['label' => 'Matriz de permissões', 'url' => 'compliance/permissions-matrix'],
                ['label' => 'Auditoria avançada', 'url' => 'compliance/audit-advanced'],
                ['label' => 'Relatórios regulatórios', 'url' => 'compliance/relatorios'],
            ],
        ],
        ['label' => 'Biometria', 'icon' => 'camera', 'url' => 'acesso/biometria/diagnostico', 'match' => ['acesso/biometria', 'admin/biometric']],
        ['label' => 'Minha Biometria', 'icon' => 'camera', 'url' => 'biometric/enrollment', 'match' => ['biometric/enrollment', 'biometric/'], 'roles' => ['funcionario', 'employee']],
        ['label' => 'Meu perfil', 'icon' => 'user', 'url' => 'profile', 'match' => ['profile']],
    ],
    'funcionario' => [
        ['type' => 'section', 'label' => 'Meu espaço'],
        ['label' => 'Dashboard', 'icon' => 'dashboard', 'url' => 'dashboard/employee', 'match' => ['dashboard', 'dashboard/employee']],
        ['label' => 'Bater ponto', 'icon' => 'clock', 'url' => 'timesheet/punch', 'match' => ['timesheet/punch']],
        ['label' => 'Espelho de ponto', 'icon' => 'calendar', 'url' => 'timesheet/history', 'match' => ['timesheet/history', 'timesheet/employee']],
        ['label' => 'Banco de horas', 'icon' => 'clock', 'url' => 'timesheet/balance', 'match' => ['timesheet/balance']],
        ['label' => 'Minhas escalas', 'icon' => 'calendar', 'url' => 'my-schedules', 'match' => ['my-schedules']],
        ['label' => 'Justificativas', 'icon' => 'file-text', 'url' => 'justifications', 'match' => ['justifications']],
        ['label' => 'Meu perfil', 'icon' => 'user', 'url' => 'profile', 'match' => ['profile']],
        ['label' => 'Segurança da conta', 'icon' => 'lock', 'url' => 'profile/security', 'match' => ['profile/security']],
        ['label' => 'Privacidade (LGPD)', 'icon' => 'shield-check', 'url' => 'lgpd/consents', 'match' => ['lgpd']],
    ],
];

$canAccessUrl = static function (string $url, string $role, array $employee): bool {
    $normalized = trim($url, '/');

    if ($normalized === '' || $normalized === 'dashboard' || str_starts_with($normalized, 'profile') || str_starts_with($normalized, 'notifications') || str_starts_with($normalized, 'timesheet') || $normalized === 'justifications' || str_starts_with($normalized, 'lgpd') || str_starts_with($normalized, 'my-schedules')) {
        return true;
    }


    if (str_starts_with($normalized, 'manager/pending-punches')) {
        return in_array($role, ['admin', 'gestor', 'rh'], true);
    }

    if (str_starts_with($normalized, 'dashboard/admin') || str_starts_with($normalized, 'settings') || str_starts_with($normalized, 'admin/settings')) {
        return $role === 'admin';
    }

    if (str_starts_with($normalized, 'dashboard/dpo')) {
        return $role === 'dpo';
    }

    if (str_starts_with($normalized, 'dashboard/manager') || str_starts_with($normalized, 'employees') || str_starts_with($normalized, 'warnings') || str_starts_with($normalized, 'shifts') || str_starts_with($normalized, 'schedules')) {
        return in_array($role, ['admin', 'gestor', 'rh'], true);
    }

    if (str_starts_with($normalized, 'reports')) {
        return in_array($role, ['admin', 'gestor', 'rh'], true);
    }

    if (str_starts_with($normalized, 'compliance')) {
        return in_array($role, ['admin', 'dpo', 'auditor'], true);
    }

    if (str_starts_with($normalized, 'audit')) {
        return in_array($role, ['admin', 'gestor', 'dpo', 'auditor'], true);
    }

    return true;
};

$items = $menuStructure[$userRole] ?? $menuStructure['funcionario'];
$items = array_values(array_filter(array_map(static function (array $item) use ($canAccessUrl, $userRole, $employee): ?array {
    if (($item['type'] ?? 'link') === 'section') {
        return $item;
    }

    if (!empty($item['submenu']) && is_array($item['submenu'])) {
        $item['submenu'] = array_values(array_filter($item['submenu'], static fn (array $subItem): bool => ($subItem['type'] ?? '') === 'separator' || $canAccessUrl((string) ($subItem['url'] ?? ''), $userRole, $employee)));
    }

    return $canAccessUrl((string) ($item['url'] ?? ''), $userRole, $employee) ? $item : null;
}, $items)));

$isActive = static function (array $item, string $currentUri): bool {
    foreach (($item['exclude'] ?? []) as $excl) {
        $e = trim((string) $excl, '/');
        if ($e !== '' && ($currentUri === $e || str_starts_with($currentUri, $e . '/'))) {
            return false;
        }
    }
    foreach (($item['match'] ?? [$item['url'] ?? '']) as $match) {
        $normalized = trim((string) $match, '/');
        if ($normalized !== '' && (
            $currentUri === $normalized ||
            str_starts_with($currentUri, $normalized . '/')
        )) {
            return true;
        }
    }
    return false;
};

// Ícones inline (mesmo padrão visual do SupportCHECK: outline/stroke, 24x24 viewBox, 17px)
$icon = static function (string $name): string {
    $a = 'xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="app-nav-icon"';
    $p = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'bar-chart' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'shield-check' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/>',
        'shield-alert' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'clipboard-check' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'camera' => '<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'chevron-left' => '<polyline points="15 18 9 12 15 6"/>',
    ];
    return '<svg ' . $a . '>' . ($p[$name] ?? '') . '</svg>';
};

// Seta dos grupos expansíveis (details/summary)
$chevronSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="app-nav-chevron"><polyline points="6 9 12 15 18 9"/></svg>';
?>

<aside id="appSidebar" class="app-sidebar">

    <?php $sidebarLogoUrl = sp_safe_url(support_logo_url('sidebar')); ?>
    <a class="app-sidebar__logo-wrap" href="<?= site_url('dashboard') ?>" aria-label="Ir para o painel">
        <div class="app-sidebar__logo-full">
            <img src="<?= $sidebarLogoUrl ?>" alt="SupportPONTO" class="app-sidebar__logo-img">
        </div>
        <div class="app-sidebar__logo-icon">
            <img src="<?= $sidebarLogoUrl ?>" alt="SupportPONTO" class="app-sidebar__logo-icon-img">
        </div>
    </a>

    <button class="app-sidebar__collapse-btn" id="appSidebarCollapseTop" type="button" aria-label="Recolher menu">
        <?= $icon('chevron-left') ?>
    </button>

    <div class="app-sidebar__section">
        <nav class="app-nav">
            <?php foreach ($items as $item): ?>
                <?php if (($item['type'] ?? 'link') === 'section'): ?>
                    <div class="app-nav-title app-nav-label"><?= esc($item['label']) ?></div>
                <?php else: ?>
                    <?php $active = $isActive($item, $currentUri); ?>
                    <?php if (!empty($item['submenu'])): ?>
                        <details class="app-nav-group" <?= $active ? 'open' : '' ?>>
                            <summary class="app-nav-link app-nav-group-toggle">
                                <?= $icon($item['icon']) ?>
                                <span class="app-nav-label"><?= esc($item['label']) ?></span>
                                <?= $chevronSvg ?>
                            </summary>
                            <div class="app-nav-sublinks">
                                <?php foreach ($item['submenu'] as $subItem): ?>
                                    <?php if (($subItem['type'] ?? '') === 'separator'): ?>
                                        <div class="app-nav-submenu-sep"><?= esc($subItem['label'] ?? '') ?></div>
                                    <?php else: ?>
                                        <?php
                                        $_subUrl = trim((string) ($subItem['url'] ?? ''), '/');
                                        $subActive = $_subUrl !== '' && (
                                            $currentUri === $_subUrl ||
                                            str_starts_with($currentUri, $_subUrl . '/')
                                        );
                                        ?>
                                        <a class="app-nav-sublink <?= $subActive ? 'is-active' : '' ?>" href="<?= site_url($subItem['url'] ?: '') ?>"
                                           <?= $subActive ? 'aria-current="page"' : '' ?>>
                                            <span class="app-nav-label"><?= esc($subItem['label']) ?></span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php else: ?>
                        <a class="app-nav-link <?= $active ? 'is-active' : '' ?>" href="<?= site_url($item['url']) ?>"
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <?= $icon($item['icon']) ?>
                            <span class="app-nav-label"><?= esc($item['label']) ?></span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="app-sidebar__bottom">
        <button class="app-nav-link app-sidebar__collapse-bottom" id="appSidebarCollapseBottom" type="button" title="Recolher menu">
            <?= $icon('chevron-left') ?>
            <span class="app-nav-label">Recolher menu</span>
        </button>
        <div class="app-user-card">
            <div class="user-name"><?= esc($employee['name'] ?? 'Usuário') ?></div>
            <div class="user-role app-nav-label"><?= esc($userRoleLabel) ?></div>
        </div>
    </div>
</aside>
