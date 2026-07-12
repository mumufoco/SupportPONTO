<?php
$payload = is_array($deepFaceObservability ?? null) ? $deepFaceObservability : [];
$payload = array_merge([
    'title' => 'DeepFace',
    'description' => 'Disponibilidade da API de reconhecimento facial.',
    'value' => (string) ($payload['statusLabel'] ?? $payload['value'] ?? 'Monitorado'),
    'icon' => 'bi bi-person-bounding-box',
    'variant' => 'info',
    'mode' => (string) ($payload['mode'] ?? 'ok'),
], $payload);
$widgetId = 'admin-deepface-observability-widget';
?>
<?= view('dashboard/partials/_admin_observability_widget', compact('payload', 'widgetId')) ?>
