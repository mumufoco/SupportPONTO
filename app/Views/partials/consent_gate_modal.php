<?php
/**
 * Lembrete flutuante de consentimento LGPD pendente -- exibido nas páginas
 * de dashboard (ver layouts/main.php) enquanto o colaborador não tiver
 * aceito todos os tipos obrigatórios (ConsentGateCatalog::TYPES). Não
 * bloqueia o restante do sistema, apenas relembra até ser resolvido.
 *
 * @var array<string,array{label:string,icon:string,description:string}> $pending
 */
$pending = $pending ?? [];
if (empty($pending)) {
    return;
}
?>
<div class="modal fade" id="consentGateModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title"><i class="bi bi-shield-lock-fill me-2 text-warning"></i>Aceite necessário</h5>
            </div>
            <form method="POST" action="<?= sp_safe_url(sp_route_url('consent-gate.accept-all')) ?>">
                <?= csrf_field() ?>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-3">
                        Para continuar usando o sistema, precisamos do seu consentimento para os itens abaixo (LGPD — Lei 13.709/2018).
                    </p>
                    <ul class="list-group list-group-flush mb-1">
                        <?php foreach ($pending as $type => $meta): ?>
                            <li class="list-group-item px-0 d-flex align-items-start gap-2">
                                <i class="<?= esc($meta['icon']) ?> mt-1 text-primary"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small"><?= esc($meta['label']) ?></div>
                                    <div class="text-muted small"><?= esc($meta['description']) ?></div>
                                </div>
                                <a href="<?= site_url('consent-gate/' . $type) ?>" target="_blank" rel="noopener"
                                   class="small flex-shrink-0" title="Ler termo completo">
                                    <i class="bi bi-file-text"></i>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="submit" class="sp-btn sp-btn-primary sp-btn-full">
                        <i class="bi bi-check-lg me-1"></i>Aceitar e continuar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('consentGateModal');
    if (el) { new bootstrap.Modal(el).show(); }
});
</script>
