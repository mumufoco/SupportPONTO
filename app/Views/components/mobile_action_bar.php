<?php
helper('navigation_context');
$employee = is_array($employee ?? null) ? $employee : sp_session_user();
$actions = sp_dashboard_shortcuts_for_role((string) ($employee['role'] ?? 'funcionario'));
?>
<?php if (!empty($actions)): ?>
<nav class="sp-mobile-action-bar" aria-label="Atalhos principais">
    <?php foreach (array_slice($actions, 0, 5) as $action): ?>
        <a href="<?= sp_safe_url($action['url'] ?? '#') ?>">
            <?php if (!empty($action['icon'])): ?><i class="<?= sp_attr($action['icon']) ?>"></i><?php endif; ?>
            <?= esc($action['label'] ?? 'Atalho') ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
