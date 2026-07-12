<?php
$variant = $variant ?? 'info';
$label = $label ?? 'Status';
$icon = $icon ?? null;
$map = [
    'active' => 'is-active',
    'success' => 'is-success',
    'warning' => 'is-warning',
    'pending' => 'is-pending',
    'danger' => 'is-danger',
    'error' => 'is-error',
    'inactive' => 'is-inactive',
    'info' => 'is-info',
];
$cls = $map[$variant] ?? 'is-info';
?>
<span class="sp-status-pill <?= esc($cls) ?>">
    <?php if ($icon): ?><i class="<?= esc($icon) ?>"></i><?php endif; ?>
    <span><?= esc($label) ?></span>
</span>
