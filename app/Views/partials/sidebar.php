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
            'icon' => 'bi bi-grid-fill',
            'url' => 'dashboard/admin',
            'match' => ['dashboard', 'admin/dashboard'],
        ],
        ['type' => 'section', 'label' => 'Operação'],
        [
            'label' => 'Ponto e jornada',
            'icon' => 'bi bi-clock-history',
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
            'icon' => 'bi bi-people-fill',
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
            'icon' => 'bi bi-calendar2-week-fill',
            'url' => 'shifts',
            'match' => ['shifts', 'schedules'],
            'submenu' => [
                ['label' => 'Turnos', 'url' => 'shifts'],
                ['label' => 'Escalas', 'url' => 'schedules'],
            ],
        ],
        [
            'label' => 'Limites Virtuais',
            'icon' => 'bi bi-geo-alt-fill',
            'url' => 'geofences',
            'match' => ['geofences', 'geofence'],
        ],
        ['type' => 'section', 'label' => 'Gestão'],
        [
            'label' => 'Relatórios',
            'icon' => 'bi bi-bar-chart-fill',
            'url' => 'reports',
            'match' => ['reports'],
        ],
        [
            'label' => 'Advertências',
            'icon' => 'bi bi-exclamation-triangle-fill',
            'url' => 'warnings',
            'match' => ['warnings'],
        ],
        [
            'label' => 'Auditoria e LGPD',
            'icon' => 'bi bi-shield-lock-fill',
            'url' => 'audit',
            'match' => ['audit', 'compliance', 'lgpd', 'settings/consent-terms', 'biometric/consent-terms'],
            'submenu' => [
                ['label' => 'Auditoria', 'url' => 'audit'],
                ['label' => 'LGPD', 'url' => 'lgpd/consents'],
                ['label' => 'Diagnóstico MTE', 'url' => 'audit/compliance'],
                ['label' => 'Termos e Aceites', 'url' => 'biometric/consent-terms/list'],
            ],
        ],
        ['type' => 'section', 'label' => 'Sistema'],
        [
            'label' => 'Configurações',
            'icon' => 'bi bi-gear-fill',
            'url' => route_to('admin.settings'),
            'match'   => ['settings', 'admin/settings', 'configuracoes', 'admin/health', 'admin/metrics', 'settings/roles', 'settings/consent-terms'],
            'exclude' => ['settings/work-units', 'settings/departments', 'settings/positions'],
            'submenu' => [
                ['label' => 'Visão geral',       'url' => route_to('admin.settings')],
                ['label' => 'Informações',        'url' => route_to('admin.settings.information')],
                ['label' => 'Personalização',     'url' => route_to('admin.settings.personalization')],
                ['label' => 'E-mail',             'url' => route_to('admin.settings.email')],
                ['label' => 'Templates de e-mail','url' => route_to('admin.settings.email-templates')],
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
        ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => 'dashboard/manager', 'match' => ['dashboard', 'dashboard/manager']],
        ['type' => 'section', 'label' => 'Equipe'],
        [
            'label' => 'Operação da equipe',
            'icon' => 'bi bi-people-fill',
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
            'icon' => 'bi bi-clock-fill',
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
            'icon' => 'bi bi-calendar2-week-fill',
            'url' => 'shifts',
            'match' => ['shifts', 'schedules'],
            'submenu' => [
                ['label' => 'Turnos', 'url' => 'shifts'],
                ['label' => 'Escalas', 'url' => 'schedules'],
            ],
        ],
        ['label' => 'Relatórios', 'icon' => 'bi bi-bar-chart-fill', 'url' => 'reports', 'match' => ['reports']],
        ['label' => 'Advertências', 'icon' => 'bi bi-exclamation-triangle-fill', 'url' => 'warnings', 'match' => ['warnings']],
    ],
    'rh' => [
        ['type' => 'section', 'label' => 'Início'],
        ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => 'dashboard/manager', 'match' => ['dashboard', 'dashboard/manager']],
        ['type' => 'section', 'label' => 'Pessoas e operação'],
        [
            'label' => 'Colaboradores',
            'icon' => 'bi bi-people-fill',
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
            'icon' => 'bi bi-clock-fill',
            'url' => 'timesheet/punch',
            'match' => ['timesheet'],
            'submenu' => [
                ['label' => 'Bater ponto', 'url' => 'timesheet/punch'],
                ['label' => 'Espelho', 'url' => 'timesheet/history'],
                ['label' => 'Banco de horas', 'url' => 'timesheet/balance'],
            ],
        ],
        ['label' => 'Relatórios', 'icon' => 'bi bi-bar-chart-fill', 'url' => 'reports', 'match' => ['reports']],
    ],
    'dpo' => [
        ['type' => 'section', 'label' => 'Conformidade'],
        ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => 'dashboard/dpo', 'match' => ['dashboard', 'dashboard/dpo']],
        [
            'label' => 'Auditoria e LGPD',
            'icon' => 'bi bi-shield-lock-fill',
            'url' => 'audit',
            'match' => ['audit', 'compliance', 'lgpd', 'settings/consent-terms', 'biometric/consent-terms'],
            'submenu' => [
                ['label' => 'Auditoria', 'url' => 'audit'],
                ['label' => 'LGPD', 'url' => 'lgpd/consents'],
                ['label' => 'Diagnóstico MTE', 'url' => 'audit/compliance'],
                ['label' => 'Termos e Aceites', 'url' => 'biometric/consent-terms/list'],
            ],
        ],
    ],
    'auditor' => [
        ['type' => 'section', 'label' => 'Auditoria'],
        ['label' => 'Trilha de auditoria', 'icon' => 'bi bi-shield-check', 'url' => 'compliance/audit-advanced', 'match' => ['compliance/audit-advanced', 'compliance']],
        [
            'label' => 'Compliance',
            'icon' => 'bi bi-clipboard2-check-fill',
            'url' => 'compliance/permissions-matrix',
            'match' => ['compliance'],
            'submenu' => [
                ['label' => 'Matriz de permissões', 'url' => 'compliance/permissions-matrix'],
                ['label' => 'Auditoria avançada', 'url' => 'compliance/audit-advanced'],
                ['label' => 'Relatórios regulatórios', 'url' => 'compliance/relatorios'],
            ],
        ],
        ['label' => 'Biometria', 'icon' => 'bi bi-fingerprint', 'url' => 'acesso/biometria/diagnostico', 'match' => ['acesso/biometria', 'admin/biometric']],
        ['label' => 'Minha Biometria', 'icon' => 'bi bi-camera-video', 'url' => 'biometric/enrollment', 'match' => ['biometric/enrollment', 'biometric/'], 'roles' => ['funcionario', 'employee']],
        ['label' => 'Meu perfil', 'icon' => 'bi bi-person-circle', 'url' => 'profile', 'match' => ['profile']],
    ],
    'funcionario' => [
        ['type' => 'section', 'label' => 'Meu espaço'],
        ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => 'dashboard/employee', 'match' => ['dashboard', 'dashboard/employee']],
        ['label' => 'Bater ponto', 'icon' => 'bi bi-clock-fill', 'url' => 'timesheet/punch', 'match' => ['timesheet/punch']],
        ['label' => 'Espelho de ponto', 'icon' => 'bi bi-calendar3', 'url' => 'timesheet/history', 'match' => ['timesheet/history', 'timesheet/employee']],
        ['label' => 'Banco de horas', 'icon' => 'bi bi-hourglass-split', 'url' => 'timesheet/balance', 'match' => ['timesheet/balance']],
        ['label' => 'Minhas escalas', 'icon' => 'bi bi-calendar2-week', 'url' => 'my-schedules', 'match' => ['my-schedules']],
        ['label' => 'Justificativas', 'icon' => 'bi bi-file-earmark-text', 'url' => 'justifications', 'match' => ['justifications']],
        ['label' => 'Meu perfil', 'icon' => 'bi bi-person-circle', 'url' => 'profile', 'match' => ['profile']],
        ['label' => 'Segurança da conta', 'icon' => 'bi bi-shield-lock-fill', 'url' => 'profile/security', 'match' => ['profile/security']],
        ['label' => 'Privacidade (LGPD)', 'icon' => 'bi bi-shield-check', 'url' => 'lgpd/consents', 'match' => ['lgpd']],
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
?>

