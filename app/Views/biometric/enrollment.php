<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
$isAdmin  = !empty($isAdminEnrollment) && !empty($targetEmployee);
$empId    = $isAdmin ? (int)$targetEmployee->id : 0;
$showForm = $isAdmin || !empty($hasConsent);
?>
<div class="container-fluid py-4 sp-module-stack">
<?= view('components/page_header', [
    'title'    => $isAdmin
        ? 'Cadastro Biométrico — ' . esc($targetEmployee->name ?? 'Colaborador')
        : 'Cadastro Biométrico Facial',
    'subtitle' => $isAdmin
        ? 'Cadastro facial em nome do colaborador. Gestão administrativa.'
        : 'Gerencie seu consentimento LGPD e realize o cadastro facial.',
    'icon'     => 'bi bi-camera-video-fill',
    'actions'  => $isAdmin
        ? [['label' => 'Voltar à visão geral', 'icon' => 'bi bi-arrow-left', 'url' => site_url('admin/employees/overview')]]
        : [['label' => 'Voltar ao perfil',     'icon' => 'bi bi-person-circle', 'url' => site_url('profile')]],
]) ?>

<?php if ($isAdmin): ?>
<div class="alert alert-info d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-person-badge-fill fs-4"></i>
    <div>
        <strong>Cadastro em nome de:</strong>
        <?= esc($targetEmployee->name ?? 'Colaborador') ?>
        &mdash; <?= esc($targetEmployee->email ?? '') ?>
    </div>
</div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-10">

<?php if (!$showForm): ?>
<!-- CONSENTIMENTO LGPD (apenas auto-cadastro sem consentimento) -->
<div class="card shadow mb-4">
    <div class="card-header bg-warning">
        <h5 class="mb-0 text-dark"><i class="fas fa-exclamation-triangle me-2"></i>Consentimento Necessário</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            <p class="mb-1"><strong><i class="fas fa-info-circle me-1"></i>Termos de Uso de Dados Biométricos (LGPD)</strong></p>
            <p class="mb-1"><strong>Finalidade:</strong> Registro de ponto por reconhecimento facial.</p>
            <p class="mb-1"><strong>Base Legal:</strong> Consentimento (Art. 7º, I da LGPD).</p>
            <p class="mb-0"><strong>Seus Direitos:</strong> Você pode revogar este consentimento a qualquer momento.</p>
        </div>
        <form action="<?= site_url('biometric/consent') ?>" method="POST">
            <?= csrf_field() ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="acceptTerms" required>
                <label class="form-check-label" for="acceptTerms">
                    Autorizo o tratamento dos meus dados biométricos (facial) para fins de registro de ponto eletrônico.
                </label>
            </div>
            <button type="submit" class="btn btn-success">
                <i class="fas fa-check me-2"></i>Aceitar e Continuar
            </button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- FORMULÁRIO DE CADASTRO -->
