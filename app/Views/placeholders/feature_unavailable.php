<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Funcionalidade indisponível em produção<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$title = $title ?? 'Funcionalidade temporariamente indisponível';
$icon = $icon ?? 'bi bi-tools';
$message = $message ?? 'Esta funcionalidade foi retirada da superfície de produção até que a implementação real esteja concluída.';
$safeUrl = $safeUrl ?? site_url('dashboard');
$safeLabel = $safeLabel ?? 'Ir para uma área disponível';
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => $title,
        'subtitle' => 'Acesso despublicado da superfície de produção para evitar uso de telas demonstrativas ou incompletas.',
        'icon' => $icon,
        'actions' => [
            ['label' => $safeLabel, 'icon' => 'bi bi-arrow-right-circle-fill', 'url' => $safeUrl],
            ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => site_url('dashboard')],
        ],
    ]) ?>

    <div class="sp-surface-card">
        <div class="sp-surface-card__body">
            <div class="sp-callout-warning mb-3">
                <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Disponibilidade controlada</strong>
                <div><?= esc($message) ?></div>
            </div>

            <div class="sp-grid-2">
                <div class="sp-surface-card">
                    <div class="sp-surface-card__header">
                        <h2 class="sp-profile-card__title"><i class="bi bi-shield-lock-fill"></i>Por que esta tela foi retirada</h2>
                    </div>
                    <div class="sp-surface-card__body">
                        <ul class="mb-0">
                            <li>Evitar interpretação de dados mockados como dados reais</li>
                            <li>Remover protótipos da navegação de produção</li>
                            <li>Preservar a confiabilidade operacional do sistema</li>
                        </ul>
                    </div>
                </div>

                <div class="sp-surface-card">
                    <div class="sp-surface-card__header">
                        <h2 class="sp-profile-card__title"><i class="bi bi-check2-square"></i>Alternativa segura</h2>
                    </div>
                    <div class="sp-surface-card__body">
                        <p class="mb-3">Use a rota estável abaixo enquanto a implementação final não é liberada.</p>
                        <a href="<?= sp_safe_url($safeUrl) ?>" class="btn btn-primary">
                            <i class="bi bi-arrow-right-circle-fill me-1"></i><?= esc($safeLabel) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
