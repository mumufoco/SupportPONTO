<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Central de ações rápidas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$actions = $actions ?? [
    ['label' => 'Novo colaborador', 'description' => 'Cadastrar colaborador rapidamente.', 'icon' => 'bi bi-person-plus-fill', 'url' => site_url('employees/create')],
    ['label' => 'Bater ponto', 'description' => 'Registrar marcação agora.', 'icon' => 'bi bi-clock-fill', 'url' => sp_timesheet_punch_url()],
    ['label' => 'Nova justificativa', 'description' => 'Criar solicitação de ajuste.', 'icon' => 'bi bi-file-earmark-plus-fill', 'url' => site_url('justifications/create')],
    ['label' => 'Nova advertência', 'description' => 'Registrar ocorrência disciplinar.', 'icon' => 'bi bi-exclamation-triangle-fill', 'url' => sp_warning_create_url()],
    ['label' => 'Relatórios', 'description' => 'Abrir relatórios gerenciais.', 'icon' => 'bi bi-bar-chart-fill', 'url' => sp_reports_index_url()],
    ['label' => 'Configurações', 'description' => 'Abrir painel de configurações.', 'icon' => 'bi bi-gear-fill', 'url' => sp_admin_settings_index_url()],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Central de ações rápidas',
        'subtitle' => 'Execute as tarefas mais recorrentes com o menor número possível de cliques.',
        'icon' => 'bi bi-lightning-charge-fill',
        'actions' => [
            ['label' => 'Pendências', 'icon' => 'bi bi-clipboard-check-fill', 'url' => site_url('operations/pending-center')],
        ],
    ]) ?>

    <div class="sp-action-center">
        <?php foreach ($actions as $action): ?>
            <a class="sp-shortcut-card" href="<?= sp_safe_url($action['url']) ?>">
                <div class="icon"><i class="<?= esc($action['icon']) ?>"></i></div>
                <strong><?= esc($action['label']) ?></strong>
                <span><?= esc($action['description']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
