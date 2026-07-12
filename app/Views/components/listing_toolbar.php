<?php
$title = $title ?? lang('AppUi.listingToolbar.defaultTitle');
$description = $description ?? null;
$slot = $slot ?? '';
?>
<div class="sp-filter-toolbar">
    <?php if ($title): ?><strong class="d-block mb-1"><?= esc($title) ?></strong><?php endif; ?>
    <?php if ($description): ?><div class="sp-form-help mb-3"><?= esc($description) ?></div><?php endif; ?>
    <?= $slot ?>
</div>
