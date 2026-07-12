<?php
/** Região acessível para feedback assíncrono de formulários e operações JS. */
$id = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($id ?? 'sp-action-feedback'));
$label = trim((string) ($label ?? 'Atualizações da operação'));
$initialMessage = trim((string) ($message ?? ''));
$variant = trim((string) ($variant ?? 'info'));
?>
<div id="<?= esc($id) ?>"
     class="sp-action-feedback <?= $initialMessage === '' ? 'd-none' : '' ?>"
     data-sp-action-feedback
     role="status"
     aria-live="polite"
     aria-atomic="true"
     aria-label="<?= esc($label) ?>">
    <?php if ($initialMessage !== ''): ?>
        <div class="alert alert-<?= esc($variant) ?> mb-0"><?= esc($initialMessage) ?></div>
    <?php endif; ?>
</div>
