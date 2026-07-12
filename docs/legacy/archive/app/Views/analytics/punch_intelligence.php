<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Inteligência do ponto<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$insights = $insights ?? [
    ['title' => 'Saída ausente detectada', 'desc' => 'Foram identificados registros sem saída em colaboradores ativos.'],
    ['title' => 'Sequência inconsistente', 'desc' => 'Há marcações com ordem não esperada entre entrada e intervalo.'],
    ['title' => 'Duplicidade de marcação', 'desc' => 'Alguns registros ocorreram em janela de tempo muito próxima.'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Inteligência do ponto',
        'subtitle' => 'Acompanhe sugestões, padrões anormais e sinais operacionais que ajudam a reduzir erros na jornada.',
        'icon' => 'bi bi-cpu-fill',
        'actions' => [
            ['label' => 'Histórico', 'icon' => 'bi bi-clock-history', 'url' => site_url('timesheet/history')],
            ['label' => 'Analytics', 'icon' => 'bi bi-graph-up-arrow', 'url' => site_url('analytics/management')],
        ],
    ]) ?>

    <div class="sp-insight-list">
        <?php foreach ($insights as $item): ?>
            <div class="sp-insight-item">
                <strong><?= esc($item['title']) ?></strong>
                <div class="text-muted mt-1"><?= esc($item['desc']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
