<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc(lang('DashboardAdmin.browserTitle')) ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/pages/dashboard.css')) ?>">
<style>
.sp-section-label{font-size:.7rem;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--sp-text-muted,#888);margin-bottom:.5rem;padding-left:.1rem}
.sp-kpi-card{border:1px solid var(--sp-border,rgba(0,0,0,.08))!important;transition:box-shadow .15s}
.sp-kpi-card:hover{box-shadow:0 2px 10px rgba(0,0,0,.08)!important}
.sp-kpi-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.15rem}
.sp-kpi-value{font-size:1.7rem;font-weight:600;line-height:1.1;color:var(--sp-text-primary,#1a1a1a)}
.sp-kpi-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--sp-text-muted,#888);margin-top:.15rem;font-weight:500}
.sp-kpi-desc{font-size:.7rem;color:var(--sp-text-muted,#999);margin-top:.2rem}
.sp-kpi-sec-card{border:1px solid var(--sp-border,rgba(0,0,0,.08))!important}
.sp-kpi-sec-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.95rem}
.sp-kpi-sec-value{font-size:1.3rem;font-weight:600;line-height:1.1;color:var(--sp-text-primary,#1a1a1a)}
#health-band .card{border-left-width:3px!important}
#health-band .card-body{padding:.75rem 1.1rem!important}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('components/observability/dashboard_refresh_runtime') ?>

<?php
$dashboardPresentation        = is_array($dashboardPresentation ?? null)                          ? $dashboardPresentation                          : [];
$pageHeader                   = is_array($dashboardPresentation['pageHeader'] ?? null)            ? $dashboardPresentation['pageHeader']            : [];
$primaryStats                 = is_array($dashboardPresentation['primaryStats'] ?? null)          ? $dashboardPresentation['primaryStats']          : [];
$secondaryStats               = is_array($dashboardPresentation['secondaryStats'] ?? null)        ? $dashboardPresentation['secondaryStats']        : [];
$hubPresentation              = is_array($dashboardPresentation['hub'] ?? null)                   ? $dashboardPresentation['hub']                   : [];
$adminActionCards             = is_array($hubPresentation['cards'] ?? null)                       ? $hubPresentation['cards']                       : [];
$navigationGuideItems         = is_array(($dashboardPresentation['navigationGuide'] ?? [])['items'] ?? null) ? $dashboardPresentation['navigationGuide']['items'] : [];
$pendingJustificationsView    = is_array($dashboardPresentation['pendingJustifications'] ?? null) ? $dashboardPresentation['pendingJustifications'] : [];
$pendingJustificationRows     = is_array($pendingJustificationsView['rows'] ?? null)              ? $pendingJustificationsView['rows']              : [];
$pendingJustificationCount    = (int) ($pendingJustificationsView['count'] ?? 0);
$pendingJustificationFooter   = is_array($pendingJustificationsView['footer'] ?? null)            ? $pendingJustificationsView['footer']            : [];
$recentActivitiesView         = is_array($dashboardPresentation['recentActivities'] ?? null)      ? $dashboardPresentation['recentActivities']      : [];
$recentActivityItems          = is_array($recentActivitiesView['items'] ?? null)                  ? $recentActivitiesView['items']                  : [];
$recentActivityCount          = (int) ($recentActivitiesView['count'] ?? 0);
$recentActivityFooter         = is_array($recentActivitiesView['footer'] ?? null)                 ? $recentActivitiesView['footer']                 : [];
$systemAlertsView             = is_array($dashboardPresentation['systemAlerts'] ?? null)          ? $dashboardPresentation['systemAlerts']          : [];
$systemAlertItems             = is_array($systemAlertsView['items'] ?? null)                      ? $systemAlertsView['items']                      : [];
$systemAlertCount             = (int) ($systemAlertsView['count'] ?? 0);
$systemAlertFooter            = is_array($systemAlertsView['footer'] ?? null)                     ? $systemAlertsView['footer']                     : [];
$missingText                  = lang('DashboardAdmin.common.missingText');
$missingInitial               = lang('DashboardAdmin.common.missingInitial');
$defaultPendingStatusLabel    = lang('DashboardAdmin.common.pending');

// BAIXO-05 (auditoria): estatísticas de método de ponto, resumo de justificativas e
// pontos pendentes agora são calculados em DashboardAdminService (query builder
// parametrizado) e chegam prontos como $_methodLabels, $_statsJson, $_justSummary,
// $_situationLabels, $_punchTypeLabels, $_pendingPunches, $_pendingPunchCount — antes
// viviam como SQL bruto interpolado direto nesta view.
?>

<?= view('components/page_header', $pageHeader) ?>

<!-- ══ Barra de saúde operacional ══════════════════════════════════════════ -->
<?php
try {
    $_health = (new \App\Services\Health\SystemHealthCheckService())->detailedHealth();
} catch (\Throwable $_he) {
    $_health = ['status' => 'error', 'summary' => [], 'modules' => [], 'alerts' => []];
}
$_hStatus  = $_health['status']  ?? 'error';
$_hSummary = $_health['summary'] ?? [];
$_hModules = $_health['modules'] ?? [];
$_hAlerts  = $_health['alerts']  ?? [];
$_hOk      = (int)($_hSummary['ok_count']      ?? 0);
$_hWarn    = (int)($_hSummary['warning_count']  ?? 0);
$_hErr     = (int)($_hSummary['error_count']    ?? 0);

$_statusMeta = [
    'ok'      => ['color' => '#198754', 'bg' => 'rgba(25,135,84,.1)',  'icon' => 'bi-check-circle-fill',        'label' => 'OK'],
    'warning' => ['color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.1)', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'Atenção'],
    'error'   => ['color' => '#dc3545', 'bg' => 'rgba(220,53,69,.1)',  'icon' => 'bi-x-circle-fill',            'label' => 'Erro'],
];
$_overallMeta = $_statusMeta[$_hStatus] ?? $_statusMeta['error'];

$_moduleLabels = [
    'database'      => ['icon' => 'bi-database-fill',      'label' => 'Banco de Dados'],
    'queue'         => ['icon' => 'bi-stack',               'label' => 'Fila de Jobs'],
    'deepface'      => ['icon' => 'bi-person-bounding-box', 'label' => 'DeepFace'],
    'storage'       => ['icon' => 'bi-hdd-fill',            'label' => 'Storage'],
    'writable'      => ['icon' => 'bi-folder-fill',         'label' => 'Writable'],
    'migrations'    => ['icon' => 'bi-arrow-repeat',        'label' => 'Migrations'],
    'websocket'     => ['icon' => 'bi-broadcast',           'label' => 'WebSocket'],
    'logs'          => ['icon' => 'bi-journal-text',        'label' => 'Logs'],
    'env'           => ['icon' => 'bi-gear-fill',           'label' => 'Ambiente'],
];
?>
<div class="sp-section-label">Saúde do sistema</div>
<div class="mb-3" id="health-band">
    <div class="card border-0 shadow-sm overflow-hidden" style="border-left:4px solid <?= $_overallMeta['color'] ?> !important">
        <div class="card-body py-3 px-4">
            <div class="d-flex flex-wrap align-items-center gap-3">

                <div class="d-flex align-items-center gap-2 me-2" style="min-width:140px">
                    <span style="width:2.2rem;height:2.2rem;background:<?= $_overallMeta['bg'] ?>;border-radius:.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi <?= $_overallMeta['icon'] ?>" style="color:<?= $_overallMeta['color'] ?>;font-size:1rem"></i>
                    </span>
                    <div>
                        <div class="fw-bold" style="font-size:.85rem;color:<?= $_overallMeta['color'] ?>">Sistema <?= esc(strtoupper($_hStatus)) ?></div>
                        <div class="text-muted" style="font-size:.72rem"><?= $_hOk ?> ok &middot; <?= $_hWarn ?> alerta(s) &middot; <?= $_hErr ?> erro(s)</div>
                    </div>
                </div>

                <div class="vr d-none d-md-block" style="height:2rem;opacity:.2"></div>

                <div class="d-flex flex-wrap gap-2 flex-grow-1">
                    <?php foreach ($_moduleLabels as $_mk => $_ml): ?>
                    <?php if (!isset($_hModules[$_mk])) continue; ?>
                    <?php
                    $_ms    = (string)($_hModules[$_mk]['status'] ?? 'error');
                    $_mmeta = $_statusMeta[$_ms] ?? $_statusMeta['error'];
                    $detail = '';
                    if (!empty($_hModules[$_mk]['details']['response_ms'])) $detail = ' · '.$_hModules[$_mk]['details']['response_ms'].'ms';
                    elseif (!empty($_hModules[$_mk]['details']['free_mb']))  $detail = ' · '.$_hModules[$_mk]['details']['free_mb'].'MB livre';
                    ?>
                    <div class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill"
                         style="background:<?= $_mmeta['bg'] ?>;border:1px solid <?= $_mmeta['color'] ?>30;font-size:.73rem;white-space:nowrap"
                         title="<?= esc($_ml['label']) ?>: <?= esc(strtoupper($_ms)) ?><?= esc($detail) ?>">
                        <i class="bi <?= $_ml['icon'] ?>" style="color:<?= $_mmeta['color'] ?>;font-size:.8rem"></i>
                        <span style="font-weight:600"><?= esc($_ml['label']) ?></span>
                        <span class="ms-1 fw-bold" style="color:<?= $_mmeta['color'] ?>"><?= esc(strtoupper($_ms)) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <a href="<?= site_url('admin/health') ?>" class="btn btn-sm btn-outline-secondary flex-shrink-0 ms-auto">
                    <i class="bi bi-activity me-1"></i>Detalhes
                </a>

            </div>
            <?php if (!empty($_hAlerts)): ?>
            <div class="mt-3 pt-2 border-top d-flex flex-column gap-1">
                <?php foreach (array_slice($_hAlerts, 0, 3) as $_alert): ?>
                <?php $_am = $_statusMeta[$_alert['status'] ?? 'warning'] ?? $_statusMeta['warning']; ?>
                <div class="d-flex align-items-start gap-2" style="font-size:.78rem">
                    <i class="bi <?= $_am['icon'] ?>" style="color:<?= $_am['color'] ?>;margin-top:.1rem;flex-shrink:0"></i>
                    <span><?= esc($_alert['message'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (count($_hAlerts) > 3): ?>
                <div class="mt-1" style="font-size:.75rem;color:var(--sp-text-muted)">
                    + <?= count($_hAlerts) - 3 ?> alerta(s) adicional(is) &mdash; <a href="<?= site_url('admin/health') ?>">ver todos</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- ════════════════════════════════════════════════════════════════════════ -->



<!-- 1. KPIs principais -->
<div class="sp-section-label">Força de trabalho</div>
<div class="row g-3 mb-2" id="main-admin-content">
<?php
$_vmap = [
    'primary'   => ['bg'=>'#e6f1fb','color'=>'#185fa5'],
    'success'   => ['bg'=>'#eaf3de','color'=>'#3b6d11'],
    'info'      => ['bg'=>'#e1f5ee','color'=>'#0f6e56'],
    'warning'   => ['bg'=>'#faeeda','color'=>'#854f0b'],
    'danger'    => ['bg'=>'#fcebeb','color'=>'#a32d2d'],
    'secondary' => ['bg'=>'#f1efe8','color'=>'#5f5e5a'],
    'dark'      => ['bg'=>'#eeedfe','color'=>'#534ab7'],
];
foreach ($primaryStats as $_st):
    $_vc = $_vmap[$_st['variant'] ?? 'primary'] ?? $_vmap['primary'];
?>
    <div class="col-6 col-md-3">
        <div class="card border-0 h-100 sp-kpi-card">
            <div class="card-body d-flex align-items-center gap-3 py-3 px-3">
                <span class="sp-kpi-icon flex-shrink-0" style="background:<?= $_vc['bg'] ?>;color:<?= $_vc['color'] ?>">
                    <i class="<?= esc($_st['icon'] ?? 'bi bi-graph-up') ?>"></i>
                </span>
                <div class="min-w-0 overflow-hidden">
                    <div class="sp-kpi-value"><?= esc($_st['value'] ?? '0') ?></div>
                    <div class="sp-kpi-label"><?= esc($_st['label'] ?? '') ?></div>
                    <?php if (!empty($_st['description'])): ?>
                    <div class="sp-kpi-desc text-truncate"><?= esc($_st['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
<?php foreach ($secondaryStats as $_st):
    $_vc = $_vmap[$_st['variant'] ?? 'secondary'] ?? $_vmap['secondary'];
?>
    <div class="col-4">
        <div class="card border-0 sp-kpi-sec-card">
            <div class="card-body d-flex align-items-center gap-3 py-2 px-3">
                <span class="sp-kpi-sec-icon flex-shrink-0" style="background:<?= $_vc['bg'] ?>;color:<?= $_vc['color'] ?>">
                    <i class="<?= esc($_st['icon'] ?? 'bi bi-graph-up') ?>"></i>
                </span>
                <div class="min-w-0 overflow-hidden">
                    <div class="sp-kpi-sec-value"><?= esc($_st['value'] ?? '0') ?></div>
                    <div class="sp-kpi-label"><?= esc($_st['label'] ?? '') ?></div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="sp-section-label">Operação</div>
<!-- 2. Pendencias + Alertas -->
<div class="row g-4 mb-4">

    <div class="col-12 col-xl-8" id="pending-justifications-section">
        <section class="card h-100" aria-labelledby="admin-pending-h">
            <div class="db-card-header">
                <h2 class="db-card-title" id="admin-pending-h" tabindex="-1" data-section-heading="pending-justifications-section">
                    <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                    <?= esc(lang('DashboardAdmin.sections.pending.title')) ?>
                </h2>
                <?php if ($pendingJustificationCount > 0): ?>
                    <span class="badge rounded-pill text-bg-warning">
                        <?= esc(lang('DashboardAdmin.sections.pending.badge', [$pendingJustificationCount])) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if ($pendingJustificationRows !== []): ?>
                    <div class="table-responsive">
                        <table class="table table-enhanced mb-0">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('DashboardAdmin.sections.pending.columns.employee')) ?></th>
                                    <th scope="col"><?= esc(lang('DashboardAdmin.sections.pending.columns.date')) ?></th>
                                    <th scope="col"><?= esc(lang('DashboardAdmin.sections.pending.columns.type')) ?></th>
                                    <th scope="col"><?= esc(lang('DashboardAdmin.sections.pending.columns.status')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingJustificationRows as $j): ?>
                                    <tr>
                                        <td>
                                            <div class="table-cell-avatar">
                                                <div class="table-avatar" aria-hidden="true"><?= esc($j['employeeInitial'] ?? $missingInitial) ?></div>
                                                <span><?= esc($j['employeeName'] ?? $missingText) ?></span>
                                            </div>
                                        </td>
                                        <td><?= esc($j['createdAtLabel'] ?? $missingText) ?></td>
                                        <td><span class="table-status info"><?= esc($j['typeLabel'] ?? $missingText) ?></span></td>
                                        <td><span class="table-status warning"><?= esc($j['statusLabel'] ?? $defaultPendingStatusLabel) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3" data-flow-external="true"
                         data-flow-label="<?= sp_attr((string) ($pendingJustificationFooter['flowLabel'] ?? '')) ?>"
                         data-flow-context="<?= sp_attr((string) ($pendingJustificationFooter['flowContext'] ?? '')) ?>"
                         data-flow-return-label="<?= sp_attr((string) ($pendingJustificationFooter['returnLabel'] ?? '')) ?>">
                        <?= view('components/admin/section_action_footer', $pendingJustificationFooter) ?>
                    </div>
                <?php else: ?>
                    <div class="p-4">
                        <?= view('components/empty_state', [
                            'icon'             => 'bi bi-inbox',
                            'title'            => lang('DashboardAdmin.sections.pending.emptyTitle'),
                            'description'      => lang('DashboardAdmin.sections.pending.emptyDescription'),
                            'wrapperClass'     => 'table-empty',
                            'iconWrapperClass' => 'table-empty-icon',
                            'titleClass'       => 'table-empty-title',
                            'descriptionClass' => 'table-empty-description',
                            'iconClass'        => '',
                        ]) ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <div class="col-12 col-xl-4" id="system-alerts-section">
        <section class="card h-100" aria-labelledby="admin-alerts-h">
            <div class="db-card-header">
                <h2 class="db-card-title" id="admin-alerts-h" tabindex="-1" data-section-heading="system-alerts-section">
                    <i class="bi bi-exclamation-triangle warn" aria-hidden="true"></i>
                    <?= esc(lang('DashboardAdmin.sections.alerts.title')) ?>
                </h2>
                <?php if ($systemAlertCount > 0): ?>
                    <span class="badge rounded-pill text-bg-danger"><?= esc($systemAlertCount) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($systemAlertItems !== []): ?>
                    <?= view('components/admin/alert_stack', [
                        'alerts'       => $systemAlertItems,
                        'wrapperClass' => 'd-flex flex-column gap-2',
                        'itemClass'    => 'alert d-flex align-items-start mb-0',
                    ]) ?>
                    <div class="mt-3" data-flow-external="true"
                         data-flow-label="<?= sp_attr((string) ($systemAlertFooter['flowLabel'] ?? '')) ?>"
                         data-flow-context="<?= sp_attr((string) ($systemAlertFooter['flowContext'] ?? '')) ?>"
                         data-flow-return-label="<?= sp_attr((string) ($systemAlertFooter['returnLabel'] ?? '')) ?>">
                        <?= view('components/admin/section_action_footer', $systemAlertFooter) ?>
                    </div>
                <?php else: ?>
                    <?= view('components/empty_state', [
                        'icon'             => 'bi bi-shield-check',
                        'title'            => lang('DashboardAdmin.sections.alerts.emptyTitle'),
                        'description'      => lang('DashboardAdmin.sections.alerts.emptyDescription'),
                        'wrapperClass'     => 'table-empty',
                        'iconWrapperClass' => 'table-empty-icon',
                        'titleClass'       => 'table-empty-title',
                        'descriptionClass' => 'table-empty-description',
                        'iconClass'        => '',
                    ]) ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- 2b. Pontos pendentes + Resumo justificativas -->
<div class="row g-4 mb-4" id="pending-punches-row">

    <!-- Pontos pendentes de revisão -->
    <div class="col-12 col-xl-8">
        <section class="card h-100" id="pending-punches-section" aria-labelledby="pp-h">
            <div class="db-card-header">
                <h2 class="db-card-title" id="pp-h">
                    <i class="bi bi-clock-history text-warning" aria-hidden="true"></i>
                    Pontos Pendentes de Revisão
                </h2>
                <?php if ($_pendingPunchCount > 0): ?>
                    <span class="badge rounded-pill text-bg-danger"><?= $_pendingPunchCount ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($_pendingPunches)): ?>
                    <div class="table-responsive">
                        <table class="table table-enhanced mb-0">
                            <thead>
                                <tr>
                                    <th>Colaborador</th>
                                    <th>Tipo</th>
                                    <th>Data/Hora</th>
                                    <th>Situação</th>
                                    <th style="text-align:center">Tentativas</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_pendingPunches as $_pp): ?>
                                    <?php
                                    $_ppDate = $_pp['intended_time'] ? date('d/m/Y H:i', strtotime($_pp['intended_time'])) : '-';
                                    $_ppType = $_punchTypeLabels[$_pp['intended_punch_type']] ?? ucfirst($_pp['intended_punch_type']);
                                    $_ppSit  = $_situationLabels[$_pp['situation_type']]     ?? ucfirst($_pp['situation_type']);
                                    $_ppInit = mb_strtoupper(mb_substr($_pp['employee_name'] ?? '?', 0, 1));
                                    ?>
                                    <tr title="<?= esc($_pp['justification_text'] ?? '') ?>">
                                        <td>
                                            <div class="table-cell-avatar">
                                                <div class="table-avatar" aria-hidden="true"><?= esc($_ppInit) ?></div>
                                                <span><?= esc($_pp['employee_name'] ?? '-') ?></span>
                                            </div>
                                        </td>
                                        <td><span class="table-status info"><?= esc($_ppType) ?></span></td>
                                        <td><?= esc($_ppDate) ?></td>
                                        <td><span class="table-status warning"><?= esc($_ppSit) ?></span></td>
                                        <td style="text-align:center">
                                            <span class="badge <?= (int)$_pp['technical_failures_count'] >= 4 ? 'text-bg-danger' : 'text-bg-secondary' ?>">
                                                <?= (int)$_pp['technical_failures_count'] ?>x
                                            </span>
                                        </td>
                                        <td><span class="badge text-bg-warning">Pendente</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3">
                        <a href="<?= site_url('manager/pending-punches') ?>" class="btn btn-sm btn-outline-warning">
                            <i class="bi bi-arrow-right-circle me-1"></i>Ver todas as pendências
                        </a>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                        Nenhum ponto pendente de revisão.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Resumo de justificativas -->
    <div class="col-12 col-xl-4">
        <section class="card h-100" id="justifications-summary-section" aria-labelledby="jsumm-h">
            <div class="db-card-header">
                <h2 class="db-card-title" id="jsumm-h">
                    <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>
                    Justificativas
                </h2>
                <?php if ($_justSummary['pendente'] > 0): ?>
                    <span class="badge rounded-pill text-bg-warning"><?= $_justSummary['pendente'] ?> pendentes</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- Status cards -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="text-center p-2 rounded" style="background:var(--sp-warning-light);border:1px solid transparent">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--sp-warning);line-height:1"><?= $_justSummary['pendente'] ?></div>
                            <div style="font-size:.7rem;color:var(--sp-warning);margin-top:.2rem">Pendentes</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 rounded" style="background:var(--sp-success-light);border:1px solid transparent">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--sp-success);line-height:1"><?= $_justSummary['aprovada'] ?></div>
                            <div style="font-size:.7rem;color:var(--sp-success);margin-top:.2rem">Aprovadas</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 rounded" style="background:var(--sp-danger-light);border:1px solid transparent">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--sp-danger);line-height:1"><?= $_justSummary['reprovada'] ?></div>
                            <div style="font-size:.7rem;color:var(--sp-danger);margin-top:.2rem">Reprovadas</div>
                        </div>
                    </div>
                </div>

                <!-- Total bar -->
                <?php
                $_justTotal = array_sum($_justSummary);
                $_justPct   = $_justTotal > 0 ? round($_justSummary['aprovada'] / $_justTotal * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:var(--sp-text-muted)">
                        <span>Taxa de aprovação</span>
                        <strong><?= $_justPct ?>%</strong>
                    </div>
                    <div style="height:8px;background:var(--sp-gray-200);border-radius:4px;overflow:hidden">
                        <div style="height:100%;width:<?= $_justPct ?>%;background:var(--sp-success);border-radius:4px;transition:width .4s"></div>
                    </div>
                </div>

                <!-- Pending list preview -->
                <?php if ($_justSummary['pendente'] > 0): ?>
                    <?php
                    $_pendJust = $_db->query("
                        SELECT j.id, e.name AS emp_name, j.justification_date, j.justification_type, j.category
                        FROM justifications j JOIN employees e ON e.id = j.employee_id
                        WHERE j.status = 'pendente' AND j.deleted_at IS NULL
                        ORDER BY j.justification_date DESC LIMIT 4
                    ")->getResultArray();
                    $_justTypeLabels = ['falta'=>'Falta','atraso'=>'Atraso','saida_antecipada'=>'Saída Antec.','outro'=>'Outro'];
                    ?>
                    <ul class="list-unstyled mb-3" style="font-size:.82rem">
                        <?php foreach ($_pendJust as $_j): ?>
                            <li class="d-flex align-items-center gap-2 py-1 border-bottom" style="border-color:var(--sp-border)!important">
                                <span class="badge text-bg-warning" style="font-size:.65rem"><?= esc($_justTypeLabels[$_j['justification_type']] ?? ucfirst($_j['justification_type'])) ?></span>
                                <span class="flex-grow-1 text-truncate"><?= esc($_j['emp_name']) ?></span>
                                <span class="text-muted" style="font-size:.72rem;white-space:nowrap"><?= date('d/m', strtotime($_j['justification_date'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <a href="<?= site_url('justifications') ?>" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-list-check me-1"></i>Ver todas as justificativas
                </a>
            </div>
        </section>
    </div>

</div><!-- /row pending-punches-row -->

<!-- 3. Atividades recentes + Métodos de registro -->
<div class="row g-4 mb-4">

<div class="col-12 col-lg-6">
<section class="card h-100 mb-0" id="recent-activities-section" aria-labelledby="recent-h">
    <div class="db-card-header">
        <h2 class="db-card-title" id="recent-h" tabindex="-1" data-section-heading="recent-activities-section">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <?= esc(lang('DashboardAdmin.sections.recent.title')) ?>
        </h2>
        <?php if ($recentActivityCount > 0): ?>
            <span class="badge rounded-pill text-bg-primary"><?= esc($recentActivityCount) ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($recentActivityItems !== []): ?>
            <ul class="activity-list mb-0" role="list" style="max-height:340px;overflow-y:auto;overflow-x:hidden">
                <?php foreach ($recentActivityItems as $activity): ?>
                    <li class="activity-item">
                        <div class="activity-icon info" aria-hidden="true"><i class="bi bi-dot"></i></div>
                        <div class="activity-content">
                            <div class="activity-title"><?= esc($activity['title'] ?? lang('DashboardAdmin.sections.recent.defaultTitle')) ?></div>
                            <div class="activity-description">
                                <?= esc(lang('DashboardAdmin.sections.recent.userPrefix')) ?>
                                <?= esc($activity['userLabel'] ?? lang('DashboardAdmin.sections.recent.defaultUser')) ?>
                            </div>
                            <div class="activity-time">
                                <i class="bi bi-clock" aria-hidden="true"></i>
                                <?= esc($activity['createdAtLabel'] ?? $missingText) ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="p-3" data-flow-external="true"
                 data-flow-label="<?= sp_attr((string) ($recentActivityFooter['flowLabel'] ?? '')) ?>"
                 data-flow-context="<?= sp_attr((string) ($recentActivityFooter['flowContext'] ?? '')) ?>"
                 data-flow-return-label="<?= sp_attr((string) ($recentActivityFooter['returnLabel'] ?? '')) ?>">
                <?= view('components/admin/section_action_footer', $recentActivityFooter) ?>
            </div>
        <?php else: ?>
            <div class="p-4">
                <?= view('components/empty_state', [
                    'icon'             => 'bi bi-inbox',
                    'title'            => lang('DashboardAdmin.sections.recent.emptyTitle'),
                    'description'      => lang('DashboardAdmin.sections.recent.emptyDescription'),
                    'wrapperClass'     => 'table-empty',
                    'iconWrapperClass' => 'table-empty-icon',
                    'titleClass'       => 'table-empty-title',
                    'descriptionClass' => 'table-empty-description',
                    'iconClass'        => '',
                ]) ?>
            </div>
        <?php endif; ?>
    </div>
</section>
</div><!-- /col recent -->

<!-- KPI: Métodos de registro de ponto -->
<div class="col-12 col-lg-6">
<section class="card mb-0" id="punch-methods-kpi" aria-labelledby="methods-h"
         style="min-height:0;overflow:hidden">
    <div class="db-card-header">
        <h2 class="db-card-title" id="methods-h">
            <i class="bi bi-bar-chart-fill" aria-hidden="true"></i>
            Métodos de registro
        </h2>
        <div class="d-flex gap-1 flex-wrap" id="methodFilterBtns" role="group" aria-label="Filtrar período">
            <button type="button" class="btn btn-sm btn-primary"          data-period="hoje">Hoje</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-period="7d">7 dias</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-period="30d">30 dias</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-period="tudo">Tudo</button>
        </div>
    </div>
    <div class="card-body" style="padding:1rem;overflow:hidden">

        <!-- total badge -->
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="text-muted" style="font-size:.78rem">Total no período:</span>
            <span id="methodsTotal" class="badge rounded-pill text-bg-primary" style="font-size:.78rem">0</span>
        </div>

        <!-- Chart: fixed 150px, non-growing -->
        <div id="methodsChartWrap" style="position:relative;width:100%;height:150px;overflow:hidden">
            <canvas id="methodsChart" style="position:absolute;inset:0;width:100%!important;height:150px!important"
                    aria-label="Gráfico de métodos de registro"></canvas>
        </div>

        <!-- Ruler: horizontal progress bars -->
        <div id="methodsRuler" style="margin-top:.85rem;display:flex;flex-direction:column;gap:.55rem"></div>

        <!-- Empty state -->
        <p id="methodsEmpty" class="text-muted text-center mt-3 mb-0 d-none" style="font-size:.85rem">
            <i class="bi bi-inbox me-1"></i>Nenhum registro no período.
        </p>
    </div>
</section>
</div><!-- /col methods -->

</div><!-- /row -->






<!-- 5. Observabilidade -->
<details class="mb-4" id="observability-details">
    <summary class="card card-header d-flex align-items-center gap-2 mb-3" style="cursor:pointer;list-style:none;">
        <i class="bi bi-broadcast-pin" aria-hidden="true"></i>
        <span class="h5 mb-0"><?= esc(lang('DashboardAdmin.delivery.observabilityTitle')) ?></span>
        <span class="ms-auto text-muted small" data-role="observability-toggle-hint">Clique para expandir</span>
    </summary>

    <div id="dashboard-delivery-overview"
         class="alert alert-warning d-none align-items-start gap-3 mb-4"
         role="status" aria-live="polite">
        <div class="fs-4 lh-1"><i class="bi bi-broadcast-pin" aria-hidden="true"></i></div>
        <div class="flex-grow-1">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <div>
                    <h2 class="h6 fw-semibold mb-1" id="dashboard-delivery-overview-title" data-role="delivery-overview-title">
                        <?= esc(lang('DashboardAdmin.delivery.overviewTitle')) ?>
                    </h2>
                    <div class="small text-muted" data-role="delivery-overview-description">
                        <?= esc(lang('DashboardAdmin.delivery.overviewDescriptionOk')) ?>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2" data-role="delivery-overview-badges">
                    <span class="badge rounded-pill text-bg-success"><span data-role="delivery-ok-count">0</span> <?= esc(lang('DashboardAdmin.delivery.badgeOkSuffix')) ?></span>
                    <span class="badge rounded-pill text-bg-warning"><span data-role="delivery-degraded-count">0</span> <?= esc(lang('DashboardAdmin.delivery.badgeDegradedSuffix')) ?></span>
                    <span class="badge rounded-pill text-bg-danger"><span data-role="delivery-error-count">0</span> <?= esc(lang('DashboardAdmin.delivery.badgeErrorSuffix')) ?></span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                <div class="small text-muted" data-role="delivery-last-event"><?= esc(lang('DashboardAdmin.delivery.lastEventEmpty')) ?></div>
                <div class="btn-group btn-group-sm" role="group" data-role="delivery-filter-group">
                    <button type="button" class="btn btn-outline-secondary active" data-delivery-filter="all" aria-pressed="true"><?= esc(lang('DashboardAdmin.delivery.filters.all')) ?></button>
                    <button type="button" class="btn btn-outline-warning" data-delivery-filter="affected" aria-pressed="false"><?= esc(lang('DashboardAdmin.delivery.filters.affected')) ?></button>
                    <button type="button" class="btn btn-outline-danger" data-delivery-filter="error" aria-pressed="false"><?= esc(lang('DashboardAdmin.delivery.filters.error')) ?></button>
                    <button type="button" class="btn btn-outline-warning" data-delivery-filter="degraded" aria-pressed="false"><?= esc(lang('DashboardAdmin.delivery.filters.degraded')) ?></button>
                </div>
            </div>
        </div>
        <div class="d-flex flex-column align-items-stretch gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-role="delivery-scroll-action">
                <i class="bi bi-arrow-down-right-circle me-1" aria-hidden="true"></i><?= esc(lang('DashboardAdmin.delivery.scrollAction')) ?>
            </button>
            <div class="small text-end text-muted d-none" data-role="delivery-filter-hint"></div>
        </div>
    </div>

    <section id="observability-widgets-region">
        <div class="row g-3 mb-4">
            <div class="col-12" data-widget-shell="system-incident">
                <?= view('dashboard/partials/admin_system_incident_widget', ['systemIncidentObservability' => $systemIncidentObservability ?? []]) ?>
            </div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-12 col-xxl-6" data-widget-shell="database">
                <?= view('dashboard/partials/admin_database_observability_widget', ['dbObservability' => $dbObservability ?? []]) ?>
            </div>
            <div class="col-12 col-xxl-6" data-widget-shell="deepface">
                <?= view('dashboard/partials/admin_deepface_observability_widget', ['deepFaceObservability' => $deepFaceObservability ?? []]) ?>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-12 col-xl-6" data-widget-shell="settings-cache">
                <?= view('dashboard/partials/admin_settings_cache_widget', ['settingsCacheObservability' => $settingsCacheObservability ?? []]) ?>
            </div>
            <div class="col-12 col-xl-6" data-widget-shell="performance-index">
                <?= view('dashboard/partials/admin_performance_index_widget', ['performanceIndexObservability' => $performanceIndexObservability ?? []]) ?>
            </div>
            <div class="col-12 col-xl-6" data-widget-shell="report-performance">
                <?= view('dashboard/partials/admin_report_performance_widget', ['reportPerformanceObservability' => $reportPerformanceObservability ?? []]) ?>
            </div>
            <div class="col-12 col-xl-6" data-widget-shell="rate-limit">
                <?= view('dashboard/partials/admin_rate_limit_widget', ['rateLimitObservability' => $rateLimitObservability ?? []]) ?>
            </div>
            <div class="col-12 col-xl-6" data-widget-shell="feature-flags">
                <?= view('dashboard/partials/admin_feature_flags_widget', ['featureFlagsObservability' => $featureFlagsObservability ?? []]) ?>
            </div>
        </div>
    </section>
</details>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('dashboard/partials/admin_database_observability_widget_refresh') ?>
<?= view('dashboard/partials/admin_deepface_observability_widget_refresh') ?>
<?= view('dashboard/partials/admin_feature_flags_widget_refresh') ?>
<?= view('dashboard/partials/admin_system_incident_widget_refresh') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    const root = document.getElementById('dashboard-delivery-overview');
    if (!root) return;
    const widgetElements = Array.from(document.querySelectorAll('[data-summary-url]'));
    const widgets = () => widgetElements;
    const titleEl        = root.querySelector('[data-role="delivery-overview-title"]');
    const descriptionEl  = root.querySelector('[data-role="delivery-overview-description"]');
    const okCountEl      = root.querySelector('[data-role="delivery-ok-count"]');
    const degradedCountEl= root.querySelector('[data-role="delivery-degraded-count"]');
    const errorCountEl   = root.querySelector('[data-role="delivery-error-count"]');
    const scrollAction   = root.querySelector('[data-role="delivery-scroll-action"]');
    const filterHintEl   = root.querySelector('[data-role="delivery-filter-hint"]');
    const filterButtons  = Array.from(root.querySelectorAll('[data-delivery-filter]'));
    const details        = document.getElementById('observability-details');
    const toggleHint     = document.querySelector('[data-role="observability-toggle-hint"]');
    let activeFilter = 'all';

    if (toggleHint && details) {
        details.addEventListener('toggle', () => {
            toggleHint.textContent = details.open ? 'Clique para recolher' : 'Clique para expandir';
        });
    }

    const shellOf = (w) => w.closest('[data-widget-shell]') || w.parentElement || w;

    const counts = () => widgets().reduce((acc, w) => {
        const mode = (w.dataset.deliveryMode || 'ok').toLowerCase();
        if (mode === 'degraded') { acc.degraded++; acc.affected.push(w); }
        else if (mode === 'error') { acc.error++; acc.affected.push(w); }
        else acc.ok++;
        return acc;
    }, {ok: 0, degraded: 0, error: 0, affected: []});

    const applyFilter = () => {
        widgets().forEach((w) => {
            const mode = (w.dataset.deliveryMode || 'ok').toLowerCase();
            const shell = shellOf(w);
            const show = activeFilter === 'all'
                || (activeFilter === 'affected' && (mode === 'degraded' || mode === 'error'))
                || (activeFilter === 'error' && mode === 'error')
                || (activeFilter === 'degraded' && mode === 'degraded');
            shell.classList.toggle('d-none', !show);
        });
        const hints = {
            all: <?= json_encode(lang('DashboardAdmin.delivery.hints.all')) ?>,
            affected: <?= json_encode(lang('DashboardAdmin.delivery.hints.affected')) ?>,
            error: <?= json_encode(lang('DashboardAdmin.delivery.hints.error')) ?>,
            degraded: <?= json_encode(lang('DashboardAdmin.delivery.hints.degraded')) ?>,
        };
        filterHintEl.textContent = hints[activeFilter] || hints.all;
        filterHintEl.classList.toggle('d-none', activeFilter === 'all');
        filterButtons.forEach((b) => {
            const active = b.dataset.deliveryFilter === activeFilter;
            b.classList.toggle('active', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    };

    const setOverviewState = (stats) => {
        okCountEl.textContent      = String(stats.ok);
        degradedCountEl.textContent= String(stats.degraded);
        errorCountEl.textContent   = String(stats.error);
        const affected = stats.degraded + stats.error;
        root.classList.remove('d-none', 'alert-warning', 'alert-danger', 'alert-info');
        if (affected === 0) {
            root.classList.add('alert-info');
            titleEl.textContent = <?= json_encode(lang('DashboardAdmin.delivery.overviewTitle')) ?>;
            descriptionEl.textContent = <?= json_encode(lang('DashboardAdmin.delivery.overviewDescriptionOk')) ?>;
            scrollAction.classList.add('d-none');
        } else if (stats.error > 0) {
            root.classList.add('alert-danger');
            titleEl.textContent = stats.error === 1
                ? <?= json_encode(lang('DashboardAdmin.delivery.state.errorOne')) ?>
                : <?= json_encode(lang('DashboardAdmin.delivery.state.errorMany')) ?>.replace('{0}', String(stats.error));
            descriptionEl.textContent = stats.degraded > 0
                ? <?= json_encode(lang('DashboardAdmin.delivery.state.errorDescriptionMixed')) ?>.replace('{0}', String(stats.degraded))
                : <?= json_encode(lang('DashboardAdmin.delivery.state.errorDescriptionOnly')) ?>;
            scrollAction.classList.remove('d-none');
            if (details) details.open = true;
        } else {
            root.classList.add('alert-warning');
            titleEl.textContent = stats.degraded === 1
                ? <?= json_encode(lang('DashboardAdmin.delivery.state.degradedOne')) ?>
                : <?= json_encode(lang('DashboardAdmin.delivery.state.degradedMany')) ?>.replace('{0}', String(stats.degraded));
            descriptionEl.textContent = <?= json_encode(lang('DashboardAdmin.delivery.state.degradedDescription')) ?>;
            scrollAction.classList.remove('d-none');
            if (details) details.open = true;
        }
        applyFilter();
    };

    scrollAction?.addEventListener('click', () => {
        const first = counts().affected[0] || null;
        if (first) shellOf(first).scrollIntoView({behavior: 'smooth', block: 'center'});
    });
    filterButtons.forEach((b) => {
        b.addEventListener('click', () => { activeFilter = b.dataset.deliveryFilter || 'all'; applyFilter(); });
    });
    document.addEventListener('support:dashboard-widget-refresh', (e) => {
        setOverviewState(counts());
    });
    setOverviewState(counts());

    document.querySelectorAll('[data-dashboard-scroll-link="true"]').forEach((link) => {
        link.addEventListener('click', (e) => {
            const href = link.getAttribute('href') || '';
            if (!href.startsWith('#')) return;
            const target = document.querySelector(href);
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({behavior: 'smooth', block: 'start'});
        });
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    const allStats   = <?= $_statsJson ?? '{}' ?>;
    const COLORS     = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6'];
    const cssVar = (n,f)=>{try{const v=getComputedStyle(document.documentElement).getPropertyValue(n).trim();return v||f;}catch(e){return f;}};
    const axisColor = cssVar('--sp-text-muted','#6b7280');
    const gridColor = cssVar('--sp-border','rgba(125,125,125,.15)');
    const METHOD_ICONS = {
        'codigo':'bi-person-badge', 'cpf':'bi-card-text',
        'facial':'bi-person-bounding-box', 'biometria':'bi-fingerprint',
        'qrcode':'bi-qr-code-scan', 'manual':'bi-pencil-square'
    };
    let activePeriod = 'hoje';
    let chartInst    = null;

    const canvas    = document.getElementById('methodsChart');
    const wrap      = document.getElementById('methodsChartWrap');
    const ruler     = document.getElementById('methodsRuler');
    const emptyEl   = document.getElementById('methodsEmpty');
    const totalEl   = document.getElementById('methodsTotal');

    function render(period) {
        const data   = allStats[period] || [];
        const total  = data.reduce((s, d) => s + d.count, 0);
        const colors = data.map((_, i) => COLORS[i % COLORS.length]);

        totalEl.textContent = total + (total === 1 ? ' registro' : ' registros');

        if (total === 0) {
            if (chartInst) { chartInst.destroy(); chartInst = null; }
            wrap.style.display  = 'none';
            ruler.innerHTML     = '';
            emptyEl.classList.remove('d-none');
            return;
        }

        wrap.style.display = '';
        emptyEl.classList.add('d-none');

        /* ── Bar chart (fixed 150 px, no growth) ── */
        const labels = data.map(d => d.label);
        const counts = data.map(d => d.count);

        if (chartInst) {
            chartInst.data.labels                        = labels;
            chartInst.data.datasets[0].data              = counts;
            chartInst.data.datasets[0].backgroundColor   = colors;
            chartInst.update('none'); /* skip animation on filter switch */
        } else {
            chartInst = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Registros',
                        data: counts,
                        backgroundColor: colors,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 48,
                    }]
                },
                options: {
                    responsive: false,           /* fixed size — prevents growth loop */
                    animation: { duration: 300 },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const pct = Math.round(ctx.parsed.y / total * 100);
                                    return ' ' + ctx.parsed.y + ' registros (' + pct + '%)';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, color: axisColor, font: { size: 11 } },
                            grid:  { color: gridColor },
                        },
                        x: {
                            ticks: { color: axisColor, font: { size: 11 } },
                            grid:  { display: false }
                        }
                    }
                }
            });
            /* force fixed pixel size after create */
            canvas.width  = wrap.offsetWidth  || 300;
            canvas.height = 150;
        }

        /* ── Horizontal ruler bars ── */
        ruler.innerHTML = data.map((d, i) => {
            const pct  = total > 0 ? Math.round(d.count / total * 100) : 0;
            const icon = METHOD_ICONS[d.method] || 'bi-dot';
            const col  = colors[i];
            return '<div style="display:grid;grid-template-columns:1fr auto;align-items:center;gap:.4rem .6rem">'
                + '<div style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                +   '<i class="bi ' + icon + '" style="color:' + col + ';flex-shrink:0"></i>'
                +   '<span style="overflow:hidden;text-overflow:ellipsis">' + d.label + '</span>'
                + '</div>'
                + '<span style="font-size:.75rem;color:var(--sp-text-muted);white-space:nowrap">' + d.count + ' (' + pct + '%)</span>'
                + '<div style="grid-column:1/-1;height:10px;background:var(--sp-gray-200);border-radius:5px;overflow:hidden">'
                +   '<div style="height:100%;width:' + pct + '%;background:' + col + ';border-radius:5px;'
                +        'transition:width .4s ease"></div>'
                + '</div>'
                + '</div>';
        }).join('');
    }

    document.querySelectorAll('#methodFilterBtns [data-period]').forEach(btn => {
        btn.addEventListener('click', () => {
            activePeriod = btn.dataset.period;
            document.querySelectorAll('#methodFilterBtns [data-period]').forEach(b => {
                b.className = b.dataset.period === activePeriod
                    ? 'btn btn-sm btn-primary'
                    : 'btn btn-sm btn-outline-secondary';
            });
            render(activePeriod);
        });
    });

    /* init after layout is stable */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => render(activePeriod));
    } else {
        render(activePeriod);
    }
})();
</script>
<?= $this->endSection() ?>
