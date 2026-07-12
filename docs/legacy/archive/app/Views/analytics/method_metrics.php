<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Métricas por método<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$methods = $methods ?? [
    ['value' => '34%', 'label' => 'Reconhecimento facial'],
    ['value' => '28%', 'label' => 'Biometria digital'],
    ['value' => '16%', 'label' => 'CPF'],
    ['value' => '12%', 'label' => 'Código único'],
    ['value' => '10%', 'label' => 'QR Code'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Métricas por método',
        'subtitle' => 'Visualize o uso relativo de cada método de marcação e identifique oportunidades de padronização.',
        'icon' => 'bi bi-fingerprint',
        'actions' => [
            ['label' => 'Relatórios avançados', 'icon' => 'bi bi-bar-chart-fill', 'url' => site_url('analytics/reports-advanced')],
        ],
    ]) ?>

    <div class="sp-method-metrics">
        <?php foreach ($methods as $item): ?>
            <div class="sp-method-metric">
                <strong><?= esc($item['value']) ?></strong>
                <span><?= esc($item['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
