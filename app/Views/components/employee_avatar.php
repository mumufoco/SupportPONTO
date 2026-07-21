<?php
/**
 * Avatar de colaborador com fallback automático para iniciais (via onerror)
 * quando não há foto -- sempre pela mesma fonte já existente
 * (employees/{id}/photo), nunca um novo mecanismo de armazenamento.
 *
 * @var int         $employeeId
 * @var string      $initials
 * @var int|null    $size        px (padrão 32)
 */
$employeeId = (int) ($employeeId ?? 0);
$initials = mb_substr((string) ($initials ?? '?'), 0, 1);
$size = (int) ($size ?? 32);
$photoUrl = $employeeId > 0 ? site_url('employees/' . $employeeId . '/photo') : '';
$fontSize = max(10, (int) round($size * 0.42));
?>
<span class="sp-avatar-fallback" data-employee-avatar
      style="display:inline-flex;align-items:center;justify-content:center;width:<?= $size ?>px;height:<?= $size ?>px;border-radius:50%;background:var(--sp-primary-soft,#e7ecff);color:var(--sp-primary,#4f46e5);font-size:<?= $fontSize ?>px;font-weight:600;flex-shrink:0;overflow:hidden;">
    <?php if ($photoUrl !== ''): ?>
        <img src="<?= esc($photoUrl) ?>" alt="<?= esc($initials, 'attr') ?>"
             style="width:100%;height:100%;object-fit:cover;border-radius:50%;"
             onerror="this.style.display='none';this.parentElement.append(this.alt);"
        ><?php else: ?><?= esc($initials) ?><?php endif; ?>
</span>