<aside id="appSidebar" class="app-sidebar">
    <a href="<?= site_url('dashboard') ?>" class="app-brand app-brand--logo-only" aria-label="Ir para o painel">
        <img src="<?= sp_safe_url(support_logo_url('sidebar')) ?>"
             alt="Logo SupportPONTO"
             class="app-brand__logo"
             style="width:100%;height:48px;object-fit:contain;display:block;">
    </a>

    <nav class="app-nav">
        <?php foreach ($items as $item): ?>
            <?php if (($item['type'] ?? 'link') === 'section'): ?>
                <div class="app-nav-title"><?= esc($item['label']) ?></div>
            <?php else: ?>
                <?php $active = $isActive($item, $currentUri); ?>
                <div class="app-nav-group">
                    <a class="app-nav-link <?= $active ? 'is-active' : '' ?>" href="<?= site_url($item['url']) ?>">
                        <span class="app-nav-main">
                            <i class="<?= esc($item['icon']) ?>"></i>
                            <span><?= esc($item['label']) ?></span>
                        </span>
                        <?php if (!empty($item['submenu'])): ?>
                            <i class="bi <?= $active ? 'bi-chevron-up' : 'bi-chevron-down' ?>"></i>
                        <?php endif; ?>
                    </a>

                    <?php if (!empty($item['submenu'])): ?>
                        <div class="app-nav-submenu">
                            <?php foreach ($item['submenu'] as $subItem): ?>
                                <?php if (($subItem['type'] ?? '') === 'separator'): ?>
                                    <div class="app-nav-submenu-sep"><?= esc($subItem['label'] ?? '') ?></div>
                                <?php else: ?>
                                <?php $_subUrl   = trim((string) ($subItem['url'] ?? ''), '/');
                                $subActive = $_subUrl !== '' && (
                                    $currentUri === $_subUrl ||
                                    str_starts_with($currentUri, $_subUrl . '/')
                                ); ?>
                                <a class="app-nav-sublink <?= $subActive ? 'is-active' : '' ?>" href="<?= site_url($subItem['url'] ?: '') ?>">
                                    <?= esc($subItem['label']) ?>
                                </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-footer">
        <div class="app-user-card">
            <div class="user-name"><?= esc($employee['name'] ?? 'Usuário') ?></div>
            <div class="user-role"><?= esc($userRoleLabel) ?></div>
        </div>
    </div>
