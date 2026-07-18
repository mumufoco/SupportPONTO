<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Aprovação de Registros Pendentes<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-clock-history me-2"></i>Aprovação de Registros Pendentes</h4>
        <span class="badge bg-warning text-dark fs-6"><?= count($pendingList) ?> pendente(s)</span>
    </div>


    <div id="panelFeedback" aria-live="polite"></div>

    <?php if (empty($pendingList)): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>Nenhum registro pendente de aprovação no momento.</span>
    </div>
    <?php else: ?>

    <div class="row g-3">
        <?php foreach ($pendingList as $p): ?>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-warning border-start border-3">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong><?= esc($p->employee_name ?? 'Colaborador') ?></strong>
                            <span class="badge bg-secondary ms-2"><?= esc($p->department ?? '') ?></span>
                        </div>
                        <small class="text-muted"><?= esc(date('d/m/Y H:i', strtotime($p->intended_time))) ?></small>
                    </div>

                    <p class="mb-1 small">
                        <span class="fw-semibold">Tipo:</span>
                        <?= esc(ucfirst(str_replace('_', ' ', $p->intended_punch_type))) ?>
                    </p>

                    <p class="mb-1 small">
                        <span class="fw-semibold">Situação:</span>
                        <?= esc(str_replace('_', ' ', $p->situation_type)) ?>
                    </p>

                    <p class="mb-2 small text-muted border-start border-3 ps-2">
                        "<?= esc(mb_substr($p->justification_text, 0, 200)) ?><?= mb_strlen($p->justification_text) > 200 ? '…' : '' ?>"
                    </p>

                    <?php if ($p->expires_at): ?>
                    <p class="mb-2 small text-danger">
                        <i class="bi bi-alarm me-1"></i>Expira: <?= esc(date('d/m H:i', strtotime($p->expires_at))) ?>
                    </p>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button"
                                class="btn btn-sm btn-success flex-grow-1 js-approve-btn"
                                data-id="<?= (int)$p->id ?>"
                                data-name="<?= esc($p->employee_name ?? '') ?>">
                            <i class="bi bi-check-lg me-1"></i>Aprovar
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-danger flex-grow-1 js-reject-btn"
                                data-id="<?= (int)$p->id ?>"
                                data-name="<?= esc($p->employee_name ?? '') ?>">
                            <i class="bi bi-x-lg me-1"></i>Rejeitar
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 mt-5">
        <h4 class="mb-0"><i class="bi bi-shield-exclamation me-2"></i>Irregularidades de conformidade (CLT)</h4>
        <span class="badge bg-info text-dark fs-6"><?= count($violationList ?? []) ?> pendente(s)</span>
    </div>

    <?php if (empty($violationList)): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i>
        <span>Nenhuma irregularidade trabalhista detectada no momento.</span>
    </div>
    <?php else: ?>

    <div class="row g-3">
        <?php foreach ($violationList as $v): ?>
        <?php
            $violationType = \App\Enums\PunchViolationType::tryFrom((string) $v->violation_type);
            $details = $violationModel->decodeDetails($v);
        ?>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100 border-info border-start border-3">
                <div class="card-body">

                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong><?= esc($v->employee_name ?? 'Colaborador') ?></strong>
                            <span class="badge bg-secondary ms-2"><?= esc($v->department ?? '') ?></span>
                        </div>
                        <small class="text-muted"><?= esc(date('d/m/Y', strtotime($v->reference_date))) ?></small>
                    </div>

                    <p class="mb-1 small">
                        <span class="fw-semibold"><?= esc($violationType?->label() ?? ucfirst(str_replace('_', ' ', $v->violation_type))) ?></span>
                        <span class="badge bg-light text-dark border ms-1"><?= esc($violationType?->legalReference() ?? '') ?></span>
                    </p>

                    <?php if (!empty($details)): ?>
                    <p class="mb-2 small text-muted border-start border-3 ps-2">
                        <?php foreach ($details as $key => $value): ?>
                            <?= esc(ucfirst(str_replace('_', ' ', (string) $key))) ?>: <strong><?= esc((string) $value) ?></strong><br>
                        <?php endforeach; ?>
                    </p>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-3">
                        <button type="button"
                                class="btn btn-sm btn-info flex-grow-1 js-resolve-violation-btn"
                                data-id="<?= (int) $v->id ?>"
                                data-name="<?= esc($v->employee_name ?? '') ?>">
                            <i class="bi bi-check2-square me-1"></i>Marcar como tratada
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>

