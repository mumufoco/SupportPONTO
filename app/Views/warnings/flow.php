<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Fluxo disciplinar<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$steps = $steps ?? [
    ['title' => '1. Registro da advertência', 'desc' => 'Criar a ocorrência com fatos objetivos e anexos relevantes.'],
    ['title' => '2. Inclusão de testemunha', 'desc' => 'Cadastrar testemunha quando necessário para reforçar rastreabilidade.'],
    ['title' => '3. Assinatura do colaborador', 'desc' => 'Coletar ciência e assinatura do colaborador.'],
    ['title' => '4. Conclusão do fluxo', 'desc' => 'Finalizar o andamento e preservar histórico completo.'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Fluxo disciplinar',
        'subtitle' => 'Organize o processo de advertências com mais clareza, previsibilidade e rastreabilidade.',
        'icon' => 'bi bi-exclamation-triangle-fill',
        'actions' => [
            ['label' => 'Nova advertência', 'icon' => 'bi bi-plus-circle-fill', 'url' => sp_warning_create_url()],
            ['label' => 'Lista de advertências', 'icon' => 'bi bi-list-ul', 'url' => sp_warning_index_url()],
        ],
    ]) ?>

    <div class="sp-warning-flow-grid">
        <?php foreach ($steps as $step): ?>
            <div class="sp-warning-flow-card">
                <strong><?= esc($step['title']) ?></strong>
                <div class="text-muted"><?= esc($step['desc']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
