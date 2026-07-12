<?php $methodReadiness = $methodReadiness ?? []; ?>
<div class="sp-surface-card">
    <div class="sp-surface-card__header">
        <h2 class="sp-profile-card__title"><i class="bi bi-grid-3x3-gap-fill"></i>Matriz de métodos</h2>
    </div>
    <div class="sp-surface-card__body">
        <div class="sp-compliance-checklist">
            <?php foreach ($methodReadiness as $method): ?>
                <div class="sp-compliance-item">
                    <strong><?= esc($method['label']) ?></strong>
                    <div><?= esc($method['description']) ?></div>
                    <div class="small text-muted mt-1">
                        Status: <?= esc($method['status_label']) ?> ·
                        Contextos: <?= esc(implode(', ', $method['recommended_contexts'] ?? [])) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
