<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Alertas de Fraude Facial<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $rows = $alerts ?? [];
    $methodLabels = ['cpf' => 'CPF', 'code' => 'Código', 'qrcode' => 'QR Code', 'fingerprint' => 'Digital'];
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Alertas de Fraude Facial',
        'subtitle' => 'Registros em que a foto de verificação não correspondeu ao cadastro biométrico — o ponto foi registrado normalmente, mas fica pendente de revisão.',
        'icon'     => 'bi bi-shield-exclamation',
    ]) ?>

    <?php if (!empty($departmentScope)): ?>
        <div class="alert alert-info d-flex gap-2 align-items-start mb-3 py-2">
            <i class="bi bi-funnel-fill mt-1 flex-shrink-0"></i>
            <small>Como gestor, você visualiza apenas alertas de colaboradores do seu departamento (<?= esc($departmentScope) ?>).</small>
        </div>
    <?php endif; ?>

    <div class="sp-grid-4 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Pendentes</div>
                <div class="stat-card-value"><?= (int) $pendingCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Revisados</div>
                <div class="stat-card-value"><?= (int) $reviewedCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Descartados</div>
                <div class="stat-card-value"><?= (int) $dismissedCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-shield-exclamation"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Total de alertas</div>
                <div class="stat-card-value"><?= (int) $pendingCount + (int) $reviewedCount + (int) $dismissedCount ?></div>
            </div>
        </div>
    </div>

    <div class="sp-data-card mb-3">
        <div class="sp-data-card__body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="f-status">Status</label>
                    <select name="status" id="f-status" class="form-select" onchange="this.form.submit()">
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pendentes</option>
                        <option value="reviewed" <?= $status === 'reviewed' ? 'selected' : '' ?>>Revisados</option>
                        <option value="dismissed" <?= $status === 'dismissed' ? 'selected' : '' ?>>Descartados</option>
                        <option value="" <?= $status === '' ? 'selected' : '' ?>>Todos</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-list-check"></i></span>
                Alertas
            </h2>
            <span class="sp-badge sp-badge-neutral"><?= (int) ($total ?? count($rows)) ?> registro<?= (int) ($total ?? count($rows)) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="sp-data-card__body p-0">
            <?php if (empty($rows)): ?>
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-shield-check"></i></div>
                    <p class="sp-empty-title">Nenhum alerta encontrado</p>
                    <p class="text-muted small mb-0">Nenhum registro de possível fraude facial para este filtro.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Método</th>
                            <th>Motivo</th>
                            <th>Similaridade</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th class="text-end">Ação</th>
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
                        $reasonBadge = $reason === 'mismatch' ? 'sp-badge-danger' : 'sp-badge-neutral';
                        $method = strtolower((string) ($r->method ?? ''));
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($r->employee_name)): ?>
                                <div class="fw-semibold"><?= esc($r->employee_name) ?></div>
                                <div class="small text-muted"><?= esc($r->employee_code ?? '') ?></div>
                            <?php else: ?>
                                <span class="text-muted"><i class="bi bi-person-x me-1"></i>Colaborador removido</span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= esc($methodLabels[$method] ?? strtoupper($method) ?: '—') ?></td>
                        <td class="small"><span class="sp-badge <?= $reasonBadge ?>"><?= esc($reasonLabel) ?></span></td>
                        <td class="small">
                            <?php if ($r->similarity_score !== null): ?>
                                <?= number_format((float) $r->similarity_score, 4) ?>
                                <span class="text-muted">/ <?= $r->threshold_used !== null ? number_format((float) $r->threshold_used, 4) : '—' ?></span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= !empty($r->created_at) ? esc(format_datetime_br((string) $r->created_at, false)) : '—' ?></td>
                        <td>
                            <?php if ($r->status === 'pending'): ?>
                                <span class="sp-badge sp-badge-warning"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
                            <?php elseif ($r->status === 'reviewed'): ?>
                                <span class="sp-badge sp-badge-success"><i class="bi bi-check-circle-fill me-1"></i>Revisado</span>
                            <?php else: ?>
                                <span class="sp-badge sp-badge-neutral"><i class="bi bi-x-circle-fill me-1"></i>Descartado</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($r->status === 'pending'): ?>
                            <div class="d-flex gap-1 justify-content-end">
                                <button type="button" class="btn btn-sm btn-success js-review"
                                        data-id="<?= (int) $r->id ?>"
                                        data-name="<?= esc($r->employee_name ?? 'colaborador removido') ?>"
                                        data-decision="reviewed">
                                    <i class="bi bi-check-circle me-1"></i>Revisar
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-review"
                                        data-id="<?= (int) $r->id ?>"
                                        data-name="<?= esc($r->employee_name ?? 'colaborador removido') ?>"
                                        data-decision="dismissed">
                                    <i class="bi bi-x-circle me-1"></i>Descartar
                                </button>
                            </div>
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

            <?php if (!empty($total) && $total > $perPage): ?>
            <?php
                $pages = (int) ceil($total / $perPage);
                $currentPage = (int) ($page ?? 1);
                $qs = http_build_query(array_filter(['status' => $status ?? '']));
            ?>
            <div class="border-top p-3">
                <nav>
                    <ul class="pagination pagination-sm mb-0 flex-wrap">
                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= $qs ? '&' . $qs : '' ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
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
