<?php
$payload = is_array($performanceIndexObservability ?? null) ? $performanceIndexObservability : [];
$payload = array_merge([
    'title' => 'Índice de performance',
    'description' => 'Sinais resumidos de resposta e carga operacional.',
    'value' => (string) ($payload['score'] ?? $payload['value'] ?? 'OK'),
    'icon' => 'bi bi-speedometer2',
    'variant' => 'success',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-performance-index-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
