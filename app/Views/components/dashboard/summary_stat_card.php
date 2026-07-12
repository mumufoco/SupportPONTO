<?php
$label = (string) ($label ?? $title ?? 'Indicador');
$value = (string) ($value ?? '0');
$icon = (string) ($icon ?? 'bi bi-graph-up');
$variant = preg_replace('/[^a-z0-9_-]/i', '', (string) ($variant ?? 'primary')) ?: 'primary';
$columnClass = trim((string) ($columnClass ?? 'col-md-3')) ?: 'col-md-3';
$description = trim((string) ($description ?? ''));
?>
<div class="<?= sp_attr($columnClass) ?>">
    <div class="card border-0 shadow-sm h-100 sp-summary-stat-card">
        <div class="card-body d-flex align-items-center gap-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-<?= sp_attr($variant) ?> bg-opacity-10 text-<?= sp_attr($variant) ?> sp-circle-icon-3">
                <i class="<?= sp_attr($icon) ?>"></i>
            </span>
            <div class="min-w-0">
                <div class="text-muted small text-uppercase fw-semibold"><?= esc($label) ?></div>
                <div class="fs-3 fw-bold lh-1"><?= esc($value) ?></div>
                <?php if ($description !== ''): ?>
                    <div class="text-muted small mt-1"><?= esc($description) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
