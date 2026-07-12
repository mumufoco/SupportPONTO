<?php $methodReadiness = $methodReadiness ?? []; ?>
<div class="sp-callout-warning">
    <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Pontos críticos de validação</strong>
    <div>Após qualquer alteração no módulo de ponto, valide método selecionado, feedback visual, integração com histórico e tratamento de falhas.</div>
    <?php if ($methodReadiness !== []): ?>
        <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($methodReadiness as $method): ?>
                <li>
                    <strong><?= esc($method['label']) ?>:</strong>
                    <?= esc($method['homologation_checks'][0] ?? 'Validar fluxo fim a fim.') ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
