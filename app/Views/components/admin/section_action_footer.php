<?php
$title = trim((string) ($title ?? ''));
$description = trim((string) ($description ?? ''));
$metaItems = is_array($metaItems ?? null) ? $metaItems : [];
$primaryAction = is_array($primaryAction ?? null) ? $primaryAction : [];
$secondaryAction = is_array($secondaryAction ?? null) ? $secondaryAction : [];
$actions = array_filter([$secondaryAction, $primaryAction], static fn ($action) => is_array($action) && trim((string) ($action['label'] ?? '')) !== '');
?>
<?php if ($title !== '' || $description !== '' || $metaItems !== [] || $actions !== []): ?>
    <div class="border-top pt-3 mt-3 d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3 sp-section-action-footer">
        <div>
            <?php if ($title !== ''): ?>
                <div class="fw-semibold"><?= esc($title) ?></div>
            <?php endif; ?>
            <?php if ($description !== ''): ?>
                <div class="text-muted small"><?= esc($description) ?></div>
            <?php endif; ?>
            <?php if ($metaItems !== []): ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php foreach ($metaItems as $item): ?>
                        <?php
                        $item = is_array($item) ? $item : [];
                        $itemVariant = preg_replace('/[^a-z0-9_-]/i', '', (string) ($item['variant'] ?? 'secondary')) ?: 'secondary';
                        ?>
                        <span class="badge rounded-pill text-bg-<?= sp_attr($itemVariant) ?>">
                            <i class="<?= sp_attr((string) ($item['icon'] ?? 'bi bi-dot')) ?> me-1"></i><?= esc((string) ($item['label'] ?? '')) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($actions !== []): ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($actions as $action): ?>
                    <?php
                    $variant = preg_replace('/[^a-z0-9_-]/i', '', (string) ($action['variant'] ?? 'secondary')) ?: 'secondary';
                    $href = trim((string) ($action['href'] ?? '#')) ?: '#';
                    $class = trim((string) ($action['class'] ?? ''));
                    $scroll = !empty($action['scroll']);
                    ?>
                    <a href="<?= sp_safe_url($href) ?>"
                       class="btn btn-sm <?= sp_attr($class !== '' ? $class : 'btn-outline-' . $variant) ?>"
                       <?= $scroll ? 'data-dashboard-scroll-link="true"' : '' ?>
                       data-navigation-label="<?= sp_attr((string) ($action['label'] ?? '')) ?>">
                        <i class="<?= sp_attr((string) ($action['icon'] ?? 'bi bi-arrow-right-circle')) ?> me-1"></i><?= esc((string) ($action['label'] ?? '')) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
