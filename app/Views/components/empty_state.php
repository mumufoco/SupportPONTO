<?php
$icon = $icon ?? 'bi bi-inbox-fill';
$title = $title ?? 'Nada encontrado';
$description = $description ?? 'Não há informações para exibir neste momento.';
$action = $action ?? null;
?>
<div class="sp-empty-box">
    <div class="mb-2"><i class="<?= esc($icon) ?> fs-2"></i></div>
    <strong class="d-block mb-1"><?= esc($title) ?></strong>
    <div class="mb-3"><?= esc($description) ?></div>
    <?php if (!empty($action['url'] ?? null) && !empty($action['label'] ?? null)): ?>
        <a href="<?= sp_safe_url($action['url']) ?>" class="btn btn-outline-primary">
            <?php if (!empty($action['icon'])): ?><i class="<?= esc($action['icon']) ?> me-1"></i><?php endif; ?>
            <?= esc($action['label']) ?>
        </a>
    <?php endif; ?>
</div>