</aside>
<script>
(function () {
    // Permite expandir/recolher submenus sem navegar ao clicar no item pai
    document.querySelectorAll('.app-nav-group').forEach(function (group) {
        var submenu = group.querySelector('.app-nav-submenu');
        if (!submenu) return; // item sem submenu — comportamento normal

        var link    = group.querySelector('.app-nav-link');
        var chevron = link ? link.querySelector('.bi-chevron-down, .bi-chevron-up') : null;

        // Se já está ativo (servidor definiu), não interferir
        if (link && link.classList.contains('is-active')) return;

        // Intercepta o clique no link pai: expande submenu em vez de navegar
        if (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var isOpen = group.classList.contains('sp-nav-open');
                // Fecha todos os outros abertos
                document.querySelectorAll('.app-nav-group.sp-nav-open').forEach(function (g) {
                    g.classList.remove('sp-nav-open');
                    var ch = g.querySelector('.bi-chevron-up');
                    if (ch) { ch.classList.replace('bi-chevron-up', 'bi-chevron-down'); }
                });
                if (!isOpen) {
                    group.classList.add('sp-nav-open');
                    if (chevron) { chevron.classList.replace('bi-chevron-down', 'bi-chevron-up'); }
                }
            });
        }
    });
})();
</script>
<style>
/* Submenu expandido via JS (páginas que não são a ativa no servidor) */
.app-nav-group.sp-nav-open .app-nav-submenu { display: block !important; }
</style>
