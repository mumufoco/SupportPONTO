<?php
$payload = is_array($dbObservability ?? null) ? $dbObservability : [];
$payload = array_merge([
    'title' => 'Banco de dados',
    'description' => 'Estado de conexão e latência operacional.',
    'value' => (string) ($payload['statusLabel'] ?? $payload['value'] ?? 'OK'),
    'icon' => 'bi bi-database-check',
    'variant' => 'primary',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-database-observability-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
