<?php
$payload = is_array($payload ?? null) ? $payload : [];
$title = (string) ($payload['title'] ?? $title ?? 'Observabilidade');
$description = (string) ($payload['description'] ?? $description ?? 'Sem detalhes adicionais no momento.');
$value = (string) ($payload['value'] ?? $value ?? 'OK');
$icon = (string) ($payload['icon'] ?? $icon ?? 'bi bi-activity');
$variant = preg_replace('/[^a-z0-9_-]/i', '', (string) ($payload['variant'] ?? $variant ?? 'primary')) ?: 'primary';
$mode = preg_replace('/[^a-z0-9_-]/i', '', (string) ($payload['mode'] ?? $payload['deliveryMode'] ?? $mode ?? 'ok')) ?: 'ok';
$summaryUrl = trim((string) ($payload['summaryUrl'] ?? $summaryUrl ?? '#')) ?: '#';
$widgetId = preg_replace('/[^a-z0-9_-]/i', '-', strtolower((string) ($widgetId ?? $title)));
$meta = is_array($payload['meta'] ?? null) ? $payload['meta'] : [];
?>
<div class="card border-0 shadow-sm h-100" data-widget-shell="true">
    <div class="card-body">
        <div id="<?= sp_attr($widgetId) ?>"
             data-summary-url="<?= sp_attr($summaryUrl) ?>"
             data-delivery-mode="<?= sp_attr($mode) ?>"
             class="sp-observability-widget">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-<?= sp_attr($variant) ?> bg-opacity-10 text-<?= sp_attr($variant) ?> sp-circle-icon-2_5">
                        <i class="<?= sp_attr($icon) ?>"></i>
                    </span>
                    <div>
                        <h3 class="h6 mb-0"><?= esc($title) ?></h3>
                        <div class="small text-muted" data-observability-description><?= esc($description) ?></div>
                    </div>
                </div>
                <span class="badge rounded-pill text-bg-<?= sp_attr($mode === 'error' ? 'danger' : ($mode === 'degraded' ? 'warning' : 'success')) ?>" data-observability-value>
                    <?= esc($value) ?>
                </span>
            </div>
            <?php if ($meta !== []): ?>
                <dl class="row small mb-0 g-2">
                    <?php foreach ($meta as $label => $itemValue): ?>
                        <dt class="col-6 text-muted"><?= esc((string) $label) ?></dt>
                        <dd class="col-6 text-end mb-0"><?= esc((string) $itemValue) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php else: ?>
                <p class="small text-muted mb-0">Aguardando telemetria detalhada do módulo.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
