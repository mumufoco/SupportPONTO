<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Histórico do colaborador<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$history = $history ?? [
    ['title' => 'Alteração de cargo', 'desc' => 'Mudança de Analista para Coordenador.', 'meta' => 'RH • 2026-03-21 09:10'],
    ['title' => 'Atualização de jornada', 'desc' => 'Jornada alterada para escala administrativa.', 'meta' => 'Admin • 2026-03-19 14:05'],
    ['title' => 'Biometria facial cadastrada', 'desc' => 'Cadastro facial concluído com sucesso.', 'meta' => 'Sistema • 2026-03-18 10:25'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Histórico do colaborador',
        'subtitle' => 'Acompanhe mudanças de cargo, jornada, biometria, status e outras alterações sensíveis.',
        'icon' => 'bi bi-clock-history',
        'actions' => [
            ['label' => 'Perfil do colaborador', 'icon' => 'bi bi-person-vcard-fill', 'url' => site_url('employees')],
            ['label' => 'Colaboradores', 'icon' => 'bi bi-people-fill', 'url' => site_url('employees')],
        ],
    ]) ?>

    <div class="sp-history-block">
        <?php foreach ($history as $item): ?>
            <div class="sp-history-item">
                <strong><?= esc($item['title']) ?></strong>
                <div><?= esc($item['desc']) ?></div>
                <div class="sp-history-item__meta"><?= esc($item['meta']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
