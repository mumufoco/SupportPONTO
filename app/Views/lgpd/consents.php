<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>LGPD — Consentimentos<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$employee     = $employee     ?? [];
$consents     = $consents     ?? ['active' => [], 'revoked' => [], 'pending' => [], 'all' => []];
$consentTypes = $consentTypes ?? [];
$consentTerms = $consentTerms ?? [];
$subjectRequests = $subjectRequests ?? [];

// Helper: find consent record for a given type
$findConsent = function (array $list, string $type): ?object {
    foreach ($list as $c) {
        if (($c->consent_type ?? '') === $type) return $c;
    }
    return null;
};

$dpoEmail = env('DPO_EMAIL', 'dpo@supportsondagens.com.br');
$pendingRequestsCount = count(array_filter($subjectRequests, static fn($r) => ($r->status ?? '') === 'pending'));
?>
<script>
const CONSENT_TERMS = <?= json_encode(array_map(function($t) {
    return ['title' => $t['title'] ?? '', 'body' => $t['body'] ?? '', 'version' => $t['version'] ?? '1.0'];
}, $consentTerms), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'LGPD — Meus Dados e Consentimentos',
        'subtitle' => 'Gerencie seus consentimentos e exerça seus direitos como titular de dados conforme a Lei 13.709/2018.',
        'icon'     => 'bi bi-shield-check',
    ]) ?>

    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div id="sp-toast" class="toast align-items-center text-white border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="sp-toast-msg"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <div class="sp-grid-4 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Consentimentos ativos</div>
                <div class="stat-card-value"><?= count($consents['active']) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Pendentes</div>
                <div class="stat-card-value"><?= count($consents['pending'] ?? []) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-send-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Solicitações em andamento</div>
                <div class="stat-card-value"><?= $pendingRequestsCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Revogados</div>
                <div class="stat-card-value"><?= count($consents['revoked']) ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8 d-flex flex-column gap-3">

            <!-- Meus Consentimentos -->
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-toggles"></i></span>
                        Meus consentimentos
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <div class="row g-3">
                        <?php foreach ($consentTypes as $type => $info): ?>
                        <?php
                            $active   = $findConsent($consents['active'],  $type);
                            $revoked  = $findConsent($consents['revoked'], $type);
                            $hasConsent = $active !== null;
                            $isPending  = in_array($type, $consents['pending'] ?? []);
                            $isRequired = $info['required'] ?? false;
                            $isSensitive = in_array($type, ['biometric_face', 'biometric_fingerprint']);
                            $wasRevoked = $revoked !== null && !empty($revoked->revoked_at);

                            $statusBadge = 'sp-badge-neutral';
                            $statusLabel = 'Não concedido';
                            $statusIcon  = 'bi-dash-circle';
                            if ($hasConsent) {
                                $statusBadge = 'sp-badge-success'; $statusLabel = 'Ativo'; $statusIcon = 'bi-check-circle-fill';
                            } elseif ($isPending) {
                                $statusBadge = 'sp-badge-warning'; $statusLabel = 'Pendente'; $statusIcon = 'bi-clock-fill';
                            } elseif ($wasRevoked) {
                                $statusBadge = 'sp-badge-danger'; $statusLabel = 'Revogado'; $statusIcon = 'bi-x-circle-fill';
                            }
                        ?>
                        <div class="col-md-6">
                            <div class="border rounded-3 p-3 h-100 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="d-flex gap-2 align-items-center">
                                        <i class="bi <?= $isSensitive ? 'bi-fingerprint' : ($type === 'geolocation' ? 'bi-geo-alt-fill' : 'bi-person-fill-lock') ?> text-primary"></i>
                                        <span class="fw-semibold small"><?= esc($info['label']) ?></span>
                                    </div>
                                    <span class="sp-badge <?= $statusBadge ?>"><i class="bi <?= $statusIcon ?> me-1"></i><?= $statusLabel ?></span>
                                </div>

                                <?php if ($isRequired): ?>
                                    <span class="sp-badge sp-badge-neutral align-self-start mb-2" style="font-size:.65rem;">Obrigatório</span>
                                <?php endif; ?>

                                <p class="small text-muted mb-1 flex-grow-1"><?= esc($info['purpose']) ?></p>
                                <p class="small mb-2"><span class="text-muted">Base legal:</span> <?= esc($info['legal_basis']) ?></p>

                                <?php if ($active): ?>
                                <div class="small text-muted mb-2"><i class="bi bi-calendar-check me-1 text-success"></i>Aceito em <?= esc(format_datetime_br((string) $active->granted_at, false)) ?><?= $active->version ? ' · v' . esc($active->version) : '' ?></div>
                                <?php elseif ($wasRevoked): ?>
                                <div class="small text-danger mb-2"><i class="bi bi-calendar-x me-1"></i>Revogado em <?= esc(format_datetime_br((string) $revoked->revoked_at, false)) ?></div>
                                <?php endif; ?>

                                <?php if ($hasConsent && !$isRequired): ?>
                                    <button class="btn btn-sm btn-outline-danger mt-auto sp-consent-revoke" data-type="<?= esc($type) ?>" data-label="<?= esc($info['label']) ?>">
                                        <i class="bi bi-x-circle me-1"></i>Revogar
                                    </button>
                                <?php elseif ($hasConsent && $isRequired): ?>
                                    <div class="small text-muted mt-auto"><i class="bi bi-lock me-1"></i>Obrigatório para uso do sistema</div>
                                <?php elseif ($type === 'biometric_face' || $type === 'biometric_fingerprint'): ?>
                                    <div class="small text-muted mt-auto"><i class="bi bi-info-circle me-1"></i>Aceito via processo de cadastro biométrico</div>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-success mt-auto sp-consent-grant"
                                            data-type="<?= esc($type) ?>" data-label="<?= esc($info['label']) ?>"
                                            data-purpose="<?= esc($info['purpose']) ?>" data-legal="<?= esc($info['legal_basis']) ?>">
                                        <i class="bi bi-check-circle me-1"></i>Conceder consentimento
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Minhas Solicitações -->
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(25,135,84,.12);color:#198754;"><i class="bi bi-list-check"></i></span>
                        Minhas solicitações
                    </h2>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="btn-export-data">
                            <i class="bi bi-download me-1"></i>Exportar meus dados
                        </button>
                        <button class="btn btn-sm btn-primary" id="btn-new-request">
                            <i class="bi bi-plus-circle me-1"></i>Nova solicitação
                        </button>
                    </div>
                </div>
                <?php if (empty($subjectRequests)): ?>
                    <div class="sp-data-card__body">
                        <div class="sp-empty">
                            <div class="sp-empty-icon"><i class="bi bi-inbox"></i></div>
                            <p class="sp-empty-title">Nenhuma solicitação registrada</p>
                            <p class="text-muted small mb-0">Use "Nova solicitação" para exercer seus direitos de acesso, correção, revisão biométrica ou desativação.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Protocolo</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Solicitado em</th>
                                    <th>Prazo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $reqTypeLabels = [
                                    'access' => 'Acesso', 'export' => 'Portabilidade',
                                    'correction' => 'Correção', 'anonymization' => 'Anonimização',
                                    'deactivation' => 'Desativação', 'biometric_purge' => 'Expurgo Biométrico',
                                ];
                                $reqStatusBadge = ['pending' => 'sp-badge-warning', 'resolved' => 'sp-badge-success', 'rejected' => 'sp-badge-danger'];
                                foreach ($subjectRequests as $req):
                                    $overdue = !empty($req->due_at) && strtotime($req->due_at) < time() && $req->status === 'pending';
                                ?>
                                <tr>
                                    <td class="font-monospace small"><?= esc($req->request_id) ?></td>
                                    <td><?= esc($reqTypeLabels[$req->request_type] ?? $req->request_type) ?></td>
                                    <td><span class="sp-badge <?= esc($reqStatusBadge[$req->status] ?? 'sp-badge-neutral') ?>"><?= esc(ucfirst($req->status)) ?></span></td>
                                    <td class="text-muted small"><?= esc(format_datetime_br((string) $req->created_at, false)) ?></td>
                                    <td class="small <?= $overdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                        <?= !empty($req->due_at) ? esc(format_date_br((string) $req->due_at)) : '—' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-info-circle-fill"></i></span>
                        Seus direitos
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <div class="list-group list-group-flush">
                        <?php foreach ([
                            ['bi-eye',          'Acesso',        'Confirmar e acessar seus dados tratados'],
                            ['bi-pencil',       'Correção',      'Corrigir dados incompletos ou incorretos'],
                            ['bi-box-arrow-up', 'Portabilidade', 'Receber seus dados em formato estruturado'],
                            ['bi-x-circle',     'Revogação',     'Revogar consentimento a qualquer momento'],
                            ['bi-trash',        'Eliminação',    'Solicitar eliminação dos dados tratados'],
                        ] as [$icon, $title, $desc]): ?>
                        <div class="list-group-item px-0">
                            <i class="bi <?= $icon ?> text-primary me-2"></i>
                            <strong><?= $title ?></strong>
                            <small class="d-block text-muted"><?= $desc ?></small>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <div class="small text-muted">
                        <i class="bi bi-envelope me-1"></i>Dúvidas? Fale com o DPO:
                        <a href="mailto:<?= esc($dpoEmail) ?>"><?= esc($dpoEmail) ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico -->
    <?php if (!empty($consents['all'])): ?>
    <div class="sp-data-card mt-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(108,117,125,.12);color:#6c757d;"><i class="bi bi-clock-history"></i></span>
                Histórico de consentimentos
            </h2>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#history-collapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse" id="history-collapse">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Data</th>
                            <th>IP</th>
                            <th>Versão</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($consents['all'] as $consent): ?>
                        <tr>
                            <td><?= esc($consentTypes[$consent->consent_type]['label'] ?? $consent->consent_type) ?></td>
                            <td>
                                <?php if ($consent->granted && !$consent->revoked_at): ?>
                                    <span class="sp-badge sp-badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="sp-badge sp-badge-danger">Revogado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                                <?= esc(format_datetime_br((string) ($consent->revoked_at ?: $consent->granted_at), false)) ?>
                            </td>
                            <td class="text-muted small"><?= esc($consent->ip_address ?? '—') ?></td>
                            <td><span class="sp-badge sp-badge-neutral">v<?= esc($consent->version) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: Conceder Consentimento -->
