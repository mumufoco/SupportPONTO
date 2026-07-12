<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Controle de Colaboradores<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$lgpdRequests = $lgpdRequests ?? [];
$lgpdStats    = $lgpdStats    ?? ['pending' => 0, 'resolved' => 0, 'overdue' => 0, 'by_type' => []];
$typeLabels   = [
    'biometric_purge' => 'Revisão Biométrica',
    'deactivation'    => 'Análise de Desativação',
    'access'          => 'Acesso aos Dados',
    'correction'      => 'Correção de Dados',
    'anonymization'   => 'Anonimização',
    'export'          => 'Exportação de Dados',
];
$statusBadge = [
    'pending'   => ['bg-warning text-dark', 'Pendente'],
    'in_review' => ['bg-info text-dark',    'Em análise'],
    'resolved'  => ['bg-success',           'Resolvido'],
    'completed' => ['bg-success',           'Concluído'],
    'rejected'  => ['bg-danger',            'Rejeitado'],
];
?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Controle de Colaboradores',
        'subtitle' => 'Visão geral de biometria, acesso e solicitações LGPD dos colaboradores.',
        'icon'     => 'bi bi-people-fill',
        'actions'  => [],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- KPIs colaboradores -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-primary"><?= $stats['total'] ?></div>
                    <div class="small text-muted">Total colaboradores</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-success"><?= $stats['active'] ?></div>
                    <div class="small text-muted">Ativos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-info"><?= $stats['with_face'] ?></div>
                    <div class="small text-muted">Com biometria facial</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-warning"><?= $stats['with_fingerprint'] ?></div>
                    <div class="small text-muted">Com digital cadastrada</div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPIs LGPD -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-shield-lock-fill text-primary"></i>
                <span class="small fw-bold text-uppercase text-muted">Solicitações LGPD</span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm h-100 <?= $lgpdStats['pending'] > 0 ? 'border-warning border-2 border' : '' ?>">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold <?= $lgpdStats['pending'] > 0 ? 'text-warning' : 'text-muted' ?>">
                        <?= $lgpdStats['pending'] ?>
                    </div>
                    <div class="small text-muted">Pendentes</div>
                    <?php if ($lgpdStats['pending'] > 0): ?>
                    <a href="#lgpd-requests" class="stretched-link"></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm h-100 <?= $lgpdStats['overdue'] > 0 ? 'border-danger border-2 border' : '' ?>">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold <?= $lgpdStats['overdue'] > 0 ? 'text-danger' : 'text-muted' ?>">
                        <?= $lgpdStats['overdue'] ?>
                    </div>
                    <div class="small text-muted">Atrasadas</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-success"><?= $lgpdStats['resolved'] ?></div>
                    <div class="small text-muted">Resolvidas</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-secondary"><?= count($lgpdRequests) ?></div>
                    <div class="small text-muted">Total histórico</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabela colaboradores -->
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body py-2">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="text-muted small me-2"><i class="bi bi-funnel me-1"></i>Filtrar:</span>
                <button class="btn btn-sm btn-outline-secondary sp-filter-btn active" data-filter="all">Todos</button>
                <button class="btn btn-sm btn-outline-success sp-filter-btn" data-filter="active">Ativos</button>
                <button class="btn btn-sm btn-outline-danger sp-filter-btn" data-filter="inactive">Inativos</button>
                <button class="btn btn-sm btn-outline-info sp-filter-btn" data-filter="no-face">Sem facial</button>
                <button class="btn btn-sm btn-outline-warning sp-filter-btn" data-filter="no-fingerprint">Sem digital</button>
                <div class="ms-auto">
                    <input type="search" class="form-control form-control-sm" id="searchEmployee"
                           placeholder="Buscar colaborador..." style="min-width:200px">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Codigo Unico</th>
                            <th>QR Code</th>
                            <th>Facial</th>
                            <th>Digital</th>
                            <th>Departamento</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                        <tr class="sp-emp-row"
                            data-active="<?= $emp->active ? 'active' : 'inactive' ?>"
                            data-face="<?= $emp->has_face_biometric ? '1' : '0' ?>"
                            data-fingerprint="<?= $emp->has_fingerprint_biometric ? '1' : '0' ?>"
                            data-name="<?= mb_strtolower(esc($emp->name)) ?>">
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                         style="width:36px;height:36px;font-size:.9rem">
                                        <?= mb_substr($emp->name, 0, 1) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold small"><?= esc($emp->name) ?></div>
                                        <div class="text-muted" style="font-size:.75rem"><?= esc($emp->email) ?></div>
                                        <?php $__rb = match($emp->role ?? '') {
                                            'gestor' => ['Gestor',      'text-bg-info'],
                                            'rh'     => ['RH',          'text-bg-warning'],
                                            default  => ['Funcionário', 'text-bg-secondary'],
                                        }; ?>
                                        <span class="badge <?= $__rb[1] ?>" style="font-size:.62rem;margin-top:2px"><?= $__rb[0] ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="bg-light px-2 py-1 rounded small"><?= esc($emp->unique_code) ?></code>
                                <button class="btn btn-sm btn-link p-0 ms-1 js-copy-code"
                                        data-code="<?= esc($emp->unique_code) ?>"
                                        title="Copiar codigo">
                                    <i class="bi bi-copy text-muted"></i>
                                </button>
                            </td>
                            <td>
                                <a href="<?= site_url('employees/' . $emp->id . '/qrcode') ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Ver QR Code">
                                    <i class="bi bi-qr-code-scan"></i>
                                </a>
                                <a href="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode((string)$emp->unique_code) ?>&format=png&download=1"
                                   class="btn btn-sm btn-outline-primary" title="Baixar QR Code PNG"
                                   download="qrcode_<?= esc($emp->unique_code) ?>.png"
                                   target="_blank">
                                    <i class="bi bi-download"></i>
                                </a>
                            </td>
                            <td>
                                <?php if ($emp->has_face_biometric): ?>
                                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Cadastrada</span>
                                <?php else: ?>
                                    <a href="<?= site_url('biometric/enroll-for/' . $emp->id) ?>"
                                       class="badge text-bg-warning text-decoration-none">
                                        <i class="bi bi-plus-circle me-1"></i>Cadastrar
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emp->has_fingerprint_biometric): ?>
                                    <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Cadastrada</span>
                                <?php else: ?>
                                    <a href="<?= site_url('biometric/fingerprint/enroll/' . $emp->id) ?>"
                                       class="badge text-bg-warning text-decoration-none">
                                        <i class="bi bi-plus-circle me-1"></i>Cadastrar
                                    </a>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= esc($emp->department ?? '&mdash;') ?></td>
                            <td>
                                <?php if ($emp->active): ?>
                                    <span class="badge text-bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end">
                                    <a href="<?= site_url('employees/' . $emp->id) ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Ver ficha">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?= site_url('employees/' . $emp->id . '/edit') ?>"
                                       class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="<?= site_url('timesheet/employee/' . $emp->id) ?>"
                                       class="btn btn-sm btn-outline-info" title="Espelho de ponto">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Solicitações LGPD -->
    <div class="card border-0 shadow-sm" id="lgpd-requests">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill text-primary"></i>
                <h6 class="fw-bold mb-0">Solicitações LGPD dos Colaboradores</h6>
                <?php if ($lgpdStats['pending'] > 0): ?>
                <span class="badge bg-warning text-dark"><?= $lgpdStats['pending'] ?> pendente<?= $lgpdStats['pending'] > 1 ? 's' : '' ?></span>
                <?php endif; ?>
                <?php if ($lgpdStats['overdue'] > 0): ?>
                <span class="badge bg-danger"><?= $lgpdStats['overdue'] ?> atrasada<?= $lgpdStats['overdue'] > 1 ? 's' : '' ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <select id="lgpd-filter-status" class="form-select form-select-sm" style="width:auto;">
                    <option value="">Todos os status</option>
                    <option value="pending">Pendentes</option>
                    <option value="in_review">Em análise</option>
                    <option value="resolved">Resolvidas</option>
                    <option value="rejected">Rejeitadas</option>
                </select>
                <select id="lgpd-filter-type" class="form-select form-select-sm" style="width:auto;">
                    <option value="">Todos os tipos</option>
                    <?php foreach ($typeLabels as $k => $v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (empty($lgpdRequests)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-shield-check fs-1 d-block mb-2 opacity-50"></i>
                <p class="fw-semibold mb-0">Nenhuma solicitação LGPD registrada</p>
                <p class="small">Colaboradores podem solicitar revisão de dados na página LGPD.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small" id="lgpd-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Prazo</th>
                            <th>Status</th>
                            <th>Recebida em</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lgpdRequests as $r): ?>
                        <?php
                            [$badgeClass, $badgeLabel] = $statusBadge[$r->status] ?? ['bg-secondary', $r->status];
                            $isOverdue = $r->due_at && strtotime($r->due_at) < time() && $r->status === 'pending';
                            $isPending = $r->status === 'pending' || $r->status === 'in_review';
                        ?>
                        <tr class="lgpd-row" data-status="<?= esc($r->status) ?>" data-type="<?= esc($r->request_type) ?>">
                            <td class="ps-3">
                                <div class="fw-semibold"><?= esc($r->employee_name ?? '-') ?></div>
                                <div class="text-muted" style="font-size:.75rem"><?= esc($r->employee_email ?? '') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-secondary rounded-pill">
                                    <?= esc($typeLabels[$r->request_type] ?? $r->request_type) ?>
                                </span>
                            </td>
                            <td class="text-muted" style="max-width:200px;">
                                <span title="<?= esc($r->reason ?? '') ?>">
                                    <?= esc(mb_strimwidth($r->reason ?? '-', 0, 60, '…')) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($r->due_at): ?>
                                    <span class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                        <?php if ($isOverdue): ?><i class="bi bi-exclamation-triangle me-1"></i><?php endif; ?>
                                        <?= date('d/m/Y', strtotime($r->due_at)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?> rounded-pill"><?= $badgeLabel ?></span>
                            </td>
                            <td class="text-muted">
                                <?= $r->created_at ? date('d/m/Y H:i', strtotime($r->created_at)) : '-' ?>
                            </td>
                            <td class="text-end pe-3">
                                <?php if ($isPending): ?>
                                <div class="d-flex gap-1 justify-content-end">
                                    <?php if ($r->request_type === 'biometric_purge'): ?>
                                    <button class="btn btn-sm btn-outline-warning lgpd-action"
                                            data-action="purge_biometrics"
                                            data-employee-id="<?= (int)$r->employee_id ?>"
                                            data-request-id="<?= esc($r->request_id) ?>"
                                            data-name="<?= esc($r->employee_name) ?>"
                                            title="Executar purga biométrica">
                                        <i class="bi bi-fingerprint"></i>
                                    </button>
                                    <?php elseif ($r->request_type === 'deactivation'): ?>
                                    <button class="btn btn-sm btn-outline-danger lgpd-action"
                                            data-action="deactivate"
                                            data-employee-id="<?= (int)$r->employee_id ?>"
                                            data-request-id="<?= esc($r->request_id) ?>"
                                            data-name="<?= esc($r->employee_name) ?>"
                                            title="Desativar colaborador">
                                        <i class="bi bi-person-slash"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-success lgpd-resolve"
                                            data-employee-id="<?= (int)$r->employee_id ?>"
                                            data-request-id="<?= esc($r->request_id) ?>"
                                            data-name="<?= esc($r->employee_name) ?>"
                                            title="Marcar como resolvida">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-secondary lgpd-reject"
                                            data-employee-id="<?= (int)$r->employee_id ?>"
                                            data-request-id="<?= esc($r->request_id) ?>"
                                            data-name="<?= esc($r->employee_name) ?>"
                                            title="Rejeitar solicitação">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="text-muted small">
                                    <?= $r->resolved_at ? date('d/m/Y', strtotime($r->resolved_at)) : '—' ?>
                                </span>
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

<!-- Modal: Resolver/Rejeitar solicitação -->
<div class="modal fade" id="modalLgpdResolve" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lgpdModalTitle">Resolver Solicitação LGPD</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Colaborador: <strong id="lgpdModalName"></strong></p>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Notas de resolução <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="lgpdResolutionNotes" rows="4"
                              placeholder="Descreva o que foi feito para resolver esta solicitação..."></textarea>
                </div>
                <input type="hidden" id="lgpdEmployeeId">
                <input type="hidden" id="lgpdRequestId">
                <input type="hidden" id="lgpdAction">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="lgpdConfirm">
                    <i class="bi bi-check-circle me-1"></i>Confirmar
                </button>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {

    // ── Filtros colaboradores ──────────────────────────────────────────
    var rows = document.querySelectorAll('.sp-emp-row');
    var filterBtns = document.querySelectorAll('.sp-filter-btn');
    var searchInput = document.getElementById('searchEmployee');
    var activeFilter = 'all';
    var searchTerm = '';
    function applyFilters() {
        rows.forEach(function(row) {
            var show = true;
            if (activeFilter === 'active'          && row.dataset.active !== 'active')   show = false;
            if (activeFilter === 'inactive'        && row.dataset.active !== 'inactive') show = false;
            if (activeFilter === 'no-face'         && row.dataset.face === '1')          show = false;
            if (activeFilter === 'no-fingerprint'  && row.dataset.fingerprint === '1')   show = false;
            if (searchTerm && (row.dataset.name || '').indexOf(searchTerm) === -1)       show = false;
            row.style.display = show ? '' : 'none';
        });
    }
    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            applyFilters();
        });
    });
    searchInput && searchInput.addEventListener('input', function () {
        searchTerm = this.value.toLowerCase();
        applyFilters();
    });

    // ── Filtros LGPD ──────────────────────────────────────────────────
    function applyLgpdFilters() {
        var status = document.getElementById('lgpd-filter-status').value;
        var type   = document.getElementById('lgpd-filter-type').value;
        document.querySelectorAll('.lgpd-row').forEach(function(row) {
            var show = true;
            if (status && row.dataset.status !== status) show = false;
            if (type   && row.dataset.type   !== type)   show = false;
            row.style.display = show ? '' : 'none';
        });
    }
    document.getElementById('lgpd-filter-status')?.addEventListener('change', applyLgpdFilters);
    document.getElementById('lgpd-filter-type')?.addEventListener('change', applyLgpdFilters);

    // ── Modal LGPD ────────────────────────────────────────────────────
    var modal = new bootstrap.Modal(document.getElementById('modalLgpdResolve'));

    function openLgpdModal(title, action, employeeId, requestId, name) {
        document.getElementById('lgpdModalTitle').textContent    = title;
        document.getElementById('lgpdModalName').textContent     = name;
        document.getElementById('lgpdEmployeeId').value          = employeeId;
        document.getElementById('lgpdRequestId').value           = requestId;
        document.getElementById('lgpdAction').value              = action;
        document.getElementById('lgpdResolutionNotes').value     = '';
        modal.show();
    }

    document.querySelectorAll('.lgpd-resolve').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openLgpdModal('Resolver Solicitação', 'resolve', btn.dataset.employeeId, btn.dataset.requestId, btn.dataset.name);
        });
    });
    document.querySelectorAll('.lgpd-reject').forEach(function(btn) {
        btn.addEventListener('click', function() {
            openLgpdModal('Rejeitar Solicitação', 'reject', btn.dataset.employeeId, btn.dataset.requestId, btn.dataset.name);
        });
    });
    document.querySelectorAll('.lgpd-action').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var action = btn.dataset.action;
            var title  = action === 'purge_biometrics' ? 'Executar Purga Biométrica' : 'Desativar Colaborador';
            openLgpdModal(title, action, btn.dataset.employeeId, btn.dataset.requestId, btn.dataset.name);
        });
    });

    // ── Confirmar ação ────────────────────────────────────────────────
    document.getElementById('lgpdConfirm').addEventListener('click', async function() {
        var notes      = document.getElementById('lgpdResolutionNotes').value.trim();
        var action     = document.getElementById('lgpdAction').value;
        var employeeId = document.getElementById('lgpdEmployeeId').value;
        var requestId  = document.getElementById('lgpdRequestId').value;

        if (!notes) {
            document.getElementById('lgpdResolutionNotes').focus();
            document.getElementById('lgpdResolutionNotes').classList.add('is-invalid');
            return;
        }
        document.getElementById('lgpdResolutionNotes').classList.remove('is-invalid');

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processando...';

        var CSRF   = '<?= csrf_token() ?>';
        var csrfH  = '<?= csrf_hash() ?>';
        var urlMap = {
            purge_biometrics: '<?= site_url('lgpd/admin/employee/') ?>' + employeeId + '/purge-biometrics',
            deactivate:       '<?= site_url('lgpd/admin/employee/') ?>' + employeeId + '/deactivate',
            resolve:          '<?= site_url('lgpd/admin/employee/') ?>' + employeeId + '/resolve-request',
            reject:           '<?= site_url('lgpd/admin/employee/') ?>' + employeeId + '/resolve-request',
        };

        var body = new URLSearchParams({ reason: notes, resolution_notes: notes, request_id: requestId });
        if (action === 'reject') body.append('status', 'rejected');
        if (action === 'purge_biometrics') body.append('types', 'face,fingerprint');
        body.append(CSRF, csrfH);

        try {
            var res  = await spFetch(urlMap[action], { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() });
            var json = await res.json();
            modal.hide();
            showToast(json.success ? 'Ação executada com sucesso.' : (json.message || 'Erro ao processar.'), json.success);
            if (json.success) setTimeout(() => location.reload(), 1500);
        } catch(e) {
            showToast('Erro de comunicação com o servidor.', false);
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirmar';
    });

    // Copy unique code buttons
    document.querySelectorAll('.js-copy-code').forEach(function(btn) {
        btn.addEventListener('click', function() {
            copyCode(this.dataset.code);
        });
    });

});

function copyCode(code) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(function() { showToast('Copiado: ' + code, true); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = code; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta); showToast('Copiado: ' + code, true);
    }
}
function showToast(msg, ok) {
    var t = document.createElement('div');
    t.className = 'position-fixed bottom-0 end-0 m-3 alert ' + (ok !== false ? 'alert-success' : 'alert-danger') + ' shadow';
    t.style.zIndex = '9999'; t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function() { t.remove(); }, 3000);
}
</script>
<?= $this->endSection() ?>
