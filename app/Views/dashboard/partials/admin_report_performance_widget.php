<?php
$payload = is_array($reportPerformanceObservability ?? null) ? $reportPerformanceObservability : [];
$payload = array_merge([
    'title' => 'Relatórios',
    'description' => 'Tempo e estabilidade de geração de relatórios.',
    'value' => (string) ($payload['statusLabel'] ?? $payload['value'] ?? 'OK'),
    'icon' => 'bi bi-file-earmark-bar-graph',
    'variant' => 'warning',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-report-performance-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
