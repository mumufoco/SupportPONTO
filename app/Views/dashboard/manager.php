<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc(lang('DashboardManager.title')) ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/pages/dashboard.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$dashboardPresentation = is_array($dashboardPresentation ?? null)               ? $dashboardPresentation             : [];
$pageHeader            = is_array($dashboardPresentation['pageHeader'] ?? null) ? $dashboardPresentation['pageHeader'] : [];
$kpis                  = is_array($dashboardPresentation['kpis'] ?? null)       ? $dashboardPresentation['kpis']     : [];
$sections              = is_array($dashboardPresentation['sections'] ?? null)   ? $dashboardPresentation['sections'] : [];
$pendingSection        = is_array($sections['pendingJustifications'] ?? null)   ? $sections['pendingJustifications'] : [];
$activitySection       = is_array($sections['teamActivity'] ?? null)            ? $sections['teamActivity']          : [];
$quickActions          = is_array($sections['quickActions'] ?? null)            ? $sections['quickActions']          : [];
$alertsSection         = is_array($sections['alerts'] ?? null)                  ? $sections['alerts']                : [];

function mgKpiMod(string $c): string {
    return match($c) { 'success'=>'db-kpi--success','warning'=>'db-kpi--warning','danger'=>'db-kpi--danger','accent'=>'db-kpi--accent','info'=>'db-kpi--info',default=>'' };
}
function mgTrend(string $t): string {
    return 'db-kpi__trend--'.match($t){'positive'=>'positive','negative'=>'negative','success'=>'success','warning'=>'warning','danger'=>'danger',default=>'neutral'};
}
?>

<?= view('components/page_header', [
    'title'    => $pageHeader['title']    ?? lang('DashboardManager.title'),
    'subtitle' => $pageHeader['subtitle'] ?? lang('DashboardManager.subtitle'),
    'icon'     => $pageHeader['icon']     ?? 'bi bi-speedometer2',
]) ?>

<!-- ════════════ KPIs ════════════ -->
<?php if (!empty($kpis)): ?>
<div class="db-kpi-grid mb-4" role="list" aria-label="Indicadores">
    <?php foreach ($kpis as $kpi):
        $mod  = mgKpiMod($kpi['iconColor'] ?? '');
        $tMod = mgTrend($kpi['indicatorType'] ?? 'neutral');
        $tag  = !empty($kpi['url']) ? 'a' : 'div';
        $href = !empty($kpi['url']) ? ' href="'.sp_safe_url($kpi['url']).'"' : '';
    ?>
    <<?= $tag ?><?= $href ?> class="db-kpi sp-anim <?= $mod ?>" role="listitem">
        <div class="db-kpi__icon" aria-hidden="true"><i class="<?= esc($kpi['icon'] ?? 'bi bi-graph-up') ?>"></i></div>
        <div class="db-kpi__body">
            <div class="db-kpi__label"><?= esc($kpi['label'] ?? '') ?></div>
            <div class="db-kpi__value"><?= esc($kpi['value'] ?? '0') ?></div>
            <?php if (!empty($kpi['indicator'])): ?>
                <span class="db-kpi__trend <?= $tMod ?>"><?= esc($kpi['indicator']) ?></span>
            <?php endif; ?>
        </div>
    </<?= $tag ?>>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════ LAYOUT PRINCIPAL ════════════ -->
