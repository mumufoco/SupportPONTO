<?php
/**
 * Reusable Filters Component
 * 
 * Usage:
 * <?= view('components/filters', [
 *     'title' => 'Filtros',
 *     'fields' => [
 *         ['type' => 'text', 'name' => 'search', 'label' => 'Buscar', 'placeholder' => 'Digite...'],
 *         ['type' => 'select', 'name' => 'status', 'label' => 'Status', 'options' => ['ativo' => 'Ativo', 'inativo' => 'Inativo']],
 *         ['type' => 'date', 'name' => 'data_inicio', 'label' => 'Data Início'],
 *     ],
 *     'action' => base_url('reports/filter'),
 *     'method' => 'GET'
 * ]) ?>
 */

$title = $title ?? lang('AppUi.filters.defaultTitle');
$fields = $fields ?? [];
$action = $action ?? '';
$method = $method ?? 'GET';
$showReset = $showReset ?? true;
$showSubmit = $showSubmit ?? true;
?>

<div class="card grid-col-12 mb-4">
    <div class="card-header">
        <div class="card-header-title">
            <i class="fas fa-filter"></i>
            <?= esc($title) ?>
        </div>
        <div class="card-header-actions">
            <?php if ($showReset): ?>
                <button type="reset" class="btn btn-sm btn-outline-primary" onclick="this.form.reset();">
                    <i class="fas fa-times me-1"></i> <?= esc(lang('AppUi.filters.clear')) ?>
                </button>
            <?php endif; ?>
            <?php if ($showSubmit): ?>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search me-1"></i> <?= esc(lang('AppUi.filters.apply')) ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <form method="<?= esc(strtoupper($method)) ?>" action="<?= esc($action) ?>" class="filter-form">
            <div class="row g-3">
                <?php foreach ($fields as $field): ?>
                    <div class="col-md-<?= $field['col'] ?? 3 ?>">
                        <label class="form-label fw-semibold sp-form-label-muted">
                            <?= esc($field['label'] ?? '') ?>
                        </label>
                        
                        <?php if ($field['type'] === 'text' || $field['type'] === 'email' || $field['type'] === 'number'): ?>
                            <input
                                type="<?= esc($field['type']) ?>"
                                name="<?= esc($field['name']) ?>" 
                                class="form-control" 
                                placeholder="<?= esc($field['placeholder'] ?? '') ?>"
                                value="<?= esc($field['value'] ?? '') ?>">
                        
                        <?php elseif ($field['type'] === 'date' || $field['type'] === 'datetime-local'): ?>
                            <input
                                type="<?= esc($field['type']) ?>"
                                name="<?= esc($field['name']) ?>" 
                                class="form-control"
                                value="<?= esc($field['value'] ?? '') ?>">
                        
                        <?php elseif ($field['type'] === 'select'): ?>
                            <select name="<?= esc($field['name']) ?>" class="form-select">
                                <option value=""><?= esc(lang('AppUi.filters.selectPlaceholder')) ?></option>
                                <?php foreach ($field['options'] ?? [] as $value => $label): ?>
                                    <option value="<?= esc($value) ?>" <?= ($field['value'] ?? '') == $value ? 'selected' : '' ?>>
                                        <?= esc($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        
                        <?php elseif ($field['type'] === 'checkbox'): ?>
                            <div class="form-check">
                                <input 
                                    type="checkbox" 
                                    name="<?= esc($field['name']) ?>" 
                                    class="form-check-input" 
                                    id="filter_<?= esc($field['name']) ?>"
                                    value="1"
                                    <?= !empty($field['checked']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="filter_<?= esc($field['name']) ?>">
                                    <?= esc($field['checkboxLabel'] ?? $field['label']) ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>