<div class="row">

    <!-- Câmera -->
    <div class="col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-camera me-2"></i>Cadastro Facial
                    <?= $isAdmin ? '— ' . esc($targetEmployee->name ?? '') : '' ?>
                </h5>
            </div>
            <div class="card-body">
                <div id="camera-container" class="text-center mb-3" style="position:relative;">
                    <video id="video" width="100%" autoplay muted playsinline class="border rounded d-none" style="max-height:300px;object-fit:cover;"></video>
                    <!-- Face positioning guide overlay -->
                    <div id="face-guide-overlay" class="d-none" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;">
                        <svg id="face-guide-svg" width="100%" height="100%" viewBox="0 0 640 300" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
                            <!-- Dark mask outside oval -->
                            <defs>
                                <mask id="oval-mask">
                                    <rect width="640" height="300" fill="white"/>
                                    <ellipse cx="320" cy="150" rx="130" ry="165" fill="black"/>
                                </mask>
                            </defs>
                            <rect width="640" height="300" fill="rgba(0,0,0,0.35)" mask="url(#oval-mask)"/>
                            <!-- Oval guide border -->
                            <ellipse id="guide-ellipse" cx="320" cy="150" rx="130" ry="165" fill="none" stroke="#00e5a0" stroke-width="3" stroke-dasharray="12 6">
                                <animate attributeName="stroke-dashoffset" from="0" to="54" dur="2s" repeatCount="indefinite"/>
                            </ellipse>
                            <!-- Corner guides -->
                            <line x1="10" y1="10" x2="40" y2="10" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="10" y1="10" x2="10" y2="40" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="630" y1="10" x2="600" y2="10" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="630" y1="10" x2="630" y2="40" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="10" y1="290" x2="40" y2="290" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="10" y1="290" x2="10" y2="260" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="630" y1="290" x2="600" y2="290" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <line x1="630" y1="290" x2="630" y2="260" stroke="rgba(255,255,255,0.6)" stroke-width="2"/>
                            <!-- Label -->
                            <text x="320" y="292" text-anchor="middle" fill="rgba(255,255,255,0.8)" font-size="11" font-family="sans-serif">Posicione o rosto dentro do oval</text>
                        </svg>
                    </div>
                    <canvas id="canvas" width="640" height="480" class="d-none"></canvas>
                    <div id="camera-placeholder" class="border rounded p-4 bg-light text-center"
                         style="min-height:200px;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                        <i class="fas fa-video fa-3x text-muted mb-2"></i>
                        <p class="text-muted mb-0">Clique em <strong>Iniciar Câmera</strong> para começar</p>
                    </div>
                    <img id="preview" class="img-fluid border rounded d-none" style="max-height:300px;object-fit:cover;">
                </div>

                <div id="progress-indicator" class="mb-3 d-none">
                    <div class="d-flex align-items-center gap-1 mb-1">
                        <div class="step-dot step-active" id="step1"></div>
                        <div class="step-line"></div>
                        <div class="step-dot" id="step2"></div>
                        <div class="step-line"></div>
                        <div class="step-dot" id="step3"></div>
                        <div class="step-line"></div>
                        <div class="step-dot" id="step4"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:.72rem;color:var(--sp-text-muted);">
                        <span>Câmera</span><span>Captura</span><span>Envio</span><span>Salvo</span>
                    </div>
                </div>

                <div id="capture-instructions" class="alert alert-info d-none py-2">
                    <small><i class="fas fa-lightbulb me-1"></i>
                        <strong>Dicas:</strong> Rosto centralizado, boa iluminação, sem óculos escuros, fundo neutro.
                    </small>
                </div>

                <div id="camera-status" class="mb-3 d-none"></div>

                <div class="d-grid gap-2">
                    <button id="btn-start-camera" class="btn btn-primary btn-lg">
                        <i class="fas fa-camera me-2"></i>Iniciar Câmera
                    </button>
                    <button id="btn-capture" class="btn btn-success btn-lg d-none">
                        <i class="fas fa-camera-retro me-2"></i>Capturar Foto
                    </button>
                    <button id="btn-retake" class="btn btn-secondary d-none">
                        <i class="fas fa-redo me-2"></i>Tirar Outra Foto
                    </button>
                    <button id="btn-enroll" class="btn btn-success btn-lg d-none">
                        <i class="fas fa-save me-2"></i>
                        <?= $isAdmin
                            ? 'Cadastrar Biometria de ' . esc($targetEmployee->name ?? 'Colaborador')
                            : 'Cadastrar Biometria' ?>
                    </button>
                </div>

                <div id="enrollment-result" class="mt-3 d-none"></div>

                <div id="job-status-box" class="mt-3 d-none">
                    <div class="alert alert-info d-flex align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                        <div><strong>Processando biometria...</strong></div>
                    </div>
                    <div class="progress mt-1" style="height:5px;">
                        <div id="job-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:0%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cadastros existentes -->
    <div class="col-md-6 mb-4">
        <div class="card shadow h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-fingerprint me-2"></i>
                    <?= $isAdmin
                        ? 'Cadastros de ' . esc($targetEmployee->name ?? 'Colaborador')
                        : 'Meus Cadastros Biométricos' ?>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($faceTemplates)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?= $isAdmin
                        ? esc($targetEmployee->name ?? 'Este colaborador') . ' ainda não possui cadastro facial.'
                        : 'Você ainda não possui cadastro facial.' ?>
                </div>
                <?php else: ?>
                <h6 class="mb-3">Cadastros Faciais</h6>
                <ul class="list-group mb-3">
                    <?php foreach ($faceTemplates as $template): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-user-circle text-primary me-2"></i>
                            <span class="badge bg-<?= $template->active ? 'success' : 'secondary' ?>">
                                <?= $template->active ? 'Ativo' : 'Inativo' ?>
                            </span>
                            <small class="text-muted ms-2">
                                Qualidade: <?= round(($template->enrollment_quality ?? 0) * 100) ?>%
                            </small>
                            <br>
                            <small class="text-muted">
                                Cadastrado em: <?= date('d/m/Y H:i', strtotime($template->created_at)) ?>
                            </small>
                        </div>
                        <button class="btn btn-outline-danger btn-sm btn-delete-template"
                                data-id="<?= (int) $template->id ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>

                <?php if (!$isAdmin): ?>
                <hr>
                <h6 class="mb-2">Testar Reconhecimento</h6>
                <button id="btn-test-recognition" class="btn btn-outline-primary w-100"
                        <?= empty($faceTemplates) ? 'disabled' : '' ?>>
                    <i class="fas fa-search me-2"></i>Testar Meu Rosto
                </button>
                <div id="test-result" class="mt-3 d-none"></div>
                <?php endif; ?>
            </div>

            <div class="card-footer bg-light">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fas fa-shield-alt text-success me-1"></i>Dados protegidos pela LGPD
                    </small>
                    <?php if (!$isAdmin): ?>
                    <button id="btn-revoke-consent" class="btn btn-link text-danger btn-sm p-0">
                        Revogar Consentimento
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>
<?php endif; ?>

