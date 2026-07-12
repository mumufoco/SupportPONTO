<?php
$context = is_array($context ?? null) ? $context : [];
if (!($context['enabled'] ?? false)) {
    return;
}
?>
<div class="alert alert-info sp-alert-soft border-0 shadow-sm rounded-4 d-flex flex-column flex-lg-row justify-content-between align-items-start gap-3" role="status" aria-live="polite">
    <div>
        <div class="d-flex flex-wrap gap-2 mb-2 small">
            <span class="badge text-bg-light"><?= esc(lang('OperationalNavigation.badges.origin')) ?>: <?= esc($context['sourceLabel'] ?? '-') ?></span>
            <span class="badge text-bg-light"><?= esc(lang('OperationalNavigation.badges.flow')) ?>: <?= esc($context['flowLabel'] ?? '-') ?></span>
            <span class="badge text-bg-light"><?= esc(lang('OperationalNavigation.badges.currentScreen')) ?>: <?= esc($context['screenLabel'] ?? '-') ?></span>
            <span class="badge text-bg-primary-subtle text-primary-emphasis"><?= esc(lang('OperationalNavigation.badges.suggestedReturn')) ?>: <?= esc($context['returnLabel'] ?? '-') ?></span>
        </div>
        <h2 class="h6 mb-1"><?= esc($context['title'] ?? '') ?></h2>
        <p class="mb-0 text-muted"><?= esc($context['description'] ?? '') ?></p>
    </div>
    <div class="ms-lg-auto">
        <a href="<?= sp_safe_url($context['backUrl'] ?? site_url('dashboard/admin')) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-left-circle me-1"></i><?= esc($context['backLabel'] ?? lang('OperationalNavigation.actions.backToDashboard')) ?>
        </a>
    </div>
</div>
