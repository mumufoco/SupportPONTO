<?php
$title = $title ?? 'Seção';
$icon = $icon ?? null;
$subtitle = $subtitle ?? null;
$slot = $slot ?? '';
?>
<div class="sp-surface-card">
    <div class="sp-surface-card__header">
        <h2 class="sp-profile-card__title">
            <?php if ($icon): ?><i class="<?= esc($icon) ?>"></i><?php endif; ?>
            <span><?= esc($title) ?></span>
        </h2>
        <?php if ($subtitle): ?><div class="sp-form-help mt-1"><?= esc($subtitle) ?></div><?php endif; ?>
    </div>
    <div class="sp-surface-card__body">
        <?= $slot ?>
    </div>
</div>