<div class="modal fade" id="modalGrant" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check-circle-fill text-success me-2"></i>Conceder Consentimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="fw-bold mb-3" id="grant-label"></h6>
                <div class="mb-3">
                    <div class="small text-muted fw-semibold mb-1">Finalidade</div>
                    <p class="small mb-0" id="grant-purpose"></p>
                </div>
                <div class="mb-3">
                    <div class="small text-muted fw-semibold mb-1">Base Legal</div>
                    <p class="small mb-0" id="grant-legal"></p>
                </div>
                <div class="mb-3" id="grant-term-container" style="display:none;">
                    <div class="small text-muted fw-semibold mb-1">Termo Completo <span class="sp-badge sp-badge-warning">Leia antes de aceitar</span></div>
                    <pre id="grant-term-body" class="small p-2 bg-body-tertiary rounded border" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;font-family:inherit;font-size:.78rem;"></pre>
                    <div class="small text-muted mt-1"><i class="bi bi-tag me-1"></i>Versão: <span id="grant-term-version"></span></div>
                </div>
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" id="grant-check">
                    <label class="form-check-label small" for="grant-check">
                        Li e concordo com os termos acima. Estou ciente de que posso revogar este consentimento a qualquer momento.
                    </label>
                </div>
                <input type="hidden" id="grant-type">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="grant-confirm" disabled>
                    <i class="bi bi-check-circle me-1"></i>Confirmar Consentimento
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Revogar Consentimento -->
<div class="modal fade" id="modalRevoke" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-x-circle-fill text-danger me-2"></i>Revogar Consentimento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja revogar o consentimento para <strong id="revoke-label"></strong>?</p>
                <div class="alert alert-warning small py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>Os dados relacionados serão marcados para eliminação conforme a LGPD.
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Motivo (opcional)</label>
                    <textarea class="form-control form-control-sm" id="revoke-reason" rows="3" placeholder="Descreva o motivo da revogação..."></textarea>
                </div>
                <input type="hidden" id="revoke-type">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="revoke-confirm">
                    <i class="bi bi-x-circle me-1"></i>Confirmar Revogação
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Nova Solicitação LGPD -->
<div class="modal fade" id="modalPrivacy" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-fill-gear text-primary me-2"></i>Nova solicitação LGPD</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Tipo de solicitação <span class="text-danger">*</span></label>
                    <select class="form-select" id="privacy-type">
                        <option value="access">Confirmar acesso aos meus dados</option>
                        <option value="correction">Corrigir dados incorretos ou incompletos</option>
                        <option value="biometric_purge">Revisão dos meus dados biométricos</option>
                        <option value="deactivation">Análise de desativação da minha conta</option>
                    </select>
                    <div class="form-text" id="privacy-type-hint"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Descreva o motivo <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="privacy-reason" rows="4" placeholder="Ex.: Solicito revisão dos meus dados biométricos pois..."></textarea>
                    <div class="form-text">O DPO será notificado e entrará em contato pelo protocolo gerado.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="privacy-confirm">
                    <i class="bi bi-send me-1"></i>Enviar Solicitação
                </button>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(function () {
    const CSRF   = '<?= csrf_token() ?>';
    let   csrfHash = '<?= csrf_hash() ?>';

    function toast(msg, type) {
        type = type || 'success';
        const el  = document.getElementById('sp-toast');
        const msg_el = document.getElementById('sp-toast-msg');
        el.className = 'toast align-items-center text-white border-0 bg-' + type;
        msg_el.textContent = msg;
        bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
    }

    async function post(url, data) {
        data[CSRF] = csrfHash;
        const res  = await spFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body:    new URLSearchParams(data).toString(),
        });
        const json = await res.json();
        if (json.csrf_hash) csrfHash = json.csrf_hash;
        return json;
    }

    // ── Grant modal ─────────────────────────────────────────────────────
    const modalGrant   = new bootstrap.Modal(document.getElementById('modalGrant'));
    const grantConfirm = document.getElementById('grant-confirm');
    const grantCheck   = document.getElementById('grant-check');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sp-consent-grant');
        if (!btn) return;
        const grantType = btn.dataset.type;
        document.getElementById('grant-label').textContent   = btn.dataset.label;
        document.getElementById('grant-purpose').textContent = btn.dataset.purpose;
        document.getElementById('grant-legal').textContent   = btn.dataset.legal;
        document.getElementById('grant-type').value          = grantType;
        grantCheck.checked    = false;
        grantConfirm.disabled = true;
        const term = (typeof CONSENT_TERMS !== 'undefined' && CONSENT_TERMS[grantType]) ? CONSENT_TERMS[grantType] : null;
        const termBox = document.getElementById('grant-term-container');
        if (term && term.body) {
            document.getElementById('grant-term-body').textContent    = term.body;
            document.getElementById('grant-term-version').textContent = term.version || '1.0';
            termBox.style.display = '';
        } else { termBox.style.display = 'none'; }
        modalGrant.show();
    });

    grantCheck.addEventListener('change', () => { grantConfirm.disabled = !grantCheck.checked; });

    grantConfirm.addEventListener('click', async function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde...';
        const type    = document.getElementById('grant-type').value;
        const purpose = document.getElementById('grant-purpose').textContent;
        const legal   = document.getElementById('grant-legal').textContent;
        try {
            const json = await post('<?= site_url('lgpd/consent/grant') ?>', {
                consent_type: type,
                purpose: purpose,
                consent_text: 'Finalidade: ' + purpose + '\nBase Legal: ' + legal,
                legal_basis: legal,
                version: '1.0',
            });
            modalGrant.hide();
            toast(json.success ? 'Consentimento registrado com sucesso.' : (json.message || 'Erro ao registrar.'), json.success ? 'success' : 'danger');
            if (json.success) setTimeout(() => location.reload(), 1500);
        } catch (err) {
            toast('Erro de comunicação com o servidor.', 'danger');
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-check-circle me-1"></i>Confirmar Consentimento';
    });

    // ── Revoke modal ────────────────────────────────────────────────────
    const modalRevoke   = new bootstrap.Modal(document.getElementById('modalRevoke'));
    const revokeConfirm = document.getElementById('revoke-confirm');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sp-consent-revoke');
        if (!btn) return;
        document.getElementById('revoke-label').textContent = btn.dataset.label;
        document.getElementById('revoke-type').value        = btn.dataset.type;
        document.getElementById('revoke-reason').value      = '';
        modalRevoke.show();
    });

    revokeConfirm.addEventListener('click', async function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde...';
        try {
            const json = await post('<?= site_url('lgpd/consent/revoke') ?>', {
                consent_type: document.getElementById('revoke-type').value,
                reason:       document.getElementById('revoke-reason').value,
            });
            modalRevoke.hide();
            toast(json.success ? 'Consentimento revogado com sucesso.' : (json.message || 'Erro ao revogar.'), json.success ? 'success' : 'danger');
            if (json.success) setTimeout(() => location.reload(), 1500);
        } catch (err) {
            toast('Erro de comunicação com o servidor.', 'danger');
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-x-circle me-1"></i>Confirmar Revogação';
    });

    // ── Nova solicitação LGPD ───────────────────────────────────────────
    const modalPrivacy   = new bootstrap.Modal(document.getElementById('modalPrivacy'));
    const privacyConfirm = document.getElementById('privacy-confirm');
    const privacyTypeSel = document.getElementById('privacy-type');
    const privacyHints = {
        access: 'Você receberá a confirmação de quais dados pessoais são tratados pelo sistema.',
        correction: 'Descreva quais dados estão incorretos e qual a correção necessária.',
        biometric_purge: 'Solicite exclusão e novo cadastro dos seus dados biométricos.',
        deactivation: 'Solicite análise para desativação da sua conta e eliminação de dados.',
    };

    function updatePrivacyHint() {
        document.getElementById('privacy-type-hint').textContent = privacyHints[privacyTypeSel.value] || '';
    }
    privacyTypeSel.addEventListener('change', updatePrivacyHint);

    document.getElementById('btn-new-request').addEventListener('click', function () {
        privacyTypeSel.value = 'access';
        document.getElementById('privacy-reason').value = '';
        updatePrivacyHint();
        modalPrivacy.show();
    });

    privacyConfirm.addEventListener('click', async function () {
        const reason = document.getElementById('privacy-reason').value.trim();
        if (!reason) { document.getElementById('privacy-reason').focus(); toast('Descreva o motivo da solicitação.', 'warning'); return; }
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
        try {
            const json = await post('<?= site_url('lgpd/privacy-request') ?>', {
                request_type: privacyTypeSel.value,
                reason: reason,
            });
            modalPrivacy.hide();
            toast(json.success ? 'Solicitação registrada. O DPO será notificado.' : (json.message || 'Erro.'), json.success ? 'success' : 'danger');
            if (json.success) setTimeout(() => location.reload(), 1500);
        } catch (err) {
            toast('Erro de comunicação com o servidor.', 'danger');
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-send me-1"></i>Enviar Solicitação';
    });

    // ── Export data ─────────────────────────────────────────────────────
    document.getElementById('btn-export-data').addEventListener('click', async function () {
        if (!confirm('Solicitar exportação de todos os seus dados pessoais?\n\nVocê receberá um e-mail com o link para download.')) return;
        try {
            const json = await post('<?= site_url('lgpd/export/request') ?>', {});
            toast(json.success
                ? 'Exportação solicitada. Você receberá um e-mail com o link para download.'
                : (json.message || 'Erro ao solicitar exportação.'),
                json.success ? 'success' : 'danger');
        } catch (err) {
            toast('Erro de comunicação com o servidor.', 'danger');
        }
    });
})();
</script>

<?= $this->endSection() ?>
