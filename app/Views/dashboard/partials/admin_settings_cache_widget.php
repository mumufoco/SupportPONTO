<?php
$payload = is_array($settingsCacheObservability ?? null) ? $settingsCacheObservability : [];
$payload = array_merge([
    'title' => 'Cache de configurações',
    'description' => 'Coerência entre configurações persistidas e cache.',
    'value' => (string) ($payload['statusLabel'] ?? $payload['value'] ?? 'OK'),
    'icon' => 'bi bi-sliders',
    'variant' => 'secondary',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-settings-cache-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
