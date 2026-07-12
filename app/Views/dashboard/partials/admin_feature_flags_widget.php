<?php
$payload = is_array($featureFlagsObservability ?? null) ? $featureFlagsObservability : [];
$payload = array_merge([
    'title' => 'Feature flags',
    'description' => 'Chaves funcionais e alternâncias operacionais.',
    'value' => (string) ($payload['enabledCount'] ?? $payload['value'] ?? 'OK'),
    'icon' => 'bi bi-toggles',
    'variant' => 'primary',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-feature-flags-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
