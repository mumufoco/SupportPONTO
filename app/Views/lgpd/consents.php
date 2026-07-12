<?php
// TEMP DEBUG
if (isset($consents)) {
    echo '<!-- DEBUG_ACTIVE_COUNT=' . count($consents["active"]) . ' -->';
    echo '<!-- DEBUG_ALL_COUNT=' . count($consents["all"]) . ' -->';
    echo '<!-- DEBUG_PENDING=' . implode(",", $consents["pending"]) . ' -->';
    foreach ($consents["active"] as $c) {
        echo '<!-- DEBUG_ACTIVE_TYPE=' . $c->consent_type . ' granted=' . var_export($c->granted, true) . ' rev=' . var_export($c->revoked_at, true) . ' -->';
    }
}
?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>LGPD — Consentimentos<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$employee     = $employee     ?? [];
$consents     = $consents     ?? ['active' => [], 'revoked' => [], 'pending' => [], 'all' => []];
$consentTypes = $consentTypes ?? [];
$consentTerms = $consentTerms ?? [];

// Helper: find consent record for a given type
$findConsent = function (array $list, string $type): ?object {
    foreach ($list as $c) {
        if (($c->consent_type ?? '') === $type) return $c;
    }
    return null;
};

$dpoEmail = env('DPO_EMAIL', 'dpo@supportsondagens.com.br');
?>
<script>
const CONSENT_TERMS = <?= json_encode(array_map(function($t) {
    return ['title' => $t['title'] ?? '', 'body' => $t['body'] ?? '', 'version' => $t['version'] ?? '1.0'];
}, $consentTerms), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'LGPD — Consentimentos e Privacidade',
        'subtitle' => 'Gerencie seus consentimentos e direitos como titular de dados conforme a Lei 13.709/2018.',
        'icon'     => 'bi bi-shield-check',
        'actions'  => [
            ['label' => 'Solicitar exportação de dados', 'icon' => 'bi bi-download', 'url' => '#', 'id' => 'btn-export-data'],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- Aviso de toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div id="sp-toast" class="toast align-items-center text-white border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="sp-toast-msg"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <!-- Seus direitos LGPD -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="d-flex gap-3 align-items-start">
                <i class="bi bi-info-circle-fill text-primary fs-4 flex-shrink-0 mt-1"></i>
                <div class="row g-2 w-100">
                    <div class="col-12">
                        <strong class="text-primary">Seus direitos como titular (Lei 13.709/2018)</strong>
                    </div>
                    <?php foreach ([
                        ['bi-eye',          'Acesso',        'Confirmar e acessar seus dados tratados'],
                        ['bi-pencil',       'Correção',      'Corrigir dados incompletos ou incorretos'],
                        ['bi-box-arrow-up', 'Portabilidade', 'Receber dados em formato estruturado'],
                        ['bi-x-circle',     'Revogação',     'Revogar consentimento a qualquer momento'],
                        ['bi-trash',        'Eliminação',    'Solicitar eliminação dos dados tratados'],
                    ] as [$icon, $title, $desc]): ?>
                    <div class="col-sm-6 col-lg">
                        <div class="d-flex gap-2 align-items-start">
                            <i class="bi <?= $icon ?> text-secondary mt-1 flex-shrink-0"></i>
                            <div>
                                <div class="small fw-semibold"><?= $title ?></div>
                                <div class="small text-muted"><?= $desc ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div class="col-12 mt-1">
                        <small class="text-muted"><i class="bi bi-envelope me-1"></i>DPO: <a href="mailto:<?= esc($dpoEmail) ?>"><?= esc($dpoEmail) ?></a></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards de consentimento -->
    <h6 class="fw-bold text-uppercase text-muted small mb-3 ls-1">
        <i class="bi bi-toggles me-2"></i>Status dos Consentimentos
    </h6>

    <div class="row g-3 mb-4">
        <?php foreach ($consentTypes as $type => $info): ?>
        <?php
            $active   = $findConsent($consents['active'],  $type);
            $revoked  = $findConsent($consents['revoked'], $type);
            $hasConsent = $active !== null;
            $isPending  = in_array($type, $consents['pending'] ?? []);
            $isRequired = $info['required'] ?? false;
            $isSensitive = in_array($type, ['biometric_face', 'biometric_fingerprint']);
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm <?= $hasConsent ? 'border-start border-success border-3' : ($isRequired ? 'border-start border-warning border-3' : '') ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex gap-2 align-items-center">
                            <i class="bi <?= $isSensitive ? 'bi-fingerprint' : ($type === 'geolocation' ? 'bi-geo-alt-fill' : 'bi-person-fill-lock') ?> text-primary fs-5"></i>
                            <div>
                                <div class="fw-semibold"><?= esc($info['label']) ?></div>
                                <?php if ($isRequired): ?>
                                <span class="badge bg-warning text-dark" style="font-size:.65rem;">Obrigatório</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($hasConsent): ?>
                            <span class="badge bg-success rounded-pill"><i class="bi bi-check-circle me-1"></i>Ativo</span>
                        <?php elseif ($isPending): ?>
                            <span class="badge bg-secondary rounded-pill"><i class="bi bi-clock me-1"></i>Pendente</span>
                        <?php else: ?>
                            <span class="badge bg-danger rounded-pill"><i class="bi bi-x-circle me-1"></i>Revogado</span>
                        <?php endif; ?>
                    </div>

                    <p class="small text-muted mb-2"><?= esc($info['purpose']) ?></p>
                    <p class="small mb-0"><span class="text-muted">Base legal:</span> <?= esc($info['legal_basis']) ?></p>

                    <?php if ($active): ?>
                    <div class="mt-3 p-2 bg-light rounded small">
                        <div><i class="bi bi-calendar-check me-1 text-success"></i><strong>Aceito em:</strong> <?= date('d/m/Y H:i', strtotime($active->granted_at)) ?></div>
                        <?php if ($active->version): ?><div><i class="bi bi-tag me-1 text-secondary"></i><strong>Versão:</strong> <?= esc($active->version) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($revoked && !$hasConsent): ?>
                    <div class="mt-3 p-2 bg-danger bg-opacity-10 rounded small text-danger">
                        <i class="bi bi-calendar-x me-1"></i>Revogado em: <?= date('d/m/Y H:i', strtotime($revoked->revoked_at)) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-transparent border-0 pt-0 pb-3">
                    <?php if ($hasConsent && !$isRequired): ?>
                        <button class="btn btn-sm btn-outline-danger w-100 sp-consent-revoke"
                                data-type="<?= esc($type) ?>"
                                data-label="<?= esc($info['label']) ?>">
                            <i class="bi bi-x-circle me-1"></i>Revogar Consentimento
                        </button>
                    <?php elseif ($hasConsent && $isRequired): ?>
                        <div class="small text-muted text-center py-1">
                            <i class="bi bi-lock me-1"></i>Obrigatório para uso do sistema
                        </div>
                    <?php elseif ($type === 'biometric_face'): ?>
                        <div class="small text-muted text-center py-1">
                            <i class="bi bi-info-circle me-1"></i>Aceito via processo de cadastro biométrico
                        </div>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-success w-100 sp-consent-grant"
                                data-type="<?= esc($type) ?>"
                                data-label="<?= esc($info['label']) ?>"
                                data-purpose="<?= esc($info['purpose']) ?>"
                                data-legal="<?= esc($info['legal_basis']) ?>">
                            <i class="bi bi-check-circle me-1"></i>Conceder Consentimento
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Ações LGPD -->
    <h6 class="fw-bold text-uppercase text-muted small mb-3">
        <i class="bi bi-person-fill-gear me-2"></i>Exercer Direitos LGPD
    </h6>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <i class="bi bi-box-arrow-up fs-3 text-primary mb-2"></i>
                    <h6 class="fw-semibold">Exportar meus dados</h6>
                    <p class="small text-muted flex-grow-1">Receba um arquivo com todos os seus dados pessoais tratados pelo sistema. Você receberá um e-mail com link de download.</p>
                    <button class="btn btn-outline-primary btn-sm mt-auto" id="btn-export-data2">
                        <i class="bi bi-download me-1"></i>Solicitar exportação
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <i class="bi bi-fingerprint fs-3 text-warning mb-2"></i>
                    <h6 class="fw-semibold">Revisão biométrica</h6>
                    <p class="small text-muted flex-grow-1">Solicite revisão dos dados biométricos registrados, incluindo exclusão e recadastro conforme Art. 18 da LGPD.</p>
                    <button class="btn btn-outline-warning btn-sm mt-auto sp-privacy-action" data-type="biometric_purge">
                        <i class="bi bi-arrow-repeat me-1"></i>Solicitar revisão
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <i class="bi bi-person-slash fs-3 text-danger mb-2"></i>
                    <h6 class="fw-semibold">Análise de desativação</h6>
                    <p class="small text-muted flex-grow-1">Solicite análise para desativação de conta e eliminação dos dados tratados, conforme Art. 18, IV da LGPD.</p>
                    <button class="btn btn-outline-danger btn-sm mt-auto sp-privacy-action" data-type="deactivation">
                        <i class="bi bi-person-x me-1"></i>Solicitar análise
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <i class="bi bi-eye fs-3 text-info mb-2"></i>
                    <h6 class="fw-semibold">Confirmar acesso aos dados</h6>
                    <p class="small text-muted flex-grow-1">Solicite confirmação de quais dados pessoais seus são tratados pelo sistema, conforme Art. 18, I da LGPD.</p>
                    <button class="btn btn-outline-info btn-sm mt-auto sp-privacy-action" data-type="access">
                        <i class="bi bi-search me-1"></i>Solicitar confirmação
                    </button>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column">
                    <i class="bi bi-pencil-square fs-3 text-secondary mb-2"></i>
                    <h6 class="fw-semibold">Solicitar correção de dados</h6>
                    <p class="small text-muted flex-grow-1">Solicite a correção de dados pessoais incompletos, inexatos ou desatualizados, conforme Art. 18, III da LGPD.</p>
                    <button class="btn btn-outline-secondary btn-sm mt-auto sp-privacy-action" data-type="correction">
                        <i class="bi bi-pencil me-1"></i>Solicitar correção
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico -->
    <?php if (!empty($consents['all'])): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
            <h6 class="fw-semibold mb-0"><i class="bi bi-clock-history me-2"></i>Histórico de Consentimentos</h6>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#history-collapse">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
        <div class="collapse show" id="history-collapse">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Tipo</th>
                                <th>Status</th>
                                <th>Data</th>
                                <th>IP</th>
                                <th class="pe-3">Versão</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consents['all'] as $consent): ?>
                            <tr>
                                <td class="ps-3"><?= esc($consentTypes[$consent->consent_type]['label'] ?? $consent->consent_type) ?></td>
                                <td>
                                    <?php if ($consent->granted && !$consent->revoked_at): ?>
                                        <span class="badge bg-success rounded-pill">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger rounded-pill">Revogado</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?= $consent->revoked_at
                                        ? date('d/m/Y H:i', strtotime($consent->revoked_at))
                                        : date('d/m/Y H:i', strtotime($consent->granted_at)) ?>
                                </td>
                                <td class="text-muted"><?= esc($consent->ip_address ?? '-') ?></td>
                                <td class="pe-3"><span class="badge bg-secondary">v<?= esc($consent->version) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php if (!empty($subjectRequests)): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
        <h6 class="fw-semibold mb-0"><i class="bi bi-list-check me-2"></i>Minhas Solicitações LGPD</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Protocolo</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Solicitado em</th>
                        <th class="pe-3">Prazo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $reqTypeLabels = [
                        'access' => 'Acesso', 'export' => 'Portabilidade',
                        'correction' => 'Correção', 'anonymization' => 'Anonimização',
                        'deactivation' => 'Desativação', 'biometric_purge' => 'Expurgo Biométrico',
                    ];
                    $reqStatusBadge = [
                        'pending'  => 'bg-warning text-dark',
                        'resolved' => 'bg-success',
                        'rejected' => 'bg-danger',
                    ];
                    foreach ($subjectRequests as $req): ?>
                    <tr>
                        <td class="ps-3 font-monospace small"><?= esc($req->request_id) ?></td>
                        <td><?= esc($reqTypeLabels[$req->request_type] ?? $req->request_type) ?></td>
                        <td>
                            <span class="badge <?= $reqStatusBadge[$req->status] ?? 'bg-secondary' ?>">
                                <?= esc(ucfirst($req->status)) ?>
                            </span>
                        </td>
                        <td class="text-muted"><?= date('d/m/Y H:i', strtotime($req->created_at)) ?></td>
                        <td class="pe-3 <?= (!empty($req->due_at) && strtotime($req->due_at) < time() && $req->status === 'pending') ? 'text-danger fw-semibold' : 'text-muted' ?>">
                            <?= !empty($req->due_at) ? date('d/m/Y', strtotime($req->due_at)) : '-' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>


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
                    <div class="small text-muted fw-semibold mb-1">Termo Completo <span class="badge bg-warning text-dark">Leia antes de aceitar</span></div>
                    <pre id="grant-term-body" class="small p-2 bg-light rounded border" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;font-family:inherit;font-size:.78rem;"></pre>
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

<!-- Modal: Solicitação LGPD -->
<div class="modal fade" id="modalPrivacy" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="privacy-modal-title"><i class="bi bi-person-fill-gear text-primary me-2"></i>Solicitação LGPD</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Descreva brevemente o motivo da sua solicitação. O DPO será notificado e entrará em contato.</p>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Motivo da solicitação <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="privacy-reason" rows="4" placeholder="Ex.: Solicito revisão dos meus dados biométricos pois..."></textarea>
                </div>
                <input type="hidden" id="privacy-type">
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

    // Toast helper
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

    // ── Privacy action modal ────────────────────────────────────────────
    const modalPrivacy   = new bootstrap.Modal(document.getElementById('modalPrivacy'));
    const privacyConfirm = document.getElementById('privacy-confirm');
    const privacyTitles  = { biometric_purge: 'Revisão Biométrica', deactivation: 'Análise de Desativação', access: 'Confirmação de Acesso aos Dados', correction: 'Solicitação de Correção de Dados' };

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.sp-privacy-action');
        if (!btn) return;
        const type = btn.dataset.type;
        document.getElementById('privacy-type').value             = type;
        document.getElementById('privacy-modal-title').innerHTML  = '<i class="bi bi-person-fill-gear text-primary me-2"></i>' + (privacyTitles[type] || 'Solicitação LGPD');
        document.getElementById('privacy-reason').value           = '';
        modalPrivacy.show();
    });

    privacyConfirm.addEventListener('click', async function () {
        const reason = document.getElementById('privacy-reason').value.trim();
        if (!reason) { document.getElementById('privacy-reason').focus(); toast('Descreva o motivo da solicitação.', 'warning'); return; }
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Enviando...';
        try {
            const json = await post('<?= site_url('lgpd/privacy-request') ?>', {
                request_type: document.getElementById('privacy-type').value,
                reason: reason,
            });
            modalPrivacy.hide();
            toast(json.success ? 'Solicitação registrada. O DPO será notificado.' : (json.message || 'Erro.'), json.success ? 'success' : 'danger');
        } catch (err) {
            toast('Erro de comunicação com o servidor.', 'danger');
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-send me-1"></i>Enviar Solicitação';
    });

    // ── Export data ─────────────────────────────────────────────────────
    async function exportData() {
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
    }

    document.getElementById('btn-export-data')?.addEventListener('click', exportData);
    document.getElementById('btn-export-data2')?.addEventListener('click', exportData);
})();
</script>

<?= $this->endSection() ?>
