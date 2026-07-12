<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Central de pendências<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
// Os dados vêm do PendingCenterService com contagens reais do banco.
// Fallback para array vazio — nunca exibir dados fictícios.
$pendingItems = $pendingItems ?? [];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Central de pendências',
        'subtitle' => 'Acompanhe tudo o que exige ação imediata e reduza retrabalho operacional.',
        'icon' => 'bi bi-clipboard-check-fill',
        'actions' => [
            ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => site_url('dashboard')],
            ['label' => 'Ações rápidas', 'icon' => 'bi bi-lightning-charge-fill', 'url' => site_url('operations/action-center')],
        ],
    ]) ?>

    <div class="sp-pending-grid">
        <?php foreach ($pendingItems as $item): ?>
            <a class="sp-pending-card" href="<?= sp_safe_url($item['url']) ?>">
                <div class="sp-pending-card__icon"><i class="<?= sp_attr($item['icon']) ?>"></i></div>
                <div class="sp-pending-card__count"><?= esc($item['count']) ?></div>
                <div class="sp-pending-card__label"><?= esc($item['label']) ?></div>
                <div class="sp-pending-card__desc"><?= esc($item['desc']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
