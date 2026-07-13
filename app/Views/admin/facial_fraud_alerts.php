<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Alertas de Fraude Facial<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $rows = $alerts ?? []; ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Alertas de Fraude Facial',
        'subtitle' => 'Registros em que a foto de verificação não correspondeu ao cadastro biométrico — o ponto foi registrado normalmente, mas fica pendente de revisão',
        'icon'     => 'bi bi-shield-exclamation',
        'actions'  => [],
    ]) ?>

    <div class="sp-data-card">
        <div class="sp-data-card__body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small fw-semibold mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="reviewed" <?= $status === 'reviewed' ? 'selected' : '' ?>>Revisados</option>
                        <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Descartados</option>
                        <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
                    </select>
                </div>
                <div class="col-auto">
                    <span class="badge bg-warning text-dark rounded-pill"><?= (int) $pendingCount ?> pendente(s)</span>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title"><i class="bi bi-list-check"></i>Alertas</h2>
            <span class="badge bg-secondary rounded-pill"><?= count($rows) ?></span>
        </div>
        <div class="sp-data-card__body p-0">
            <?php if (empty($rows)): ?>
                <?= view('components/empty_state', [
                    'icon'        => 'bi bi-shield-check',
                    'title'       => 'Nenhum alerta encontrado',
                    'description' => 'Nenhum registro de possível fraude facial para este filtro.',
                ]) ?>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Método</th>
                            <th>Motivo</th>
                            <th>Similaridade</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                    <?php
                        $reason = $r->reason ?? 'mismatch';
                        $reasonLabel = [
                            'mismatch' => 'Foto não corresponde',
                            'no_photo' => 'Falha técnica (sem foto)',
                            'service_error' => 'Serviço indisponível',
                        ][$reason] ?? $reason;
                        $reasonBadgeClass = $reason === 'mismatch' ? 'bg-danger' : 'bg-secondary';
                    ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= esc($r->employee_name ?? '—') ?></div>
                            <div class="small text-muted"><?= esc($r->employee_code ?? '') ?></div>
                        </td>
                        <td class="small text-uppercase"><?= esc($r->method ?? '—') ?></td>
                        <td class="small"><span class="badge <?= $reasonBadgeClass ?>"><?= esc($reasonLabel) ?></span></td>
                        <td class="small">
                            <?php if ($r->similarity_score !== null): ?>
                                <?= number_format((float) $r->similarity_score, 4) ?>
                                <span class="text-muted">/ <?= $r->threshold_used !== null ? number_format((float) $r->threshold_used, 4) : '—' ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= !empty($r->created_at) ? date('d/m/Y H:i', strtotime($r->created_at)) : '—' ?></td>
                        <td>
                            <?php if ($r->status === 'pending'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
                            <?php elseif ($r->status === 'reviewed'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Revisado</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><i class="bi bi-x-circle me-1"></i>Descartado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <?php if ($r->status === 'pending'): ?>
                            <button type="button" class="btn btn-sm btn-success js-review"
                                    data-id="<?= (int) $r->id ?>"
                                    data-name="<?= esc($r->employee_name ?? '') ?>"
                                    data-decision="reviewed">
                                <i class="bi bi-check-circle me-1"></i>Revisar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-1 js-review"
                                    data-id="<?= (int) $r->id ?>"
                                    data-name="<?= esc($r->employee_name ?? '') ?>"
                                    data-decision="dismissed">
                                <i class="bi bi-x-circle me-1"></i>Descartar
                            </button>
                            <?php else: ?>
                                <span class="small text-muted"><?= esc($r->review_notes ?? '') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal: Revisar/Descartar -->
<div class="modal fade" id="modalReview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content">
            <form method="post" id="formReview">
                <?= csrf_field() ?>
                <input type="hidden" name="decision" id="reviewDecision" value="">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="reviewTitle"><i class="bi bi-shield-exclamation me-2"></i>Revisar alerta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="mb-3">Registrar decisão sobre o alerta de <strong id="reviewName"></strong>?</p>
                    <div>
                        <label class="form-label small fw-semibold">Observações (opcional)</label>
                        <textarea name="review_notes" rows="3" class="form-control form-control-sm"
                                  placeholder="Ex.: verificado com o colaborador, sem indício de fraude."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check-circle me-1"></i>Confirmar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(() => {
    const modalReview = new bootstrap.Modal(document.getElementById('modalReview'));

    document.querySelectorAll('.js-review').forEach(btn => {
        btn.addEventListener('click', () => {
            const decision = btn.dataset.decision;
            document.getElementById('reviewName').textContent = btn.dataset.name;
            document.getElementById('reviewDecision').value = decision;
            document.getElementById('reviewTitle').innerHTML = decision === 'reviewed'
                ? '<i class="bi bi-check-circle me-2"></i>Marcar como revisado'
                : '<i class="bi bi-x-circle me-2"></i>Descartar alerta';
            document.getElementById('formReview').action = '<?= site_url('admin/facial-fraud-alerts/') ?>' + btn.dataset.id + '/review';
            document.querySelector('#formReview textarea').value = '';
            modalReview.show();
        });
    });
})();
</script>
<?= $this->endSection() ?>
