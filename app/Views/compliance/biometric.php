<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Biometria e consentimento<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('biometric/partials/_biometric_compliance_bridge') ?>
<?php
$summary = $summary ?? [];
$cards = $cards ?? [];
$guidelines = $guidelines ?? [];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Biometria e consentimento',
        'subtitle' => 'Consolide o relacionamento entre biometria, perfil do colaborador, consentimento e trilha de conformidade.',
        'icon' => 'bi bi-fingerprint',
        'actions' => [
            ['label' => 'LGPD', 'icon' => 'bi bi-person-lock', 'url' => site_url('compliance/lgpd')],
            ['label' => 'Perfil', 'icon' => 'bi bi-person-vcard-fill', 'url' => site_url('profile')],
        ],
    ]) ?>

    <div class="sp-callout-warning">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc($summary['label'] ?? 'Consentimento biométrico') ?></strong>
        <div><?= esc($summary['description'] ?? 'Revise o consentimento antes da ativação biométrica.') ?></div>
    </div>

    <div class="sp-lgpd-grid">
        <?php foreach ($cards as $card): ?>
            <div class="sp-lgpd-card">
                <strong><?= esc($card['value']) ?></strong>
                <span><?= esc($card['label']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="sp-surface-card">
        <div class="sp-surface-card__header">
            <h2 class="sp-profile-card__title"><i class="bi bi-check2-square"></i>Diretrizes estruturais</h2>
        </div>
        <div class="sp-surface-card__body">
            <div class="sp-compliance-checklist">
                <?php foreach ($guidelines as $item): ?>
                    <div class="sp-compliance-item">
                        <strong><?= esc($item['title']) ?></strong>
                        <div><?= esc($item['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
