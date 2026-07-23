<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc(lang('DashboardAdmin.browserTitle')) ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/pages/dashboard.css')) ?>">
<style>
/* ══ Comando / hero ══════════════════════════════════════════════════ */
.sp-cmd-hero{
    position:relative;overflow:hidden;border-radius:16px;margin-bottom:1.25rem;
    background:linear-gradient(135deg,var(--sp-bg-surface,#161a22) 0%,var(--sp-bg-page,#0e1116) 100%);
    border:1px solid var(--sp-border,rgba(255,255,255,.08));
    padding:1.4rem 1.6rem;
}
.sp-cmd-hero::before{
    content:'';position:absolute;top:-60%;right:-8%;width:340px;height:340px;border-radius:50%;
    background:radial-gradient(circle,var(--accent,#4f46e5) 0%,transparent 70%);opacity:.16;pointer-events:none;
}
.sp-cmd-hero__row{position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:1rem;justify-content:space-between}
.sp-cmd-hero__eyebrow{font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--accent,#4f46e5);margin-bottom:.3rem;display:block}
.sp-cmd-hero__title{font-size:1.5rem;font-weight:800;color:var(--sp-text-primary);margin:0;line-height:1.2}
.sp-cmd-hero__subtitle{font-size:.82rem;color:var(--sp-text-secondary,var(--sp-text-muted));margin-top:.25rem}
.sp-cmd-hero__status{
    display:flex;align-items:center;gap:.55rem;padding:.55rem .9rem;border-radius:999px;flex-shrink:0;
    background:var(--sp-bg-page,rgba(0,0,0,.15));border:1px solid var(--sp-border,rgba(255,255,255,.08));
}
.sp-cmd-hero__status-dot{width:.55rem;height:.55rem;border-radius:50%;flex-shrink:0}
.sp-cmd-hero__status-text{font-size:.78rem;font-weight:600;color:var(--sp-text-primary)}

/* ══ Categoria (agrupamento tematico de indicadores) ═══════════════════ */
/* Component tokens por categoria — cada uma referencia as mesmas variaveis
   semanticas (--sp-*) só trocando o par bg/color, nada de hex novo solto. */
.sp-cat{ margin-bottom:1.75rem; }
.sp-cat__head{ display:flex; align-items:center; gap:.75rem; margin-bottom:.85rem; }
.sp-cat__icon{
    width:2.4rem;height:2.4rem;border-radius:.65rem;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;font-size:1.05rem;
}
.sp-cat__title{ font-size:1rem; font-weight:700; color:var(--sp-text-primary); margin:0; line-height:1.2 }
.sp-cat__desc{ font-size:.76rem; color:var(--sp-text-muted); margin:.1rem 0 0 }
.sp-cat__badge{
    margin-left:auto; font-size:.72rem; font-weight:700; padding:.3rem .65rem; border-radius:999px;
    flex-shrink:0; white-space:nowrap;
}
.sp-cat--rh        .sp-cat__icon{ background:var(--sp-info-light);    color:var(--sp-info) }
.sp-cat--ponto      .sp-cat__icon{ background:var(--sp-primary-light); color:var(--sp-primary) }
.sp-cat--pendencias .sp-cat__icon{ background:var(--sp-warning-light); color:var(--sp-warning) }
.sp-cat--compliance .sp-cat__icon{ background:rgba(139,92,246,.15);    color:#8b5cf6 }
.sp-cat--auditoria  .sp-cat__icon{ background:rgba(91,115,232,.15);    color:var(--sp-info) }
.sp-cat--metricas   .sp-cat__icon{ background:var(--sp-gray-200);      color:var(--sp-text-secondary) }

/* KPI card — mesmo componente pra todas as categorias */
.sp-kpi-card{border:1px solid var(--sp-border,rgba(0,0,0,.08))!important;transition:transform .15s,box-shadow .15s}
.sp-kpi-card:hover{box-shadow:0 6px 18px rgba(0,0,0,.1)!important;transform:translateY(-1px)}
.sp-kpi-icon{width:44px;height:44px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.15rem}
.sp-kpi-value{font-size:1.55rem;font-weight:700;line-height:1.1;color:var(--sp-text-primary,#1a1a1a)}
.sp-kpi-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;color:var(--sp-text-muted,#888);margin-top:.15rem;font-weight:600}
.sp-kpi-desc{font-size:.7rem;color:var(--sp-text-muted,#999);margin-top:.2rem}

/* Cartao de status de compliance (NSR / PIS) */
.sp-compliance-card{ border:1px solid var(--sp-border) !important; height:100% }
.sp-compliance-card__row{ display:flex; align-items:flex-start; gap:.75rem; padding:.9rem 1rem }
.sp-compliance-card__icon{ width:2.2rem;height:2.2rem;border-radius:.6rem;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1rem }
.sp-compliance-card__title{ font-size:.85rem; font-weight:700; color:var(--sp-text-primary); margin:0 }
.sp-compliance-card__desc{ font-size:.76rem; color:var(--sp-text-muted); margin:.15rem 0 0 }
.sp-compliance-status--ok{ background:var(--sp-success-light); color:var(--sp-success) }
.sp-compliance-status--warning{ background:var(--sp-warning-light); color:var(--sp-warning) }
.sp-compliance-status--error{ background:var(--sp-danger-light); color:var(--sp-danger) }

#health-band .card{border-left-width:3px!important}
#health-band .card-body{padding:.75rem 1.1rem!important}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('components/observability/dashboard_refresh_runtime') ?>

<?php
$dashboardPresentation        = is_array($dashboardPresentation ?? null)                          ? $dashboardPresentation                          : [];
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

// Estatisticas cruas (DashboardAdminService::statistics()) — usadas direto aqui em
// vez de reindexar as listas primaryStats/secondaryStats do presenter, pra deixar
// explicito qual numero alimenta qual card em cada categoria.
$statistics = is_array($statistics ?? null) ? $statistics : [];
$compliance = is_array($compliance ?? null) ? $compliance : [];
$nsrHealth  = is_array($compliance['nsr_health'] ?? null) ? $compliance['nsr_health'] : ['status' => 'error', 'message' => 'Indisponível'];
$employeesWithoutPis = (int) ($compliance['employees_without_pis'] ?? 0);

// Saudação + status geral (calculados aqui pra dar identidade à página sem
// precisar de nenhum dado novo do backend).
$_firstName = 'Admin';
if (is_object($currentUser) && !empty($currentUser->name)) {
    $_firstName = trim(explode(' ', (string) $currentUser->name)[0]);
} elseif (is_array($currentUser) && !empty($currentUser['name'])) {
    $_firstName = trim(explode(' ', (string) $currentUser['name'])[0]);
}
$_diasSemana = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
$_meses      = ['','janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$_now        = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
$_dataExtenso = $_diasSemana[(int) $_now->format('w')] . ', ' . (int) $_now->format('j') . ' de ' . $_meses[(int) $_now->format('n')] . ' de ' . $_now->format('Y');
$_hour       = (int) $_now->format('G');
$_saudacao   = $_hour < 12 ? 'Bom dia' : ($_hour < 18 ? 'Boa tarde' : 'Boa noite');

// Cadastros pendentes de aprovação -- vem de $pendingApprovals['employees']
// (DashboardAdminService::pendingApprovals()).
$_pendingRegistrations      = is_array($pendingApprovals['employees'] ?? null) ? $pendingApprovals['employees'] : [];
$_pendingRegistrationsCount = (int) ($statistics['pending_registrations'] ?? count($_pendingRegistrations));

try {
    $_health = (new \App\Services\Health\SystemHealthCheckService())->detailedHealth();
} catch (\Throwable $_he) {
    $_health = ['status' => 'error', 'summary' => [], 'modules' => [], 'alerts' => []];
}
// SystemHealthCheckService::detailedHealth()['status'] vem de aggregateStatus(),
// que usa um vocabulário PRÓPRIO ("healthy"/"degraded"/"unhealthy") — diferente do
// vocabulário por módulo ("ok"/"warning"/"error", usado em cada item de
// $_hModules). Sem esta tradução, "healthy" nunca batia com nenhuma chave de
// $_statusMeta e caía sempre no fallback de erro — o sistema aparecia com o
// selo "Erro detectado" mesmo 100% saudável, e a palavra em inglês "HEALTHY"
// ainda vazava direto pro card de Métricas Administrativas.
$_hStatus  = $_health['status']  ?? 'error';
$_hStatusToVariant = ['healthy' => 'ok', 'degraded' => 'warning', 'unhealthy' => 'error'];
$_hVariant = $_hStatusToVariant[$_hStatus] ?? 'error';
$_hStatusLabelPt = ['healthy' => 'SAUDÁVEL', 'degraded' => 'DEGRADADO', 'unhealthy' => 'COM ERRO'][$_hStatus] ?? 'DESCONHECIDO';
$_hSummary = $_health['summary'] ?? [];
$_hModules = $_health['modules'] ?? [];
$_hAlerts  = $_health['alerts']  ?? [];
$_hOk      = (int)($_hSummary['ok_count']      ?? 0);
$_hWarn    = (int)($_hSummary['warning_count']  ?? 0);
$_hErr     = (int)($_hSummary['error_count']    ?? 0);

$_statusMeta = [
    'ok'      => ['color' => '#198754', 'bg' => 'rgba(25,135,84,.1)',  'icon' => 'bi-check-circle-fill',        'label' => 'Operando normalmente'],
    'warning' => ['color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.1)', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'Atenção em algum módulo'],
    'error'   => ['color' => '#dc3545', 'bg' => 'rgba(220,53,69,.1)',  'icon' => 'bi-x-circle-fill',            'label' => 'Erro detectado'],
];
$_overallMeta = $_statusMeta[$_hVariant] ?? $_statusMeta['error'];

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

/** Renderiza um card de KPI simples (usado nas seções RH / Ponto / Compliance). */
$kpiCard = static function (string $label, string $value, string $icon, string $bg, string $color, string $col = 'col-6 col-md-3', ?string $href = null) {
    $tag = $href ? 'a' : 'div';
    $hrefAttr = $href ? ' href="' . esc($href) . '"' : '';
    echo '<div class="' . $col . '">';
    echo "<{$tag}{$hrefAttr} class=\"card border-0 h-100 sp-kpi-card text-decoration-none\">";
    echo '<div class="card-body d-flex align-items-center gap-3 py-3 px-3">';
    echo '<span class="sp-kpi-icon flex-shrink-0" style="background:' . $bg . ';color:' . $color . '"><i class="' . esc($icon) . '"></i></span>';
    echo '<div class="min-w-0 overflow-hidden">';
    echo '<div class="sp-kpi-value">' . esc($value) . '</div>';
    echo '<div class="sp-kpi-label">' . esc($label) . '</div>';
    echo '</div></div>';
    echo "</{$tag}>";
    echo '</div>';
};

/** Cabeçalho padrão de categoria — mesmo componente nas 6 seções. */
$catHead = static function (string $variantClass, string $icon, string $title, string $desc, ?array $badge = null) {
    echo '<div class="sp-cat__head">';
    echo '<span class="sp-cat__icon"><i class="' . esc($icon) . '"></i></span>';
    echo '<div><h2 class="sp-cat__title">' . esc($title) . '</h2><p class="sp-cat__desc">' . esc($desc) . '</p></div>';
    if ($badge !== null) {
        echo '<span class="sp-cat__badge" style="background:' . esc($badge['bg']) . ';color:' . esc($badge['color']) . '">' . esc($badge['text']) . '</span>';
    }
    echo '</div>';
};
?>
<!-- ══ Comando / boas-vindas ═══════════════════════════════════════════════ -->
<div class="sp-cmd-hero">
    <div class="sp-cmd-hero__row">
        <div>
            <span class="sp-cmd-hero__eyebrow"><i class="bi bi-shield-lock-fill me-1"></i>Painel Administrativo &middot; SupportPONTO</span>
            <h1 class="sp-cmd-hero__title"><?= esc($_saudacao) ?>, <?= esc($_firstName) ?>.</h1>
            <p class="sp-cmd-hero__subtitle"><?= esc(ucfirst($_dataExtenso)) ?> &middot; <?= (int) ($statistics['employees_present'] ?? 0) ?> colaborador(es) já bateram ponto hoje</p>
        </div>
        <div class="sp-cmd-hero__status">
            <span class="sp-cmd-hero__status-dot" style="background:<?= $_overallMeta['color'] ?>"></span>
            <span class="sp-cmd-hero__status-text"><?= esc($_overallMeta['label']) ?></span>
            <a href="<?= site_url('admin/health') ?>" class="btn btn-sm btn-outline-secondary ms-2">Detalhes</a>
        </div>
    </div>
</div>

<!-- ══════════════════════ 1. PENDÊNCIAS ═══════════════════════════════════ -->
<div class="sp-cat sp-cat--pendencias" id="cat-pendencias">
    <?php $catHead('pendencias', 'bi-clipboard-check-fill', 'Pendências', 'Tudo que está aguardando uma ação sua, num só lugar.', $pendingJustificationCount + $_pendingPunchCount + $_pendingRegistrationsCount > 0 ? ['text' => ($pendingJustificationCount + $_pendingPunchCount + $_pendingRegistrationsCount) . ' aguardando', 'bg' => 'var(--sp-warning-light)', 'color' => 'var(--sp-warning)'] : ['text' => 'Tudo em dia', 'bg' => 'var(--sp-success-light)', 'color' => 'var(--sp-success)']); ?>

    <div class="row g-3 mb-3">
        <?php $kpiCard('Justificativas pendentes', (string) $pendingJustificationCount, 'bi bi-clipboard-check', 'var(--sp-warning-light)', 'var(--sp-warning)', 'col-6 col-md-3', '#pending-justifications-section'); ?>
        <?php $kpiCard('Pontos p/ revisão', (string) $_pendingPunchCount, 'bi bi-clock-history', 'var(--sp-danger-light)', 'var(--sp-danger)', 'col-6 col-md-3', '#pending-punches-section'); ?>
        <?php $kpiCard('Cadastros aguardando', (string) $_pendingRegistrationsCount, 'bi bi-person-plus-fill', 'rgba(79,70,229,.14)', '#4338ca', 'col-6 col-md-3', sp_safe_url(sp_route_url('employees.pending'))); ?>
        <?php $kpiCard('Advertências pendentes', (string) ($statistics['active_warnings'] ?? 0), 'bi bi-exclamation-triangle-fill', 'var(--sp-danger-light)', 'var(--sp-danger)', 'col-6 col-md-3'); ?>
    </div>

    <div class="row g-4">
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
                                                    <?= view('components/employee_avatar', ['employeeId' => (int) ($j['employeeId'] ?? 0), 'initials' => $j['employeeInitial'] ?? $missingInitial, 'size' => 32]) ?>
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

        <div class="col-12 col-xl-4">
            <section class="card h-100" id="justifications-summary-section" aria-labelledby="jsumm-h">
                <div class="db-card-header">
                    <h2 class="db-card-title" id="jsumm-h">
                        <i class="bi bi-clipboard2-pulse" aria-hidden="true"></i>
                        Justificativas &mdash; resumo
                    </h2>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <div class="text-center p-2 rounded" style="background:rgba(245,158,11,.16);border:1px solid transparent">
                                <div style="font-size:1.6rem;font-weight:700;color:#f59e0b;line-height:1"><?= $_justSummary['pendente'] ?></div>
                                <div style="font-size:.7rem;color:#f59e0b;margin-top:.2rem">Pendentes</div>
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

                    <a href="<?= site_url('justifications') ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-list-check me-1"></i>Ver todas as justificativas
                    </a>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4 mt-1" id="pending-punches-row">
        <div class="col-12" id="pending-punches-section">
            <section class="card h-100" aria-labelledby="pp-h">
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
                                                    <?= view('components/employee_avatar', ['employeeId' => (int) ($_pp['employee_id'] ?? 0), 'initials' => $_ppInit, 'size' => 32]) ?>
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
    </div>
</div>

<!-- ══════════════════════ 2. RH ═══════════════════════════════════════════ -->
<div class="sp-cat sp-cat--rh" id="cat-rh">
    <?php $catHead('rh', 'bi-people-fill', 'RH', 'Quadro de colaboradores — quem está ativo, inativo e chegando.'); ?>
    <div class="row g-3">
        <?php $kpiCard('Total de colaboradores', (string) ($statistics['total_employees'] ?? 0), 'bi bi-people-fill', 'rgba(91,115,232,.14)', 'var(--sp-info)'); ?>
        <?php $kpiCard('Colaboradores ativos', (string) ($statistics['active_employees'] ?? 0), 'bi bi-person-check-fill', 'var(--sp-success-light)', 'var(--sp-success)'); ?>
        <?php $kpiCard('Colaboradores inativos', (string) ($statistics['total_inactive'] ?? 0), 'bi bi-person-x-fill', 'var(--sp-danger-light)', 'var(--sp-danger)'); ?>
        <?php $kpiCard('Cadastros recentes (7 dias)', (string) ($statistics['pending_registrations'] ?? 0), 'bi bi-calendar-plus', 'rgba(91,115,232,.14)', 'var(--sp-info)', 'col-6 col-md-3', site_url('employees')); ?>
    </div>
</div>

<!-- ══════════════════════ 3. CONTROLE DE PONTO ════════════════════════════ -->
<div class="sp-cat sp-cat--ponto" id="cat-ponto">
    <?php $catHead('ponto', 'bi-fingerprint', 'Controle de Ponto', 'Presença de hoje e como os colaboradores estão registrando o ponto.'); ?>
    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="row g-3 h-100">
                <?php $kpiCard('Presentes hoje', (string) ($statistics['employees_present'] ?? 0), 'bi bi-fingerprint', 'var(--sp-warning-light)', 'var(--sp-warning)', 'col-12'); ?>
                <?php $kpiCard('Registros hoje', (string) ($statistics['punches_today'] ?? 0), 'bi bi-clock-fill', 'var(--sp-primary-light)', 'var(--sp-primary)', 'col-12'); ?>
                <?php $kpiCard('Registros no mês', (string) ($statistics['punches_month'] ?? 0), 'bi bi-calendar3', 'var(--sp-primary-light)', 'var(--sp-primary)', 'col-12'); ?>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <section class="card h-100 mb-0" id="punch-methods-kpi" aria-labelledby="methods-h" style="min-height:0;overflow:hidden">
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
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted" style="font-size:.78rem">Total no período:</span>
                        <span id="methodsTotal" class="badge rounded-pill text-bg-primary" style="font-size:.78rem">0</span>
                    </div>
                    <div id="methodsChartWrap" style="position:relative;width:100%;height:150px;overflow:hidden">
                        <canvas id="methodsChart" style="position:absolute;inset:0;width:100%!important;height:150px!important"
                                aria-label="Gráfico de métodos de registro"></canvas>
                    </div>
                    <div id="methodsRuler" style="margin-top:.85rem;display:flex;flex-direction:column;gap:.55rem"></div>
                    <p id="methodsEmpty" class="text-muted text-center mt-3 mb-0 d-none" style="font-size:.85rem">
                        <i class="bi bi-inbox me-1"></i>Nenhum registro no período.
                    </p>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- ══════════════════════ 4. COMPLIANCE ═══════════════════════════════════ -->
<?php
$_nsrStatus = (string) ($nsrHealth['status'] ?? 'error');
$_nsrMeta = [
    'ok'      => ['class' => 'sp-compliance-status--ok',      'icon' => 'bi-check-circle-fill',        'label' => 'Contador coerente'],
    'warning' => ['class' => 'sp-compliance-status--warning', 'icon' => 'bi-exclamation-triangle-fill', 'label' => 'Atenção'],
    'error'   => ['class' => 'sp-compliance-status--error',   'icon' => 'bi-x-circle-fill',             'label' => 'Erro'],
][$_nsrStatus] ?? ['class' => 'sp-compliance-status--error', 'icon' => 'bi-x-circle-fill', 'label' => 'Erro'];
$_pisMeta = $employeesWithoutPis > 0
    ? ['class' => 'sp-compliance-status--warning', 'icon' => 'bi-exclamation-triangle-fill']
    : ['class' => 'sp-compliance-status--ok',      'icon' => 'bi-check-circle-fill'];
?>
<div class="sp-cat sp-cat--compliance" id="cat-compliance">
    <?php $catHead('compliance', 'bi-shield-check', 'Compliance', 'Conformidade trabalhista — NSR (Portaria MTE 671/2021) e PIS/eSocial.', $compliance['status'] === 'ok' ? ['text' => 'Regular', 'bg' => 'var(--sp-success-light)', 'color' => 'var(--sp-success)'] : ['text' => ($compliance['issues_count'] ?? 0) . ' pendência(s)', 'bg' => 'var(--sp-warning-light)', 'color' => 'var(--sp-warning)']); ?>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <div class="card sp-compliance-card">
                <div class="sp-compliance-card__row">
                    <span class="sp-compliance-card__icon <?= $_nsrMeta['class'] ?>"><i class="bi <?= $_nsrMeta['icon'] ?>"></i></span>
                    <div class="flex-grow-1">
                        <p class="sp-compliance-card__title">Contador NSR</p>
                        <p class="sp-compliance-card__desc"><?= esc((string) ($nsrHealth['message'] ?? 'Indisponível')) ?></p>
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <a href="<?= site_url('audit/compliance') ?>" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Ver relatório de compliance
                    </a>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card sp-compliance-card">
                <div class="sp-compliance-card__row">
                    <span class="sp-compliance-card__icon <?= $_pisMeta['class'] ?>"><i class="bi <?= $_pisMeta['icon'] ?>"></i></span>
                    <div class="flex-grow-1">
                        <p class="sp-compliance-card__title"><?= $employeesWithoutPis ?> colaborador(es) sem PIS/NIS cadastrado</p>
                        <p class="sp-compliance-card__desc">Obrigatório para o eSocial — colaboradores ativos, administrador já excluído da contagem.</p>
                    </div>
                </div>
                <div class="px-3 pb-3">
                    <a href="<?= site_url('employees') ?>?filter=no_pis" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Ver colaboradores
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════ 5. AUDITORIA ═════════════════════════════════════ -->
<div class="sp-cat sp-cat--auditoria" id="cat-auditoria">
    <?php $catHead('auditoria', 'bi-journal-text', 'Auditoria', 'Alertas do sistema e trilha de atividades administrativas recentes.'); ?>
    <div class="row g-4">
        <div class="col-12 col-xl-6" id="system-alerts-section">
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

        <div class="col-12 col-xl-6">
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
        </div>
    </div>
</div>

<!-- ══════════════════════ 6. MÉTRICAS ADMINISTRATIVAS ═══════════════════════ -->
<div class="sp-cat sp-cat--metricas" id="cat-metricas">
    <?php $catHead('metricas', 'bi-speedometer2', 'Métricas Administrativas', 'Saúde técnica do sistema e indicadores operacionais avançados.'); ?>

    <div class="mb-3" id="health-band">
        <div class="card border-0 shadow-sm overflow-hidden" style="border-left:4px solid <?= $_overallMeta['color'] ?> !important">
            <div class="card-body py-3 px-4">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2 me-2" style="min-width:140px">
                        <span style="width:2.2rem;height:2.2rem;background:<?= $_overallMeta['bg'] ?>;border-radius:.5rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi <?= $_overallMeta['icon'] ?>" style="color:<?= $_overallMeta['color'] ?>;font-size:1rem"></i>
                        </span>
                        <div>
                            <div class="fw-bold" style="font-size:.85rem;color:<?= $_overallMeta['color'] ?>">Sistema <?= esc($_hStatusLabelPt) ?></div>
                            <div class="text-muted" style="font-size:.72rem"><?= $_hOk ?> ok &middot; <?= $_hWarn ?> alerta(s) &middot; <?= $_hErr ?> erro(s)</div>
                        </div>
                    </div>

                    <div class="vr d-none d-md-block" style="height:2rem;opacity:.2"></div>

                    <div class="d-flex flex-wrap gap-2 flex-grow-1">
                        <?php $_msLabelPt = ['ok' => 'OK', 'warning' => 'ATENÇÃO', 'error' => 'ERRO']; ?>
                        <?php foreach ($_moduleLabels as $_mk => $_ml): ?>
                        <?php if (!isset($_hModules[$_mk])) continue; ?>
                        <?php
                        $_ms    = (string)($_hModules[$_mk]['status'] ?? 'error');
                        $_mmeta = $_statusMeta[$_ms] ?? $_statusMeta['error'];
                        $_msPt  = $_msLabelPt[$_ms] ?? 'ERRO';
                        $detail = '';
                        if (!empty($_hModules[$_mk]['details']['response_ms'])) $detail = ' · '.$_hModules[$_mk]['details']['response_ms'].'ms';
                        elseif (!empty($_hModules[$_mk]['details']['free_mb']))  $detail = ' · '.$_hModules[$_mk]['details']['free_mb'].'MB livre';
                        ?>
                        <div class="d-flex align-items-center gap-1 px-2 py-1 rounded-pill"
                             style="background:<?= $_mmeta['bg'] ?>;border:1px solid <?= $_mmeta['color'] ?>30;font-size:.73rem;white-space:nowrap"
                             title="<?= esc($_ml['label']) ?>: <?= esc($_msPt) ?><?= esc($detail) ?>">
                            <i class="bi <?= $_ml['icon'] ?>" style="color:<?= $_mmeta['color'] ?>;font-size:.8rem"></i>
                            <span style="font-weight:600"><?= esc($_ml['label']) ?></span>
                            <span class="ms-1 fw-bold" style="color:<?= $_mmeta['color'] ?>"><?= esc($_msPt) ?></span>
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
                    <?php
                    // buildAlerts() do SystemHealthCheckService gera 'severity' ('critical'/
                    // 'warning'), não 'status' — lendo a chave errada, todo alerta (mesmo
                    // crítico) sempre caía no visual de "atenção" (laranja), nunca no de erro.
                    $_alertVariant = ($_alert['severity'] ?? 'warning') === 'critical' ? 'error' : 'warning';
                    $_am = $_statusMeta[$_alertVariant] ?? $_statusMeta['warning'];
                    ?>
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
</div>

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

        const labels = data.map(d => d.label);
        const counts = data.map(d => d.count);

        if (chartInst) {
            chartInst.data.labels                        = labels;
            chartInst.data.datasets[0].data              = counts;
            chartInst.data.datasets[0].backgroundColor   = colors;
            chartInst.update('none');
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
                    responsive: false,
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
            canvas.width  = wrap.offsetWidth  || 300;
            canvas.height = 150;
        }

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => render(activePeriod));
    } else {
        render(activePeriod);
    }
})();
</script>
<?= $this->endSection() ?>
