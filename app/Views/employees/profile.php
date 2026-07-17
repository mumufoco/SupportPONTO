<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Perfil do colaborador<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $emp = $employee ?? null; ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Perfil do colaborador',
        'subtitle' => 'Consulte dados principais, indicadores e atalhos de gestão do colaborador selecionado.',
        'icon' => 'bi bi-person-vcard-fill',
        'actions' => [
            ['label' => 'Editar cadastro', 'icon' => 'bi bi-pencil-square', 'url' => site_url('employees/' . ($emp->id ?? 0) . '/edit')],
            ['label' => 'Advertências', 'icon' => 'bi bi-exclamation-triangle-fill', 'url' => sp_warning_dashboard_url((int) ($emp->id ?? 0))],
            ['label' => 'Voltar para colaboradores', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('employees')],
        ],
    ]) ?>

    <div class="sp-profile-grid">
        <div class="span-4">
            <div class="sp-profile-card">
                <div class="sp-profile-card__body sp-avatar-panel">
                    <div class="sp-avatar-placeholder"><i class="bi bi-person-badge-fill"></i></div>
                    <h3 class="mb-1"><?= esc($emp->name ?? '-') ?></h3>
                    <p class="text-muted mb-3"><?= esc($emp->email ?? '-') ?></p>
                    <div class="sp-meta-list text-start">
                        <div class="sp-meta-item"><small>Cargo</small><strong><?= esc($emp->position ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Departamento</small><strong><?= esc($emp->department ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Status</small><strong><?= !empty($emp->active) ? 'Ativo' : 'Inativo' ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="span-8">
            <div class="sp-shortcuts-grid">
                <a class="sp-shortcut-card" href="<?= site_url('employees/' . ($emp->id ?? 0) . '/edit') ?>">
                    <div class="icon"><i class="bi bi-pencil-square"></i></div>
                    <strong>Editar colaborador</strong>
                    <span>Atualize vínculos, dados e parâmetros operacionais.</span>
                </a>
                <a class="sp-shortcut-card" href="<?= sp_warning_dashboard_url((int) ($emp->id ?? 0)) ?>">
                    <div class="icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <strong>Histórico disciplinar</strong>
                    <span>Acompanhe ocorrências e advertências do colaborador.</span>
                </a>
                <a class="sp-shortcut-card" href="<?= site_url('minha-biometria') ?>">
                    <div class="icon"><i class="bi bi-fingerprint"></i></div>
                    <strong>Biometria</strong>
                    <span>Gerencie status facial e digital do colaborador.</span>
                </a>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
