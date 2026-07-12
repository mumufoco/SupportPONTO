<?php
$payload = is_array($rateLimitObservability ?? null) ? $rateLimitObservability : [];
$payload = array_merge([
    'title' => 'Rate limit',
    'description' => 'Controle de abuso em autenticação, biometria e API.',
    'value' => (string) ($payload['statusLabel'] ?? $payload['value'] ?? 'Ativo'),
    'icon' => 'bi bi-shield-lock',
    'variant' => 'dark',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-rate-limit-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
