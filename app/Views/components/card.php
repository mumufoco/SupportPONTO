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
    <?php /*
     * Cards do tipo 'kpi' nunca usam $title/$extra no próprio design (só value/
     * label/indicator) — nenhum call site do app passa 'title' para este
     * componente com type=kpi. A checagem "$type !== 'kpi'" existe porque
     * CodeIgniter\View com saveData=true (padrão do framework) faz o $title da
     * view de página (ex.: controller passando 'title' => 'Nome da Página')
     * vazar para dentro de qualquer view('components/...') chamada depois,
     * dentro do mesmo request — sem essa guarda, todo kpi-card herdava e
     * exibia o título da página inteira como cabeçalho do card.
     */ ?>
    <?php if (($title || $extra) && $type !== 'kpi'): ?>
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
            
            <?php if ($value !== '' && $value !== null): ?>
                <div class="kpi-value"><?= esc((string) $value) ?></div>
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
