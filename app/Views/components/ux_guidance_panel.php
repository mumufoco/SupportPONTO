<?php
/**
 * Painel reutilizável de orientação operacional.
 *
 * Mantém mensagens de ajuda, critérios de conclusão e orientações técnicas em
 * um único componente para reduzir duplicação em telas grandes do admin/kiosk.
 */
$title = trim((string) ($title ?? 'Orientação da tela'));
$subtitle = trim((string) ($subtitle ?? 'Siga os passos abaixo para concluir esta operação com segurança.'));
$icon = trim((string) ($icon ?? 'bi bi-compass'));
$variant = trim((string) ($variant ?? 'primary'));
$mode = trim((string) ($mode ?? 'simple'));
$items = is_array($items ?? null) ? $items : [];
$technical = is_array($technical ?? null) ? $technical : [];
$compact = (bool) ($compact ?? false);
$panelId = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) ($id ?? ('ux-guidance-' . substr(sha1($title), 0, 8))));
?>
<section id="<?= esc($panelId) ?>"
         class="sp-ux-guidance sp-ux-guidance--<?= esc($variant) ?> <?= $compact ? 'sp-ux-guidance--compact' : '' ?>"
         data-sp-ux-guidance
         data-mode="<?= esc($mode) ?>"
         role="region"
         aria-labelledby="<?= esc($panelId) ?>-title">
    <div class="sp-ux-guidance__header">
        <div class="sp-ux-guidance__icon" aria-hidden="true"><i class="<?= esc($icon) ?>"></i></div>
        <div class="sp-ux-guidance__copy">
            <h2 class="sp-ux-guidance__title" id="<?= esc($panelId) ?>-title"><?= esc($title) ?></h2>
            <?php if ($subtitle !== ''): ?>
                <p class="sp-ux-guidance__subtitle"><?= esc($subtitle) ?></p>
            <?php endif; ?>
        </div>
        <button type="button"
                class="sp-ux-guidance__toggle"
                data-sp-ux-toggle
                aria-expanded="false"
                aria-controls="<?= esc($panelId) ?>-technical">
            <span class="sp-ux-guidance__toggle-simple">Ver detalhes técnicos</span>
            <span class="sp-ux-guidance__toggle-technical">Ocultar detalhes</span>
        </button>
    </div>

    <?php if (!empty($items)): ?>
        <ul class="sp-ux-guidance__list">
            <?php foreach ($items as $item): ?>
                <?php
                $itemLabel = is_array($item) ? (string) ($item['label'] ?? '') : (string) $item;
                $itemIcon = is_array($item) ? (string) ($item['icon'] ?? 'bi bi-check2-circle') : 'bi bi-check2-circle';
                if (trim($itemLabel) === '') {
                    continue;
                }
                ?>
                <li><i class="<?= esc($itemIcon) ?>" aria-hidden="true"></i><span><?= esc($itemLabel) ?></span></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div id="<?= esc($panelId) ?>-technical" class="sp-ux-guidance__technical" data-sp-ux-technical hidden>
        <?php if (!empty($technical)): ?>
            <ul class="mb-0">
                <?php foreach ($technical as $line): ?>
                    <?php $line = trim((string) $line); if ($line === '') { continue; } ?>
                    <li><?= esc($line) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="mb-0">Não há observações técnicas adicionais para esta tela.</p>
        <?php endif; ?>
    </div>
</section>
