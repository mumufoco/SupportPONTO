<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Indicadores por equipe<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$teams = $teams ?? [
    ['name' => 'Equipe Administrativa', 'metric' => '96%', 'desc' => 'Presença média no período.'],
    ['name' => 'Equipe Operacional', 'metric' => '89%', 'desc' => 'Presença média no período.'],
    ['name' => 'Equipe Técnica', 'metric' => '92%', 'desc' => 'Presença média no período.'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Indicadores por equipe',
        'subtitle' => 'Analise desempenho operacional por grupo, equipe e unidade de trabalho.',
        'icon' => 'bi bi-people-fill',
        'actions' => [
            ['label' => 'Analytics', 'icon' => 'bi bi-graph-up-arrow', 'url' => site_url('analytics/management')],
        ],
    ]) ?>

    <div class="sp-insight-list">
        <?php foreach ($teams as $team): ?>
            <div class="sp-insight-item">
                <strong><?= esc($team['name']) ?> — <?= esc($team['metric']) ?></strong>
                <div class="text-muted mt-1"><?= esc($team['desc']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
