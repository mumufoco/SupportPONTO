<?php
/**
 * Standalone KPI Card Component
 * A more focused version of the card component specifically for KPIs
 * 
 * Usage:
 * <?= view('components/kpi', [
 *     'icon' => 'fas fa-users',
 *     'iconColor' => 'primary',
 *     'value' => '150',
 *     'label' => 'Total Users',
 *     'indicator' => '+12%',
 *     'indicatorType' => 'positive',
 *     'classes' => 'grid-col-3'
 * ]) ?>
 */

// Delegate to card component with type='kpi'
echo view('components/card', array_merge([
    'type' => 'kpi'
], get_defined_vars()));
