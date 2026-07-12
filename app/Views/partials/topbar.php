<?php
helper(['session_context', 'navigation_context']);

if (!isset($employee) || empty($employee)) {
    $employee = sp_session_user();
}

$userName = $employee['name'] ?? 'Usuário';
$pageTitle = $pageTitle ?? $title ?? 'Painel';
$pageDescription = $pageDescription ?? 'Operação padronizada e rápida para o dia a dia.';
$unreadNotifications = (int) (session()->get('unread_notifications') ?? 0);
$navigation = sp_topbar_navigation($employee);
$quickActions = $navigation['quick_actions'];
$dropdownLinks = $navigation['dropdown_links'];

$breadcrumbLabels = [
    'dashboard' => 'Dashboard',
    'admin' => 'Admin',
    'settings' => 'Configurações',
    'employees' => 'Colaboradores',
    'timesheet' => 'Ponto',
    'punch' => 'Registro',
    'history' => 'Histórico',
    'balance' => 'Banco de horas',
    'reports' => 'Relatórios',
    'justifications' => 'Justificativas',
    'warnings' => 'Advertências',
    'audit' => 'Auditoria',
    'lgpd' => 'LGPD',
    'profile' => 'Perfil',
    'health' => 'Saúde',
    'biometric' => 'Biometria',
    'compliance' => 'Compliance',
];
$segments = array_values(array_filter(explode('/', trim(uri_string(), '/'))));
$breadcrumbs = [['label' => 'Início', 'url' => site_url(sp_session_dashboard_url())]];
$accumulated = '';
foreach ($segments as $segment) {
    if (is_numeric($segment)) {
        continue;
    }
    $accumulated = trim($accumulated . '/' . $segment, '/');
    $breadcrumbs[] = [
        'label' => $breadcrumbLabels[$segment] ?? ucfirst(str_replace(['-', '_'], ' ', $segment)),
        'url' => site_url($accumulated),
    ];
}
?>

<header class="app-topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary" type="button" data-sidebar-toggle>
            <i class="bi bi-list"></i>
        </button>

        <div class="sp-topbar-context">
            <nav aria-label="Caminho da página" class="d-none d-lg-block">
                <ol class="sp-breadcrumbs">
                    <?php foreach ($breadcrumbs as $index => $breadcrumb): ?>
                        <li>
                            <?php if ($index < count($breadcrumbs) - 1): ?>
                                <a href="<?= sp_safe_url($breadcrumb['url']) ?>"><?= esc($breadcrumb['label']) ?></a>
                            <?php else: ?>
                                <span aria-current="page"><?= esc($breadcrumb['label']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <div class="app-topbar-title">
                <h1><?= esc($pageTitle) ?></h1>
                <span><?= esc($pageDescription) ?></span>
            </div>
        </div>
    </div>

    <div class="app-topbar-actions">
        <?php foreach ($quickActions as $action): ?>
            <a class="sp-quick-action d-none d-md-inline-flex" href="<?= sp_safe_url($action['url']) ?>">
                <i class="<?= sp_attr($action['icon']) ?>"></i>
                <span><?= esc($action['label']) ?></span>
            </a>
        <?php endforeach; ?>

        <!-- Botão tema claro/escuro -->
        <button class="btn btn-sm btn-outline-secondary" id="sp-theme-toggle" title="Alternar tema" aria-label="Alternar entre tema claro e escuro">
            <i class="bi bi-sun-fill" id="sp-theme-icon"></i>
        </button>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-bell-fill"></i>
                <?php if ($unreadNotifications > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger"><?= (int) $unreadNotifications ?></span>
                <?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= site_url('operations/notifications-center') ?>"><i class="bi bi-bell me-2"></i>Ver notificações</a></li>
            </ul>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="ms-1 d-none d-md-inline"><?= esc($userName) ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach ($dropdownLinks as $link): ?>
                    <li><a class="dropdown-item" href="<?= sp_safe_url($link['url']) ?>"><i class="<?= sp_attr($link['icon']) ?> me-2"></i><?= esc($link['label']) ?></a></li>
                <?php endforeach; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#spLogoutModal">
                        <i class="bi bi-box-arrow-right me-2"></i>Sair
                    </button>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Modal confirmação de logout -->
<div class="modal fade" id="spLogoutModal" tabindex="-1" aria-labelledby="spLogoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="spLogoutModalLabel">
                    <i class="bi bi-box-arrow-right text-danger me-2"></i>Sair do sistema
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body pt-2">
                <p class="mb-0 text-muted">Tem certeza que deseja encerrar a sessão?</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" action="<?= site_url('auth/logout') ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Confirmar saída
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
