<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>LGPD Operacional<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('biometric/partials/_biometric_compliance_bridge') ?>
<?php
$cards      = $cards      ?? [];
$guidelines = $guidelines ?? [];
$requests   = $requests   ?? [];

$typeLabels = [
    'access'          => 'Acesso (Art. 18, I)',
    'export'          => 'Portabilidade (Art. 19)',
    'correction'      => 'Correção (Art. 18, III)',
    'anonymization'   => 'Anonimização (Art. 18, IV)',
    'deactivation'    => 'Desativação (Art. 18, VI)',
    'biometric_purge' => 'Expurgo Biométrico',
];
$statusBadge = [
    'pending'  => 'bg-warning text-dark',
    'resolved' => 'bg-success',
    'rejected' => 'bg-danger',
];
$pending = array_filter($requests, fn($r) => ($r->status ?? '') === 'pending');
?>

<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'LGPD Operacional',
        'subtitle' => 'Gestão de consentimentos, solicitações de titulares e conformidade LGPD.',
        'icon'     => 'bi bi-person-lock',
        'actions'  => [
            ['label' => 'Biometria',  'icon' => 'bi bi-fingerprint',      'url' => site_url('biometric/manage')],
            ['label' => 'Auditoria',  'icon' => 'bi bi-shield-lock-fill', 'url' => site_url('compliance/audit-advanced')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- Contadores -->
    <div class="row g-3 mb-4">
        <?php foreach ($cards as $card): ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-4 fw-bold text-primary"><?= esc($card['value']) ?></div>
                <div class="small text-muted"><?= esc($card['label']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3 <?= count($pending) > 0 ? 'border-start border-warning border-3' : '' ?>">
                <div class="fs-4 fw-bold <?= count($pending) > 0 ? 'text-warning' : 'text-success' ?>"><?= count($pending) ?></div>
                <div class="small text-muted">Solicitações pendentes</div>
            </div>
        </div>
    </div>

    <!-- Fila de solicitações de titulares -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0">
                <i class="bi bi-list-check me-2"></i>Solicitações de Titulares (Art. 18 LGPD)
                <?php if (count($pending) > 0): ?>
                <span class="badge bg-warning text-dark ms-2"><?= count($pending) ?> pendente(s)</span>
                <?php endif; ?>
            </h6>
        </div>
        <?php if (empty($requests)): ?>
        <div class="card-body text-center text-muted py-4">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>Nenhuma solicitação registrada.
        </div>
        <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small" id="tbl-requests">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Protocolo</th>
                            <th>Titular</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Solicitado em</th>
                            <th>Prazo</th>
                            <th class="pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <?php $overdue = ($req->status === 'pending' && !empty($req->due_at) && strtotime($req->due_at) < time()); ?>
                        <tr class="<?= $overdue ? 'table-danger' : '' ?>">
                            <td class="ps-3 font-monospace small"><?= esc($req->request_id) ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($req->employee_name ?? '—') ?></div>
                                <div class="text-muted"><?= esc($req->employee_cpf ?? '') ?></div>
                            </td>
                            <td><?= esc($typeLabels[$req->request_type] ?? $req->request_type) ?></td>
                            <td><span class="badge <?= $statusBadge[$req->status] ?? 'bg-secondary' ?>"><?= esc(ucfirst($req->status)) ?></span></td>
                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($req->created_at)) ?></td>
                            <td class="<?= $overdue ? 'fw-semibold text-danger' : 'text-muted' ?>">
                                <?= !empty($req->due_at) ? date('d/m/Y', strtotime($req->due_at)) : '—' ?>
                                <?= $overdue ? '<i class="bi bi-exclamation-triangle-fill ms-1"></i>' : '' ?>
                            </td>
                            <td class="pe-3">
                                <?php if ($req->status === 'pending'): ?>
                                <button class="btn btn-sm btn-outline-success me-1 btn-resolve"
                                        data-request-id="<?= esc($req->request_id) ?>"
                                        data-employee-id="<?= (int) $req->employee_id ?>"
                                        data-action="resolved">
                                    <i class="bi bi-check-circle"></i> Resolver
                                </button>
                                <button class="btn btn-sm btn-outline-danger btn-resolve"
                                        data-request-id="<?= esc($req->request_id) ?>"
                                        data-employee-id="<?= (int) $req->employee_id ?>"
                                        data-action="rejected">
                                    <i class="bi bi-x-circle"></i> Rejeitar
                                </button>
                                <?php else: ?>
                                <span class="text-muted small"><?= !empty($req->resolved_at) ? date('d/m/Y', strtotime($req->resolved_at)) : '—' ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Diretrizes operacionais -->
    <div class="sp-surface-card">
        <div class="sp-surface-card__header">
            <h2 class="sp-profile-card__title"><i class="bi bi-check2-square"></i>Diretrizes operacionais</h2>
        </div>
        <div class="sp-surface-card__body">
            <div class="sp-compliance-checklist">
                <?php foreach ($guidelines as $item): ?>
                    <div class="sp-compliance-item">
                        <strong><?= esc($item['title']) ?></strong>
                        <div><?= esc($item['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Resolver / Rejeitar solicitação -->
<div class="modal fade" id="modalResolve" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resolve-title"><i class="bi bi-check-circle me-2"></i>Resolver Solicitação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Protocolo: <strong id="resolve-protocol"></strong></p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Notas de resolução <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="resolve-notes" rows="4" placeholder="Descreva a ação tomada..."></textarea>
                </div>
                <input type="hidden" id="resolve-request-id">
                <input type="hidden" id="resolve-employee-id">
                <input type="hidden" id="resolve-action">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="resolve-confirm">
                    <i class="bi bi-check-circle me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
    <div id="sp-toast" class="toast align-items-center text-white border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body fw-semibold" id="sp-toast-msg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(function () {
    const CSRF = '<?= csrf_token() ?>';
    let csrfHash = '<?= csrf_hash() ?>';

    function toast(msg, type) {
        const el = document.getElementById('sp-toast');
        el.className = 'toast align-items-center text-white border-0 bg-' + (type || 'success');
        document.getElementById('sp-toast-msg').textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, {delay: 4000}).show();
    }

    async function post(url, data) {
        data[CSRF] = csrfHash;
        const r = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(data),
        });
        const json = await r.json();
        if (json.csrf_hash) csrfHash = json.csrf_hash;
        return json;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalResolve'));

    document.querySelectorAll('.btn-resolve').forEach(btn => {
        btn.addEventListener('click', function () {
            const action = this.dataset.action;
            document.getElementById('resolve-request-id').value  = this.dataset.requestId;
            document.getElementById('resolve-employee-id').value = this.dataset.employeeId;
            document.getElementById('resolve-action').value      = action;
            document.getElementById('resolve-protocol').textContent = this.dataset.requestId;
            document.getElementById('resolve-title').innerHTML = action === 'resolved'
                ? '<i class="bi bi-check-circle-fill text-success me-2"></i>Resolver Solicitação'
                : '<i class="bi bi-x-circle-fill text-danger me-2"></i>Rejeitar Solicitação';
            document.getElementById('resolve-confirm').className = action === 'resolved'
                ? 'btn btn-success' : 'btn btn-danger';
            document.getElementById('resolve-notes').value = '';
            modal.show();
        });
    });

    document.getElementById('resolve-confirm').addEventListener('click', async function () {
        const notes      = document.getElementById('resolve-notes').value.trim();
        const requestId  = document.getElementById('resolve-request-id').value;
        const employeeId = document.getElementById('resolve-employee-id').value;
        const action     = document.getElementById('resolve-action').value;

        if (!notes) { document.getElementById('resolve-notes').focus(); toast('Informe as notas de resolução.', 'warning'); return; }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando...';

        try {
            const json = await post(`<?= site_url('lgpd/admin/employee/') ?>${employeeId}/resolve-request`, {
                request_id: requestId,
                status: action,
                resolution_notes: notes,
            });
            modal.hide();
            toast(json.message || (json.success ? 'Concluído.' : 'Erro.'), json.success ? 'success' : 'danger');
            if (json.success) setTimeout(() => location.reload(), 1500);
        } catch (e) {
            toast('Erro de comunicação.', 'danger');
        }

        this.disabled = false;
        this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirmar';
    });
})();
</script>

<?= $this->endSection() ?>
