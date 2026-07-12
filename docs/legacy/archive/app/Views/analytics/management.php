<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Analytics gerenciais<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$cards = $cards ?? [
    ['value' => '7%', 'label' => 'Atrasos no período'],
    ['value' => '3%', 'label' => 'Absenteísmo estimado'],
    ['value' => '12', 'label' => 'Justificativas abertas'],
    ['value' => '4', 'label' => 'Advertências ativas'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Analytics gerenciais',
        'subtitle' => 'Visualize indicadores consolidados para apoiar decisões operacionais, táticas e de RH.',
        'icon' => 'bi bi-graph-up-arrow',
        'actions' => [
            ['label' => 'Relatórios avançados', 'icon' => 'bi bi-bar-chart-fill', 'url' => site_url('analytics/reports-advanced')],
            ['label' => 'Indicadores por equipe', 'icon' => 'bi bi-people-fill', 'url' => site_url('analytics/team-indicators')],
        ],
    ]) ?>

    <div class="sp-analytics-grid">
        <?php foreach ($cards as $card): ?>
            <div class="sp-analytics-card">
                <strong><?= esc($card['value']) ?></strong>
                <span><?= esc($card['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