<!-- Modal: Aprovar -->
<div class="modal fade" id="approveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content">
            <div class="modal-header bg-success text-white border-0">
                <h5 class="modal-title"><i class="bi bi-check-circle me-2"></i>Aprovar Registro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Confirma aprovação do registro de <strong id="approveEmployeeName"></strong>?</p>
                <p class="small text-muted mb-3">O ponto será efetivado com método <em>"Manual — aprovado por gestor"</em>.</p>
                <div>
                    <label class="form-label fw-semibold small">Observação (opcional)</label>
                    <textarea id="approveNotes" class="form-control form-control-sm" rows="2" maxlength="500"
                              placeholder="Observação para o colaborador..."></textarea>
                </div>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success btn-sm" id="btnConfirmApprove">
                    <i class="bi bi-check-circle me-1"></i>Confirmar aprovação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Rejeitar -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title"><i class="bi bi-x-circle me-2"></i>Rejeitar Registro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Motivo da rejeição para <strong id="rejectEmployeeName"></strong>: <span class="text-danger">*</span></p>
                <textarea id="rejectNotes" class="form-control form-control-sm" rows="3" maxlength="500" required
                          placeholder="Explique o motivo da rejeição..."></textarea>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmReject">
                    <i class="bi bi-x-circle me-1"></i>Confirmar rejeição
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Marcar irregularidade como tratada -->
<div class="modal fade" id="resolveViolationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:440px">
        <div class="modal-content">
            <div class="modal-header bg-info border-0">
                <h5 class="modal-title"><i class="bi bi-check2-square me-2"></i>Marcar como tratada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Providência tomada para a irregularidade de <strong id="resolveViolationEmployeeName"></strong>: <span class="text-danger">*</span></p>
                <textarea id="resolveViolationNotes" class="form-control form-control-sm" rows="3" maxlength="500" required
                          placeholder="Ex: ajustado no banco de horas, conversado com o colaborador..."></textarea>
            </div>
            <div class="modal-footer border-0 gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-info btn-sm" id="btnConfirmResolveViolation">
                    <i class="bi bi-check2-square me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/csrf-fetch.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?>>