</div>
</div>
</div>

<style>
.step-dot { width:16px; height:16px; border-radius:50%; background:var(--sp-gray-300); flex-shrink:0; display:inline-block; }
.step-dot.step-active { background:var(--sp-primary); }
.step-dot.step-done   { background:var(--sp-success); }
.step-dot.step-error  { background:var(--sp-danger); }
.step-line { flex:1; height:3px; background:var(--sp-border); }
</style>

<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/camera-manager.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    const cam         = CameraManager.getInstance();
    const video       = document.getElementById('video');
    const canvas      = document.getElementById('canvas');
    const preview     = document.getElementById('preview');
    const placeholder = document.getElementById('camera-placeholder');
    const instrBox    = document.getElementById('capture-instructions');
    const statusDiv   = document.getElementById('camera-status');
    let capturedPhoto = null;

    function setStep(n, err) {
        document.getElementById('progress-indicator').classList.remove('d-none');
        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById('step' + i);
            el.className = 'step-dot' + (i < n ? ' step-done' : i === n ? (err ? ' step-error' : ' step-active') : '');
        }
    }

    function showStatus(msg, type) {
        type = type || 'info';
        statusDiv.innerHTML =
            `<div class="alert alert-${type} alert-dismissible fade show py-2 mb-0">` +
            `<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button>` +
            `${escHtml(msg)}</div>`;
        statusDiv.classList.remove('d-none');
    }

    function validateQuality(cvs) {
        const d = cvs.getContext('2d').getImageData(0, 0, cvs.width, cvs.height).data;
        let b = 0, c = 0;
        const n = Math.min(2000, d.length / 4);
        for (let i = 0; i < n; i++) {
            const idx = i * 4;
            const g   = (d[idx] + d[idx + 1] + d[idx + 2]) / 3;
            b += g; c += Math.abs(g - 128);
        }
        b /= n; c /= n;
        if (b < 60) return 'Iluminação insuficiente. Fique em local mais claro.';
        if (c < 25) return 'Contraste muito baixo. Evite fundo uniforme.';
        return true;
    }

    // ── Iniciar câmera ────────────────────────────────────────────────────
    document.getElementById('btn-start-camera').addEventListener('click', async function () {
        try {
            showStatus('Iniciando câmera...', 'info');
            await cam.start(video);
            video.classList.remove('d-none');
            document.getElementById('face-guide-overlay').classList.remove('d-none');
            placeholder.classList.add('d-none');
            instrBox.classList.remove('d-none');
            this.classList.add('d-none');
            document.getElementById('btn-capture').classList.remove('d-none');
            setStep(1);
            setTimeout(() => showStatus('Câmera pronta! Centralize o rosto e clique em Capturar Foto.', 'success'), 1200);
        } catch (e) {
            showStatus('Erro ao acessar câmera: ' + e.message + '. Verifique as permissões do navegador.', 'danger');
            setStep(1, true);
        }
    });

    // ── Capturar foto ─────────────────────────────────────────────────────
    document.getElementById('btn-capture').addEventListener('click', function () {
        capturedPhoto = cam.captureFrame(canvas, 0.85);
        if (!capturedPhoto) { showStatus('Erro ao capturar. Tente novamente.', 'danger'); return; }
        preview.src = capturedPhoto;
        preview.classList.remove('d-none');
        video.classList.add('d-none');
        document.getElementById('face-guide-overlay').classList.add('d-none');
        this.classList.add('d-none');
        document.getElementById('btn-retake').classList.remove('d-none');
        document.getElementById('btn-enroll').classList.remove('d-none');
        cam.stop();
        setStep(2);
        const q = validateQuality(canvas);
        if (q !== true) {
            showStatus(q + ' — Você pode tirar outra foto ou prosseguir.', 'warning');
        } else {
            showStatus('Foto capturada! Clique em Cadastrar Biometria.', 'success');
        }
    });

    // ── Retomar câmera ────────────────────────────────────────────────────
    document.getElementById('btn-retake').addEventListener('click', async function () {
        capturedPhoto = null;
        preview.classList.add('d-none');
        this.classList.add('d-none');
        document.getElementById('btn-enroll').classList.add('d-none');
        try {
            await cam.start(video);
            video.classList.remove('d-none');
            placeholder.classList.add('d-none');
            instrBox.classList.remove('d-none');
            document.getElementById('btn-start-camera').classList.add('d-none');
            document.getElementById('btn-capture').classList.remove('d-none');
            setStep(1);
            showStatus('Câmera reiniciada. Posicione o rosto.', 'info');
        } catch (e) { showStatus(e.message, 'danger'); }
    });

    document.getElementById('btn-enroll').addEventListener('click', enrollFace);

    const testBtn   = document.getElementById('btn-test-recognition');
    if (testBtn) testBtn.addEventListener('click', testRecognition);

    const revokeBtn = document.getElementById('btn-revoke-consent');
    if (revokeBtn) revokeBtn.addEventListener('click', revokeConsent);

    document.querySelectorAll('.btn-delete-template').forEach(btn => {
        btn.addEventListener('click', function () {
            if (confirm('Tem certeza que deseja excluir este cadastro biométrico?')) {
                deleteTemplate(this.dataset.id);
            }
        });
    });

    // ── Cadastrar biometria ───────────────────────────────────────────────
    async function enrollFace() {
        if (!capturedPhoto) { showStatus('Capture uma foto primeiro.', 'warning'); return; }
        const q = validateQuality(canvas);
        if (q !== true && !confirm(q + '\n\nDeseja prosseguir mesmo assim?')) return;

        const btn  = document.getElementById('btn-enroll');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        setStep(3);
        document.getElementById('enrollment-result').classList.add('d-none');

        try {
            const response = await spFetch('<?= site_url('biometric/face/enroll') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'photo=' + encodeURIComponent(capturedPhoto)
                    + '&employee_id=<?= $empId ?>'
                    + '&<?= csrf_token() ?>=<?= csrf_hash() ?>'
            });
            let result = {};
            try { result = await response.json(); } catch (e) { result = { success: false, message: 'Resposta inválida do servidor.' }; }

            const resultDiv = document.getElementById('enrollment-result');
            resultDiv.classList.remove('d-none');

            if (result.success) {
                setStep(4);
                const jobId = result.data?.job_id;
                if (jobId) {
                    resultDiv.innerHTML = `<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>${escHtml(result.message)}</div>`;
                    document.getElementById('job-status-box').classList.remove('d-none');
                    pollJobStatus(jobId, resultDiv);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>${escHtml(result.message || 'Cadastro realizado com sucesso!')}</div>`;
                    setTimeout(() => location.reload(), 2500);
                }
            } else {
                setStep(3, true);
                resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${escHtml(result.message || result.error || 'Erro ao cadastrar biometria.')}</div>`;
            }
        } catch (e) {
            setStep(3, true);
            const rd = document.getElementById('enrollment-result');
            rd.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Erro de comunicação com o servidor.</div>';
            rd.classList.remove('d-none');
        }
        btn.disabled = false;
        btn.innerHTML = orig;
    }

    // ── Polling do job ────────────────────────────────────────────────────
    function pollJobStatus(jobId, resultDiv, attempt) {
        attempt = attempt || 1;
        if (attempt > 30) {
            document.getElementById('job-status-box').classList.add('d-none');
            resultDiv.innerHTML = '<div class="alert alert-warning"><i class="fas fa-clock me-2"></i>Processamento em andamento. Recarregue a página em alguns segundos.</div>';
            return;
        }
        const bar = document.getElementById('job-progress-bar');
        if (bar) bar.style.width = Math.min(95, attempt * 4) + '%';
        setTimeout(async function () {
            try {
                const res  = await spFetch('<?= site_url('jobs/status/') ?>' + jobId);
                const data = await res.json();
                const st   = data?.job?.status || data?.data?.status || data?.status;
                if (st === 'completed' || st === 'success') {
                    document.getElementById('job-status-box').classList.add('d-none');
                    if (bar) bar.style.width = '100%';
                    resultDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Cadastro facial concluído com sucesso!</div>';
                    setTimeout(() => location.reload(), 2000);
                } else if (st === 'failed' || st === 'error') {
                    document.getElementById('job-status-box').classList.add('d-none');
                    const em = data?.job?.last_error || data?.data?.result?.message || 'Falha no processamento.';
                    resultDiv.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${escHtml(em)}</div>`;
                    setStep(3, true);
                } else {
                    pollJobStatus(jobId, resultDiv, attempt + 1);
                }
            } catch (e) {
                pollJobStatus(jobId, resultDiv, attempt + 1);
            }
        }, 2000);
    }

    // ── Testar reconhecimento (somente auto-cadastro) ─────────────────────
    async function testRecognition() {
        showStatus('Iniciando câmera para teste...', 'info');
        try {
            preview.classList.add('d-none');
            await cam.start(video);
            video.classList.remove('d-none');
            placeholder.classList.add('d-none');
            await new Promise(r => setTimeout(r, 1500));
            const testPhoto = cam.captureFrame(canvas, 0.85);
            cam.stop();
            video.classList.add('d-none');
            if (!testPhoto) { showStatus('Erro ao capturar para teste.', 'danger'); return; }
            showStatus('Analisando reconhecimento...', 'info');
            const response = await spFetch('<?= site_url('biometric/face/test') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'photo=' + encodeURIComponent(testPhoto) + '&<?= csrf_token() ?>=<?= csrf_hash() ?>'
            });
            let result = {};
            try { result = await response.json(); } catch (e) { result = { success: false, message: 'Resposta inválida.' }; }
            const testDiv = document.getElementById('test-result');
            testDiv.classList.remove('d-none');
            if (result.success && result.data?.test_passed) {
                testDiv.innerHTML = `<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Reconhecimento bem-sucedido! Similaridade: ${Math.round((result.data.similarity || 0) * 100)}%</div>`;
                showStatus('Teste concluído com sucesso!', 'success');
            } else {
                testDiv.innerHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>${escHtml(result?.message || 'Teste falhou.')}</div>`;
                showStatus('Teste falhou. Verifique os detalhes.', 'warning');
            }
        } catch (e) {
            showStatus('Erro ao testar: ' + e.message, 'danger');
        }
    }

    // ── Excluir template ──────────────────────────────────────────────────
    async function deleteTemplate(id) {
        try {
            const r = await spFetch('<?= site_url('biometric/face/') ?>' + id, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (r.ok) { location.reload(); }
            else { showStatus('Erro ao excluir template biométrico.', 'danger'); }
        } catch (e) { showStatus('Erro de comunicação.', 'danger'); }
    }

    // ── Revogar consentimento ─────────────────────────────────────────────
    async function revokeConsent() {
        if (!confirm('Tem certeza que deseja revogar o consentimento?\nSeus dados biométricos serão desativados.')) return;
        try {
            const r = await spFetch('<?= site_url('biometric/consent/revoke') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '<?= csrf_token() ?>=<?= csrf_hash() ?>'
            });
            if (r.ok) { location.reload(); }
            else { showStatus('Erro ao revogar consentimento.', 'danger'); }
        } catch (e) { showStatus('Erro de comunicação.', 'danger'); }
    }

    window.addEventListener('beforeunload', () => cam.stop());
});
</script>
<?= $this->endSection() ?>
