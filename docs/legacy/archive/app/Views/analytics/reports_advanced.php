<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Relatórios avançados<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$reports = $reports ?? [
    ['title' => 'Atrasos por setor', 'desc' => 'Comparativo entre departamentos e períodos.'],
    ['title' => 'Uso por método de marcação', 'desc' => 'Distribuição entre facial, biometria, CPF, código e QR.'],
    ['title' => 'Banco de horas por equipe', 'desc' => 'Consolidação de saldos por unidade e grupo.'],
    ['title' => 'Ocorrências disciplinares', 'desc' => 'Visão de advertências por período e área.'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Relatórios avançados',
        'subtitle' => 'Amplie a visão gerencial com recortes táticos e comparativos por período, equipe e método.',
        'icon' => 'bi bi-bar-chart-fill',
        'actions' => [
            ['label' => 'Analytics', 'icon' => 'bi bi-graph-up-arrow', 'url' => site_url('analytics/management')],
            ['label' => 'Métricas por método', 'icon' => 'bi bi-fingerprint', 'url' => site_url('analytics/method-metrics')],
        ],
    ]) ?>

    <div class="sp-insight-list">
        <?php foreach ($reports as $item): ?>
            <div class="sp-insight-item">
                <strong><?= esc($item['title']) ?></strong>
                <div class="text-muted mt-1"><?= esc($item['desc']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
