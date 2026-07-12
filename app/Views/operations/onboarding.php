<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Guia rápido do sistema<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$steps = $steps ?? [
    ['title' => '1. Bater ponto', 'text' => 'Use o módulo de ponto para entrada, saída e intervalos.'],
    ['title' => '2. Consultar espelho', 'text' => 'Abra o espelho para verificar seus registros e o saldo do período.'],
    ['title' => '3. Enviar justificativa', 'text' => 'Sempre que houver inconsistência, crie uma justificativa com detalhes claros.'],
    ['title' => '4. Configurar biometria', 'text' => 'Complete o cadastro biométrico para agilizar autenticação e registros.'],
    ['title' => '5. Acompanhar pendências', 'text' => 'Verifique a central de pendências e as notificações para não perder ações importantes.'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Guia rápido do sistema',
        'subtitle' => 'Entenda o fluxo principal do SupportPONTO e comece a operar com clareza.',
        'icon' => 'bi bi-signpost-split-fill',
        'actions' => [
            ['label' => 'Ações rápidas', 'icon' => 'bi bi-lightning-charge-fill', 'url' => site_url('operations/action-center')],
            ['label' => 'Ponto', 'icon' => 'bi bi-clock-fill', 'url' => sp_timesheet_punch_url()],
        ],
    ]) ?>

    <div class="sp-onboarding-steps">
        <?php foreach ($steps as $step): ?>
            <div class="sp-onboarding-step">
                <strong><?= esc($step['title']) ?></strong>
                <div class="text-muted mt-1"><?= esc($step['text']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
