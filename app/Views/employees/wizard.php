<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cadastro guiado de colaborador<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$steps = $steps ?? [
    ['title' => 'Dados pessoais', 'desc' => 'Nome, email, CPF e contato.'],
    ['title' => 'Dados profissionais', 'desc' => 'Departamento, cargo e vínculo.'],
    ['title' => 'Parâmetros operacionais', 'desc' => 'Código interno, jornada e unidade.'],
    ['title' => 'Acesso e biometria', 'desc' => 'Perfil, consentimento e meios de autenticação.'],
    ['title' => 'Revisão final', 'desc' => 'Conferência antes de concluir o cadastro.'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Cadastro guiado de colaborador',
        'subtitle' => 'Fluxo orientado para reduzir erros e tornar o onboarding mais claro para RH e gestores.',
        'icon' => 'bi bi-diagram-3-fill',
        'actions' => [
            ['label' => 'Cadastro tradicional', 'icon' => 'bi bi-person-plus-fill', 'url' => site_url('employees/create')],
            ['label' => 'Listagem', 'icon' => 'bi bi-list-ul', 'url' => site_url('employees')],
        ],
    ]) ?>

    <div class="sp-wizard-shell">
        <div class="sp-wizard-steps">
            <?php foreach ($steps as $index => $step): ?>
                <div class="sp-wizard-step <?= $index === 0 ? 'is-active' : '' ?>">
                    <strong><?= esc(($index + 1) . '. ' . $step['title']) ?></strong>
                    <span><?= esc($step['desc']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="sp-surface-card">
            <div class="sp-surface-card__header">
                <h2 class="sp-profile-card__title"><i class="bi bi-person-vcard-fill"></i>Pré-estrutura do fluxo guiado</h2>
            </div>
            <div class="sp-surface-card__body">
                <div class="sp-callout-info mb-3">
                    <strong><i class="bi bi-info-circle-fill me-2"></i>Objetivo</strong>
                    <div>Esta etapa introduz a estrutura do cadastro guiado para que a equipe possa evoluir o fluxo sem quebrar o cadastro tradicional já existente.</div>
                </div>

                <div class="sp-grid-2">
                    <div class="sp-surface-card">
                        <div class="sp-surface-card__header">
                            <h3 class="sp-profile-card__title"><i class="bi bi-check2-square"></i>Benefícios</h3>
                        </div>
                        <div class="sp-surface-card__body">
                            <ul class="mb-0">
                                <li>Menos campos esquecidos</li>
                                <li>Fluxo mais claro para RH</li>
                                <li>Onboarding mais rápido</li>
                                <li>Menos retrabalho no cadastro</li>
                            </ul>
                        </div>
                    </div>
                    <div class="sp-surface-card">
                        <div class="sp-surface-card__header">
                            <h3 class="sp-profile-card__title"><i class="bi bi-list-task"></i>Próxima evolução técnica</h3>
                        </div>
                        <div class="sp-surface-card__body">
                            <ul class="mb-0">
                                <li>Persistência por etapa</li>
                                <li>Validação progressiva</li>
                                <li>Resumo consolidado</li>
                                <li>Conclusão com revisão final</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="sp-actions-bar mt-3">
                    <div class="sp-actions-bar__group">
                        <a href="<?= site_url('employees/create') ?>" class="btn btn-primary">
                            <i class="bi bi-person-plus-fill me-1"></i>Usar cadastro atual
                        </a>
                        <a href="<?= site_url('employees') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left-circle me-1"></i>Voltar para colaboradores
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
