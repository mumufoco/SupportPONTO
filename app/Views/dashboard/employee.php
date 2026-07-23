<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc(lang('DashboardEmployee.title')) ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/pages/dashboard.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$dashboardPresentation = is_array($dashboardPresentation ?? null) ? $dashboardPresentation : [];
$pageHeader      = is_array($dashboardPresentation['pageHeader'] ?? null)       ? $dashboardPresentation['pageHeader']   : [];
$hero            = is_array($dashboardPresentation['hero'] ?? null)             ? $dashboardPresentation['hero']         : [];
$kpis            = is_array($dashboardPresentation['kpis'] ?? null)             ? $dashboardPresentation['kpis']         : [];
$sections        = is_array($dashboardPresentation['sections'] ?? null)         ? $dashboardPresentation['sections']     : [];
$shortcutsSection= is_array($sections['shortcuts'] ?? null)                     ? $sections['shortcuts']                 : [];
$shortcuts       = is_array($shortcutsSection['items'] ?? null)                 ? $shortcutsSection['items']             : [];

/* Mapeia iconColor do presenter para modificador CSS */
function dbKpiMod(string $color): string {
    return match($color) {
        'success' => 'db-kpi--success',
        'warning' => 'db-kpi--warning',
        'danger'  => 'db-kpi--danger',
        'accent'  => 'db-kpi--accent',
        'info'    => 'db-kpi--info',
        default   => '',
    };
}

function dbTrendMod(string $type): string {
    return 'db-kpi__trend--' . match($type) {
        'positive' => 'positive',
        'negative' => 'negative',
        'success'  => 'success',
        'warning'  => 'warning',
        'danger'   => 'danger',
        default    => 'neutral',
    };
}
?>

<?= view('components/page_header', [
    'title'    => $pageHeader['title']    ?? lang('DashboardEmployee.title'),
    'subtitle' => $pageHeader['subtitle'] ?? lang('DashboardEmployee.subtitle'),
    'icon'     => $pageHeader['icon']     ?? lang('DashboardEmployee.icon'),
    'actions'  => $pageHeader['actions']  ?? [],
]) ?>

<!-- ════════════ HERO ════════════ -->
<section class="db-hero" aria-label="Status do dia">
    <div class="db-hero__inner">
        <div>
            <h2 class="db-hero__title"><?= esc($hero['title'] ?? '') ?></h2>
            <p class="db-hero__sub">
                <?= esc(lang('DashboardEmployee.hero.statusLabel')) ?>
                <span class="db-hero__badge <?= (($hero['statusBadgeClass'] ?? '') === 'text-bg-success') ? 'is-in' : 'is-out' ?>">
                    <?= esc($hero['statusText'] ?? '') ?>
                </span>
            </p>
        </div>
        <a href="<?= sp_safe_url($hero['ctaUrl'] ?? sp_timesheet_punch_url()) ?>"
           class="db-hero__cta"
           aria-label="<?= esc($hero['ctaLabel'] ?? lang('DashboardEmployee.hero.cta')) ?>">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <?= esc($hero['ctaLabel'] ?? lang('DashboardEmployee.hero.cta')) ?>
        </a>
    </div>
</section>

<!-- ════════════ KPIs ════════════ -->
<?php if (!empty($kpis)): ?>
<div class="db-section-label"><i class="bi bi-speedometer2"></i>Meus indicadores &mdash; Ponto &amp; Pendências</div>
<div class="db-kpi-grid" role="list" aria-label="Indicadores de desempenho">
    <?php foreach ($kpis as $kpi):
        $mod      = dbKpiMod($kpi['iconColor'] ?? '');
        $trendMod = dbTrendMod($kpi['indicatorType'] ?? 'neutral');
        $tag      = !empty($kpi['url']) ? 'a' : 'div';
        $href     = !empty($kpi['url']) ? ' href="' . sp_safe_url($kpi['url']) . '"' : '';
    ?>
    <<?= $tag ?><?= $href ?> class="db-kpi sp-anim <?= $mod ?>" role="listitem"
        <?= $tag === 'a' ? 'aria-label="' . esc($kpi['label'] ?? '') . ': ' . esc($kpi['value'] ?? '') . '"' : '' ?>>
        <div class="db-kpi__icon" aria-hidden="true">
            <i class="<?= esc($kpi['icon'] ?? 'bi bi-graph-up') ?>"></i>
        </div>
        <div class="db-kpi__body">
            <div class="db-kpi__label"><?= esc($kpi['label'] ?? '') ?></div>
            <div class="db-kpi__value"><?= esc($kpi['value'] ?? '0') ?></div>
            <?php if (!empty($kpi['indicator'])): ?>
                <span class="db-kpi__trend <?= $trendMod ?>" aria-label="Tendencia: <?= esc($kpi['indicator']) ?>">
                    <?= esc($kpi['indicator']) ?>
                </span>
            <?php endif; ?>
        </div>
    </<?= $tag ?>>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ════════════ ATALHOS ════════════ -->
<?php if (!empty($shortcuts)): ?>
<div class="db-section-label"><i class="bi bi-grid-fill"></i>Métricas administrativas &mdash; Acessos rápidos</div>
<section class="card" aria-labelledby="shortcuts-heading">
    <div class="db-card-header">
        <h2 class="db-card-title" id="shortcuts-heading">
            <i class="bi bi-grid-fill" aria-hidden="true"></i>
            <?= esc($shortcutsSection['title'] ?? lang('DashboardEmployee.shortcuts.sectionTitle')) ?>
        </h2>
    </div>
    <div class="card-body">
        <nav class="db-shortcuts" aria-label="Atalhos rapidos">
            <?php foreach ($shortcuts as $shortcut): ?>
                <a class="db-shortcut sp-anim"
                   href="<?= sp_safe_url($shortcut['href'] ?? '#') ?>"
                   aria-label="<?= esc($shortcut['title'] ?? '') ?>">
                    <div class="db-shortcut__icon" aria-hidden="true">
                        <i class="<?= esc($shortcut['icon'] ?? 'bi bi-link-45deg') ?>"></i>
                    </div>
                    <span class="db-shortcut__title"><?= esc($shortcut['title'] ?? '') ?></span>
                    <?php if (!empty($shortcut['description'])): ?>
                        <span class="db-shortcut__desc"><?= esc($shortcut['description']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</section>
<?php endif; ?>

<?= $this->endSection() ?>