<div class="row g-4">

    <!-- Coluna principal -->
    <div class="col-12 col-xl-8 d-flex flex-column gap-4">

        <!-- Justificativas pendentes -->
        <section class="card" aria-labelledby="pending-heading">
            <div class="db-card-header">
                <h2 class="db-card-title" id="pending-heading">
                    <i class="bi bi-clipboard-list-fill" aria-hidden="true"></i>
                    <?= esc($pendingSection['title'] ?? lang('DashboardManager.pending.title')) ?>
                </h2>
                <a href="<?= sp_safe_url($pendingSection['actionUrl'] ?? base_url('justifications')) ?>"
                   class="btn btn-sm btn-outline-secondary">
                    <?= esc($pendingSection['actionLabel'] ?? lang('DashboardManager.pending.action')) ?>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($pendingSection['rows'])): ?>
                    <div class="table-responsive">
                        <table class="table table-enhanced mb-0">
                            <thead>
                                <tr>
                                    <?php foreach (($pendingSection['headers'] ?? []) as $h): ?>
                                        <th scope="col"><?= esc($h) ?></th>
                                    <?php endforeach; ?>
                                    <th scope="col"><?= esc(lang('DashboardManager.pending.actionsColumn')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingSection['rows'] as $row): ?>
                                    <tr>
                                        <td><strong><?= esc($row['employeeName'] ?? '') ?></strong></td>
                                        <td><?= esc($row['typeLabel'] ?? '') ?></td>
                                        <td><?= esc($row['dateLabel'] ?? '') ?></td>
                                        <td><?= esc($row['submittedLabel'] ?? '') ?></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="<?= esc($row['approveUrl'] ?? '#') ?>" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                                    <?= esc(lang('DashboardManager.pending.approve')) ?>
                                                </a>
                                                <a href="<?= esc($row['rejectUrl'] ?? '#') ?>" class="btn btn-sm btn-danger">
                                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                                    <?= esc(lang('DashboardManager.pending.reject')) ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="db-empty">
                        <i class="bi bi-check-circle text-success" aria-hidden="true"></i>
                        <p><?= esc($pendingSection['emptyTitle'] ?? lang('DashboardManager.pending.emptyTitle')) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Atividade recente da equipe -->
        <section class="card" aria-labelledby="activity-heading">
            <div class="db-card-header">
                <h2 class="db-card-title" id="activity-heading">
                    <i class="bi bi-activity" aria-hidden="true"></i>
                    <?= esc($activitySection['title'] ?? lang('DashboardManager.activity.title')) ?>
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($activitySection['rows'])): ?>
                    <ul class="db-activity" role="list">
                        <?php foreach ($activitySection['rows'] as $row):
                            $vm = ['success'=>'text-bg-success','danger'=>'text-bg-danger','warning'=>'text-bg-warning','info'=>'text-bg-info'];
                            $bc = $vm[$row['statusVariant'] ?? ''] ?? 'text-bg-secondary';
                            $ini = mb_substr($row['employeeInitial'] ?? '?', 0, 2);
                        ?>
                            <li class="db-activity__item" role="listitem">
                                <div class="db-avatar" aria-hidden="true"><?= esc($ini) ?></div>
                                <div class="db-activity__body">
                                    <div class="db-activity__name"><?= esc($row['employeeName'] ?? '') ?></div>
                                    <div class="db-activity__meta">
                                        <?= esc($row['actionLabel'] ?? '') ?>
                                        <span class="text-muted"> &mdash; <?= esc($row['timeLabel'] ?? '') ?></span>
                                    </div>
                                </div>
                                <span class="badge rounded-pill <?= $bc ?>"><?= esc($row['statusLabel'] ?? '') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="db-empty">
                        <i class="bi bi-inbox" aria-hidden="true"></i>
                        <p><?= esc($activitySection['emptyTitle'] ?? lang('DashboardManager.activity.emptyTitle')) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Coluna lateral -->
    <div class="col-12 col-xl-4 d-flex flex-column gap-4">

        <!-- Acoes rapidas -->
        <section class="card" aria-labelledby="qa-heading">
            <div class="db-card-header">
                <h2 class="db-card-title" id="qa-heading">
                    <i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>
                    <?= esc($quickActions['title'] ?? lang('DashboardManager.quickActions.title')) ?>
                </h2>
            </div>
            <div class="card-body">
                <nav class="db-quick-actions" aria-label="Acoes rapidas">
                    <?php foreach (($quickActions['items'] ?? []) as $action): ?>
                        <a href="<?= sp_safe_url($action['href'] ?? '#') ?>" class="db-quick-action">
                            <i class="<?= esc($action['icon'] ?? 'bi bi-link-45deg') ?>" aria-hidden="true"></i>
                            <?= esc($action['label'] ?? '') ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </section>

        <!-- Alertas -->
        <?php if (!empty($alertsSection['items'])): ?>
        <section class="card" aria-labelledby="alerts-heading">
            <div class="db-card-header">
                <h2 class="db-card-title" id="alerts-heading">
                    <i class="bi bi-bell-fill warn" aria-hidden="true"></i>
                    <?= esc($alertsSection['title'] ?? lang('DashboardManager.alerts.title')) ?>
                </h2>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <?php foreach ($alertsSection['items'] as $alert): ?>
                    <div class="alert alert-<?= esc($alert['type'] ?? 'info') ?> d-flex align-items-start gap-2 mb-0" role="alert">
                        <i class="bi bi-exclamation-circle flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <span><?= esc($alert['message'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
