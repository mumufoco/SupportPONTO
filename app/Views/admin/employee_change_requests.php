<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Solicitações de Alteração Cadastral<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $reqs = $requests ?? []; ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Solicitações de Alteração Cadastral',
        'subtitle' => 'Revise e decida as solicitações enviadas pelos colaboradores',
        'icon'     => 'bi bi-pencil-square',
        'actions'  => [],
    ]) ?>


    <?php
    $pending  = array_filter($reqs, fn($r) => $r->status === 'pending');
    $reviewed = array_filter($reqs, fn($r) => $r->status !== 'pending');
    ?>

    <?php if (!empty($pending)): ?>
    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title"><i class="bi bi-hourglass-split text-warning"></i>Pendentes</h2>
            <span class="badge bg-warning text-dark rounded-pill"><?= count($pending) ?></span>
        </div>
        <div class="sp-data-card__body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Campo</th>
                            <th>Alteração solicitada</th>
                            <th>Justificativa</th>
                            <th>Enviado em</th>
                            <th class="text-end pe-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pending as $r): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= esc($r->employee_name ?? '—') ?></div>
                            <div class="small text-muted"><?= esc($r->department ?? '') ?></div>
                        </td>
                        <td class="fw-medium small"><?= esc($r->field_label ?? $r->field_key ?? '') ?></td>
                        <td class="small">
                            <?php if (!empty($r->current_value)): ?>
                                <span class="text-muted text-decoration-line-through"><?= esc($r->current_value) ?></span>
                                <i class="bi bi-arrow-right text-muted mx-1"></i>
                            <?php endif; ?>
                            <span class="fw-semibold text-primary"><?= esc($r->requested_value ?? '') ?></span>
                        </td>
                        <td class="small text-muted" style="max-width:200px">
                            <span title="<?= esc($r->justification ?? '') ?>">
                                <?= esc(mb_strimwidth($r->justification ?? '', 0, 80, '…')) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= !empty($r->created_at) ? date('d/m/Y H:i', strtotime($r->created_at)) : '—' ?></td>
                        <td class="text-end pe-3">
                            <button type="button" class="btn btn-sm btn-success js-approve"
                                    data-id="<?= (int)$r->id ?>"
                                    data-name="<?= esc($r->employee_name ?? '') ?>"
                                    data-field="<?= esc($r->field_label ?? '') ?>"
                                    data-new="<?= esc($r->requested_value ?? '') ?>">
                                <i class="bi bi-check-circle me-1"></i>Aprovar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-1 js-reject"
                                    data-id="<?= (int)$r->id ?>"
                                    data-name="<?= esc($r->employee_name ?? '') ?>"
                                    data-field="<?= esc($r->field_label ?? '') ?>">
                                <i class="bi bi-x-circle me-1"></i>Recusar
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Histórico revisadas -->
    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title"><i class="bi bi-clock-history"></i>Histórico</h2>
        </div>
        <div class="sp-data-card__body p-0">
            <?php if (empty($reviewed)): ?>
                <?= view('components/empty_state', [
                    'icon'        => 'bi bi-check2-all',
                    'title'       => 'Nenhuma solicitação revisada ainda',
                    'description' => 'As solicitações aprovadas ou recusadas aparecerão aqui.',
                ]) ?>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Campo</th>
                            <th>Alteração</th>
                            <th>Status</th>
                            <th>Revisado por</th>
                            <th>Data</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reviewed as $r): ?>
                    <tr>
                        <td class="ps-3">
                            <div class="fw-semibold"><?= esc($r->employee_name ?? '—') ?></div>
                            <div class="small text-muted"><?= esc($r->department ?? '') ?></div>
                        </td>
                        <td class="small fw-medium"><?= esc($r->field_label ?? $r->field_key ?? '') ?></td>
                        <td class="small">
                            <?php if (!empty($r->current_value)): ?>
                                <span class="text-muted text-decoration-line-through me-1"><?= esc($r->current_value) ?></span>
                                <i class="bi bi-arrow-right text-muted me-1"></i>
                            <?php endif; ?>
                            <?= esc($r->requested_value ?? '') ?>
                        </td>
                        <td>
                            <?php if ($r->status === 'approved'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aprovada</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Recusada</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= esc($r->reviewer_name ?? '—') ?></td>
                        <td class="small text-muted"><?= !empty($r->reviewed_at) ? date('d/m/Y H:i', strtotime($r->reviewed_at)) : '—' ?></td>
                        <td class="small text-muted"><?= esc($r->review_note ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Modal: Aprovar -->
<div class="modal fade" id="modalApprove" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content">
            <form method="post" id="formApprove">
                <?= csrf_field() ?>
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-success"><i class="bi bi-check-circle me-2"></i>Aprovar solicitação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="mb-1">Aprovar alteração de <strong id="approveField"></strong> para
                       <strong id="approveNew" class="text-primary"></strong>
                       de <strong id="approveName"></strong>?
                    </p>
                    <p class="text-muted small mb-3">A alteração será aplicada automaticamente no cadastro do colaborador.</p>
                    <div>
                        <label class="form-label small fw-semibold">Nota para o colaborador (opcional)</label>
                        <textarea name="review_note" rows="2" class="form-control form-control-sm"
                                  placeholder="Ex.: Alteração verificada e aprovada."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-check-circle me-1"></i>Confirmar aprovação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Recusar -->
<div class="modal fade" id="modalReject" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content">
            <form method="post" id="formReject">
                <?= csrf_field() ?>
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Recusar solicitação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="mb-3">Recusar alteração de <strong id="rejectField"></strong> de <strong id="rejectName"></strong>?</p>
                    <div>
                        <label class="form-label small fw-semibold">Motivo da recusa <span class="text-danger">*</span></label>
                        <textarea name="review_note" rows="3" class="form-control form-control-sm" required
                                  placeholder="Explique o motivo da recusa para o colaborador..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0 gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Confirmar recusa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(() => {
    const modalApprove = new bootstrap.Modal(document.getElementById('modalApprove'));
    const modalReject  = new bootstrap.Modal(document.getElementById('modalReject'));

    document.querySelectorAll('.js-approve').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('approveName').textContent  = btn.dataset.name;
            document.getElementById('approveField').textContent = btn.dataset.field;
            document.getElementById('approveNew').textContent   = btn.dataset.new;
            document.getElementById('formApprove').action = '<?= site_url('admin/change-requests/') ?>' + btn.dataset.id + '/approve';
            document.querySelector('#formApprove textarea').value = '';
            modalApprove.show();
        });
    });

    document.querySelectorAll('.js-reject').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('rejectName').textContent  = btn.dataset.name;
            document.getElementById('rejectField').textContent = btn.dataset.field;
            document.getElementById('formReject').action = '<?= site_url('admin/change-requests/') ?>' + btn.dataset.id + '/reject';
            document.querySelector('#formReject textarea').value = '';
            modalReject.show();
        });
    });
})();
</script>
<?= $this->endSection() ?>
