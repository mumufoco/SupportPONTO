<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$dashboardPresentation = is_array($dashboardPresentation ?? null) ? $dashboardPresentation : [];
$pageHeader           = is_array($dashboardPresentation['pageHeader'] ?? null)                 ? $dashboardPresentation['pageHeader']   : [];
$kpis                 = is_array($dashboardPresentation['kpis'] ?? null)                       ? $dashboardPresentation['kpis']         : [];
$sections             = is_array($dashboardPresentation['sections'] ?? null)                   ? $dashboardPresentation['sections']     : [];
$shortcutsSection     = is_array($sections['shortcuts'] ?? null)                               ? $sections['shortcuts']                 : [];
$shortcuts            = is_array($shortcutsSection['items'] ?? null)                           ? $shortcutsSection['items']             : [];
$notificationsSection = is_array($sections['notifications'] ?? null)                           ? $sections['notifications']             : [];
$notifications        = is_array($notificationsSection['items'] ?? null)                       ? $notificationsSection['items']         : [];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => $pageHeader['title']    ?? 'Painel DPO',
        'subtitle' => $pageHeader['subtitle'] ?? 'Auditoria e conformidade LGPD.',
        'icon'     => $pageHeader['icon']     ?? 'bi bi-shield-lock-fill',
        'actions'  => $pageHeader['actions']  ?? [],
    ]) ?>

    <!-- KPIs -->
    <?php if (!empty($kpis)): ?>
    <div class="sp-kpi-grid mb-4">
        <?php foreach ($kpis as $kpi): ?>
            <?= view('components/kpi', $kpi) ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Atalhos rápidos -->
    <?php if (!empty($shortcuts)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-grid-fill me-1"></i>
                <?= esc($shortcutsSection['title'] ?? 'Acessos rápidos') ?>
            </h5>
        </div>
        <div class="card-body">
            <div class="sp-shortcuts-grid">
                <?php foreach ($shortcuts as $shortcut): ?>
                    <a class="sp-shortcut-card" href="<?= sp_safe_url($shortcut['href'] ?? '#') ?>">
                        <div class="icon"><i class="<?= esc($shortcut['icon'] ?? 'bi bi-link-45deg') ?>"></i></div>
                        <strong><?= esc($shortcut['title'] ?? '') ?></strong>
                        <span><?= esc($shortcut['description'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alertas recentes -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-bell me-1"></i>
                <?= esc($notificationsSection['title'] ?? 'Alertas recentes') ?>
            </h5>
        </div>
        <div class="card-body">
            <?php if (!empty($notifications)): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $n): ?>
                        <div class="border rounded p-3">
                            <strong><?= esc($n['title'] ?? 'Notificação') ?></strong>
                            <p class="mb-1 text-muted small"><?= esc($n['message'] ?? '') ?></p>
                            <?php if (!empty($n['createdAt'])): ?>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i><?= esc($n['createdAt']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bi bi-check-circle fs-3 d-block mb-2"></i>
                    Sem alertas recentes.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
