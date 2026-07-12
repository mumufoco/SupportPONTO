<?php
$title = $title ?? lang('DashboardCommon.pageHeader.defaultTitle');
$subtitle = $subtitle ?? '';
$icon = $icon ?? 'bi bi-grid';
$actions = $actions ?? [];
?>

<section class="sp-page-header">
    <div class="sp-page-header__content">
        <div class="sp-page-header__eyebrow">
            <i class="<?= esc($icon) ?>"></i>
            <span><?= esc($title) ?></span>
        </div>
        <h1 class="sp-page-header__title"><?= esc($title) ?></h1>
        <?php if ($subtitle !== ''): ?>
            <p class="sp-page-header__subtitle"><?= esc($subtitle) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($actions)): ?>
        <div class="sp-page-header__actions">
            <?php foreach ($actions as $action): ?>
                <a class="sp-page-chip" href="<?= sp_safe_url($action['url'] ?? '#') ?>" <?= !empty($action['attrs']) ? $action['attrs'] : '' ?>>
                    <?php if (!empty($action['icon'])): ?><i class="<?= esc($action['icon']) ?>"></i><?php endif; ?>
                    <span><?= esc($action['label'] ?? lang('DashboardCommon.pageHeader.defaultAction')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