(() => {
    'use strict';

    const xEsc     = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    const baseUrl  = '<?= site_url('manager/pending-punches/') ?>';
    const csrfName = '<?= csrf_token() ?>';
    let   csrfHash = '<?= csrf_hash() ?>';

    // Instancia modais de forma lazy para evitar erro se Bootstrap ainda não carregou
    const getModalApprove = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('approveModal'));
    const getModalReject  = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('rejectModal'));
    const getModalResolveViolation = () => bootstrap.Modal.getOrCreateInstance(document.getElementById('resolveViolationModal'));
    const feedbackEl      = document.getElementById('panelFeedback');
    let   pendingId       = null;
    let   violationId     = null;

    function showFeedback(msg, type) {
        const icons = {success:'check-circle-fill', danger:'x-circle-fill', warning:'exclamation-triangle-fill'};
        feedbackEl.innerHTML = `<div class="alert alert-${type} d-flex gap-2 align-items-start mb-3">
            <i class="bi bi-${icons[type] ?? 'info-circle-fill'} flex-shrink-0 mt-1"></i>
            <div>${msg}</div></div>`;
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    async function doAction(url, notes) {
        const body = new URLSearchParams();
        body.append('review_notes', notes);
        body.append(csrfName, csrfHash);

        const resp = await spFetch(url, {
            method  : 'POST',
            headers : {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'},
            redirect: 'error',
            body,
        });

        let json;
        try {
            json = await resp.json();
        } catch {
            // Resposta não é JSON (ex: página de erro HTML do servidor)
            json = { success: false, message: `Erro ${resp.status} — tente novamente ou contate o suporte.` };
        }
        if (json.csrf_hash) csrfHash = json.csrf_hash;
        return json;
    }

    // ── Aprovar ──────────────────────────────────────────────────────────────
    document.querySelectorAll('.js-approve-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            pendingId = btn.dataset.id;
            document.getElementById('approveEmployeeName').textContent = btn.dataset.name;
            document.getElementById('approveNotes').value = '';
            getModalApprove().show();
        });
    });

    document.getElementById('btnConfirmApprove')?.addEventListener('click', async function () {
        if (!pendingId) return;
        const orig = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aprovando...';
        try {
            const data = await doAction(baseUrl + pendingId + '/approve',
                                        document.getElementById('approveNotes').value.trim());
            getModalApprove().hide();
            if (data.success) {
                showFeedback(xEsc(data.message ?? 'Ponto aprovado e efetivado.'), 'success');
                setTimeout(() => location.reload(), 1400);
            } else {
                showFeedback(xEsc(data.message ?? 'Não foi possível aprovar.'), 'danger');
            }
        } catch { getModalApprove().hide(); showFeedback('Falha de comunicação. Tente novamente.', 'danger'); }
        this.disabled = false; this.innerHTML = orig;
    });

    // ── Rejeitar ─────────────────────────────────────────────────────────────
    document.querySelectorAll('.js-reject-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            pendingId = btn.dataset.id;
            document.getElementById('rejectEmployeeName').textContent = btn.dataset.name;
            document.getElementById('rejectNotes').value = '';
            document.getElementById('rejectNotes').classList.remove('is-invalid');
            getModalReject().show();
        });
    });

    document.getElementById('btnConfirmReject')?.addEventListener('click', async function () {
        if (!pendingId) return;
        const notes = document.getElementById('rejectNotes').value.trim();
        if (!notes) {
            document.getElementById('rejectNotes').classList.add('is-invalid');
            return;
        }
        const orig = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Rejeitando...';
        try {
            const data = await doAction(baseUrl + pendingId + '/reject', notes);
            getModalReject().hide();
            if (data.success) {
                showFeedback(xEsc(data.message ?? 'Registro rejeitado.'), 'success');
                setTimeout(() => location.reload(), 1400);
            } else {
                showFeedback(xEsc(data.message ?? 'Não foi possível rejeitar.'), 'danger');
            }
        } catch { getModalReject().hide(); showFeedback('Falha de comunicação. Tente novamente.', 'danger'); }
        this.disabled = false; this.innerHTML = orig;
    });

    // ── Marcar irregularidade como tratada ──────────────────────────────────────
    document.querySelectorAll('.js-resolve-violation-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            violationId = btn.dataset.id;
            document.getElementById('resolveViolationEmployeeName').textContent = btn.dataset.name;
            document.getElementById('resolveViolationNotes').value = '';
            document.getElementById('resolveViolationNotes').classList.remove('is-invalid');
            getModalResolveViolation().show();
        });
    });

    document.getElementById('btnConfirmResolveViolation')?.addEventListener('click', async function () {
        if (!violationId) return;
        const notes = document.getElementById('resolveViolationNotes').value.trim();
        if (!notes) {
            document.getElementById('resolveViolationNotes').classList.add('is-invalid');
            return;
        }
        const orig = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';
        try {
            const data = await doAction(baseUrl + 'violations/' + violationId + '/resolve', notes);
            getModalResolveViolation().hide();
            if (data.success) {
                showFeedback(xEsc(data.message ?? 'Irregularidade marcada como tratada.'), 'success');
                setTimeout(() => location.reload(), 1400);
            } else {
                showFeedback(xEsc(data.message ?? 'Não foi possível atualizar.'), 'danger');
            }
        } catch { getModalResolveViolation().hide(); showFeedback('Falha de comunicação. Tente novamente.', 'danger'); }
        this.disabled = false; this.innerHTML = orig;
    });

})();
</script>
<?= $this->endSection() ?>
