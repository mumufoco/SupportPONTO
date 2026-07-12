<?php
$alerts = is_array($alerts ?? null) ? $alerts : [];
$wrapperClass = trim((string) ($wrapperClass ?? 'd-flex flex-column gap-2')) ?: 'd-flex flex-column gap-2';
$itemClass = trim((string) ($itemClass ?? 'alert d-flex align-items-start mb-0')) ?: 'alert d-flex align-items-start mb-0';
?>
<div class="<?= sp_attr($wrapperClass) ?>">
    <?php foreach ($alerts as $alert): ?>
        <?php
        $alert = is_array($alert) ? $alert : [];
        $type = preg_replace('/[^a-z0-9_-]/i', '', (string) ($alert['type'] ?? 'info')) ?: 'info';
        $icon = (string) ($alert['icon'] ?? 'bi bi-info-circle');
        $title = trim((string) ($alert['title'] ?? ''));
        $message = trim((string) ($alert['message'] ?? ''));
        $actions = is_array($alert['actions'] ?? null) ? $alert['actions'] : [];
        ?>
        <div class="<?= sp_attr($itemClass) ?> alert-<?= sp_attr($type) ?>" role="alert">
            <i class="<?= sp_attr($icon) ?> me-2 mt-1"></i>
            <div class="flex-grow-1">
                <?php if ($title !== ''): ?>
                    <div class="fw-semibold"><?= esc($title) ?></div>
                <?php endif; ?>
                <div><?= esc($message !== '' ? $message : 'Alerta do sistema sem mensagem detalhada.') ?></div>
                <?php if ($actions !== []): ?>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php foreach ($actions as $action): ?>
                            <?php $action = is_array($action) ? $action : []; ?>
                            <a class="btn btn-sm btn-outline-<?= sp_attr($type) ?>" href="<?= sp_safe_url((string) ($action['href'] ?? '#')) ?>">
                                <?= esc((string) ($action['label'] ?? 'Abrir')) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
