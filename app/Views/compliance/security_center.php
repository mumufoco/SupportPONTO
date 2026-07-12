<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Centro de segurança<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$checks = $checks ?? [];

// Mapa de status → classes e ícones visuais
$statusMap = [
    'ok'      => ['icon' => 'bi bi-check-circle-fill',    'class' => 'text-success', 'label' => 'OK'],
    'aviso'   => ['icon' => 'bi bi-exclamation-circle-fill','class' => 'text-warning', 'label' => 'Atenção'],
    'critico' => ['icon' => 'bi bi-x-circle-fill',         'class' => 'text-danger',  'label' => 'Crítico'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Centro de segurança',
        'subtitle' => 'Visão dos controles críticos do ambiente com status em tempo real.',
        'icon'     => 'bi bi-shield-fill-check',
        'actions'  => [
            ['label' => 'Matriz de permissões', 'icon' => 'bi bi-key-fill',       'url' => site_url('compliance/permissions-matrix')],
            ['label' => 'Auditoria avançada',   'icon' => 'bi bi-shield-lock-fill','url' => site_url('compliance/audit-advanced')],
        ],
    ]) ?>

    <div class="sp-compliance-checklist">
        <?php foreach ($checks as $item):
            $s = $statusMap[$item['status'] ?? 'aviso'] ?? $statusMap['aviso'];
        ?>
            <div class="sp-compliance-item d-flex align-items-start gap-3">
                <i class="<?= esc($s['icon']) ?> <?= esc($s['class']) ?> fs-5 mt-1 flex-shrink-0"></i>
                <div>
                    <strong><?= esc($item['title']) ?></strong>
                    <span class="badge ms-2 <?= $s['class'] === 'text-success' ? 'bg-success' : ($s['class'] === 'text-danger' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= esc($s['label']) ?></span>
                    <div class="text-muted small mt-1"><?= esc($item['desc']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>

