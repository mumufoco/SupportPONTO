<?php
/**
 * Modern Reusable Card Component
 *
 * Usage:
 * <?= view('components/card', [
 *     'title' => 'Card Title',
 *     'subtitle' => 'Optional subtitle',
 *     'icon' => 'fas fa-users',
 *     'extra' => 'Right side content/actions',
 *     'classes' => 'grid-col-4',
 *     'id' => 'unique-id',
 *     'type' => 'kpi', // kpi, default
 *     'slot' => '<p>Card content here</p>'
 * ]) ?>
 *
 * KPI Card specific:
 *     'value' => '150',
 *     'label' => 'Total Users',
 *     'indicator' => '+12%',
 *     'indicatorType' => 'positive' // positive, negative, neutral
 */

$title = $title ?? '';
$subtitle = $subtitle ?? '';
$icon = $icon ?? '';
$extra = $extra ?? '';
$classes = $classes ?? '';
$id = $id ?? '';
$type = $type ?? 'default';
$slot = $slot ?? '';

// KPI specific
$value = $value ?? '';
$label = $label ?? '';
$indicator = $indicator ?? '';
$indicatorType = $indicatorType ?? 'neutral';
$iconColor = $iconColor ?? 'primary'; // primary, accent, warning, danger
?>

<div class="card <?= $type === 'kpi' ? 'card-kpi' : '' ?> <?= $classes ?>" <?= $id ? 'id="' . esc($id) . '"' : '' ?>>
    <?php if ($title || $extra): ?>
        <div class="card-header">
            <div class="card-header-title">
                <?php if ($icon): ?>
                    <i class="<?= $icon ?>"></i>
                <?php endif; ?>
                <?= esc($title) ?>
            </div>
            <?php if ($extra): ?>
                <div class="card-header-actions">
                    <?= $extra ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card-body">
        <?php if ($type === 'kpi'): ?>
            <!-- KPI Card Layout -->
            <?php if ($icon && !$title): ?>
                <div class="card-icon <?= $iconColor ?>">
                    <i class="<?= $icon ?>"></i>
                </div>
            <?php endif; ?>
            
            <?php if ($value): ?>
                <div class="kpi-value"><?= esc($value) ?></div>
            <?php endif; ?>
            
            <?php if ($label): ?>
                <div class="kpi-label"><?= esc($label) ?></div>
            <?php endif; ?>
            
            <?php if ($indicator): ?>
                <div class="kpi-indicator <?= $indicatorType ?>">
                    <?php if ($indicatorType === 'positive'): ?>
                        <i class="fas fa-arrow-up"></i>
                    <?php elseif ($indicatorType === 'negative'): ?>
                        <i class="fas fa-arrow-down"></i>
                    <?php endif; ?>
                    <?= esc($indicator) ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Default Card Layout -->
            <?php if ($subtitle): ?>
                <div class="card-subtitle"><?= esc($subtitle) ?></div>
            <?php endif; ?>
            
            <?= $slot ?>
        <?php endif; ?>
    </div>
</div>
