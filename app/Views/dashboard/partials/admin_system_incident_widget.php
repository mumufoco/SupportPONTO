<?php
$payload = is_array($systemIncidentObservability ?? null) ? $systemIncidentObservability : [];
$payload = array_merge([
    'title' => 'Incidentes do sistema',
    'description' => 'Eventos críticos e degradações recentes.',
    'value' => (string) ($payload['openIncidents'] ?? $payload['value'] ?? 'OK'),
    'icon' => 'bi bi-exclamation-octagon',
    'variant' => 'danger',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-system-incident-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
