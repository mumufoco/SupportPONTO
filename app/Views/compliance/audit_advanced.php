<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Auditoria avançada<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $events = $events ?? []; ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Auditoria avançada',
        'subtitle' => 'Acompanhe eventos críticos, mudanças sensíveis e rastros administrativos importantes.',
        'icon' => 'bi bi-shield-lock-fill',
        'actions' => [
            ['label' => 'Auditoria padrão', 'icon' => 'bi bi-list-ul', 'url' => site_url('audit')],
            ['label' => 'Saúde do sistema', 'icon' => 'bi bi-heart-pulse-fill', 'url' => site_url('admin/health')],
        ],
    ]) ?>

    <div class="sp-risk-banner">
        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Foco da auditoria avançada</strong>
        <div>Esta área deve concentrar eventos de maior impacto operacional, administrativo e de segurança.</div>
    </div>

    <div class="sp-compliance-checklist">
        <?php foreach ($events as $event): ?>
            <div class="sp-compliance-item">
                <strong><?= esc($event['title']) ?></strong>
                <div><?= esc($event['desc']) ?></div>
                <div class="sp-form-help mt-2"><?= esc($event['meta']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
