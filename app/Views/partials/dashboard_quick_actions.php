<?php

helper(['navigation_context']);

$quickActions = sp_dashboard_shortcuts_for_role();
?>

<section class="sp-content-grid mb-4">
    <div class="sp-page-header">
        <div>
            <h2>Atalhos inteligentes</h2>
            <p>Acesse as ações mais usadas de forma rápida e padronizada.</p>
        </div>
    </div>

    <div class="sp-shortcuts-grid">
        <?php foreach ($quickActions as $action): ?>
            <a class="sp-shortcut-card" href="<?= sp_safe_url($action['url']) ?>">
                <div class="icon"><i class="<?= esc($action['icon']) ?>"></i></div>
                <strong><?= esc($action['label']) ?></strong>
                <span><?= esc($action['description']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
