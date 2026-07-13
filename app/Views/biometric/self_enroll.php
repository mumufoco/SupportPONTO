<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Minha Biometria<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
/* ── Wizard steps ────────────────────────────────── */
.sp-enroll-wizard { counter-reset: step; }
.sp-step-header {
    display: flex; align-items: center; gap: .75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--sp-border, #e5e7eb);
    cursor: pointer;
    user-select: none;
}
.sp-step-number {
    width: 2rem; height: 2rem; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; flex-shrink: 0;
    background: var(--sp-border, #e5e7eb); color: var(--sp-text-muted);
    transition: all .3s;
}
.sp-step--active .sp-step-number  { background: var(--sp-primary); color: #fff; }
.sp-step--done   .sp-step-number  { background: var(--sp-success); color: #fff; }
.sp-step--done   .sp-step-number::after { content: '✓'; font-size: .9rem; }
.sp-step-body { display: none; padding: 1.5rem 1.25rem; }
.sp-step--active .sp-step-body { display: block; }

/* ── Camera ──────────────────────────────────────── */
.sp-camera-wrap {
    position: relative;
    max-width: 400px; margin: 0 auto;
    border-radius: .75rem; overflow: hidden;
    background: #000;
    aspect-ratio: 4/3;
}
#spVideo {
    width: 100%; height: 100%;
    object-fit: cover; display: block;
}
#spOverlay {
    position: absolute; inset: 0;
    pointer-events: none;
}
.sp-camera-placeholder {
    position: absolute; inset: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: .75rem; color: var(--sp-text-muted);
}

/* ── Guide text ──────────────────────────────────── */
#spGuideText {
    position: absolute; bottom: .75rem; left: 0; right: 0;
    text-align: center; font-size: .8rem;
    color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.8);
}

/* ── Quality bar ─────────────────────────────────── */
.sp-quality-bar { height: 6px; border-radius: 3px; transition: width .4s, background .4s; }

/* ── Fingerprint icon ────────────────────────────── */
.sp-fp-icon { font-size: 5rem; line-height: 1; transition: color .5s; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= $this->include('biometric/partials/_biometric_compliance_bridge') ?>
<div class="container" style="max-width:680px">

    <?= view('components/page_header', [
        'title'    => 'Minha Biometria',
        'subtitle' => 'Cadastre seu rosto e biometria digital para bater ponto.',
        'icon'     => 'bi bi-fingerprint',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('profile')],
        ],
    ]) ?>


    <?php
    // Status geral
    $hasFace        = !empty($faceTemplates);
    $hasFingerprint = !empty($fingerprintTemplates);
    $hasConsent     = $hasConsent ?? false;
    $me             = $currentUser ?? null;
    ?>

    <!-- Resumo de status -->
    <div class="row g-2 mb-4">
        <div class="col-4">
            <div class="card text-center border-0 shadow-sm py-2">
                <div class="fs-3"><?= $hasConsent ? '✅' : '⬜' ?></div>
                <div class="small fw-semibold">Consentimento</div>
                <div class="small text-muted"><?= $hasConsent ? 'Concedido' : 'Pendente' ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center border-0 shadow-sm py-2">
                <div class="fs-3"><?= $hasFace ? '✅' : '⬜' ?></div>
                <div class="small fw-semibold">Rosto</div>
                <div class="small text-muted"><?= $hasFace ? 'Cadastrado' : 'Pendente' ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="card text-center border-0 shadow-sm py-2">
                <div class="fs-3"><?= $hasFingerprint ? '✅' : '⬜' ?></div>
                <div class="small fw-semibold">Digital</div>
                <div class="small text-muted"><?= $hasFingerprint ? 'Cadastrada' : 'Pendente' ?></div>
            </div>
        </div>
    </div>

    <div class="sp-enroll-wizard d-flex flex-column gap-3">

        <!-- ╔══ PASSO 1 — LGPD ═════════════════════════════════╗ -->
        <div class="card shadow-sm sp-step <?= $hasConsent ? 'sp-step--done' : 'sp-step--active' ?>" id="stepLgpd">
            <div class="sp-step-header" onclick="spToggleStep('stepLgpd')">
                <div class="sp-step-number">1</div>
                <div>
                    <div class="fw-semibold">Consentimento LGPD</div>
                    <div class="small text-muted">Autorização para tratamento de dados biométricos</div>
                </div>
                <i class="bi bi-chevron-down ms-auto"></i>
            </div>
            <div class="sp-step-body">
                <?php if ($hasConsent): ?>
                <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-check-circle-fill fs-5"></i>
                    <div>Consentimento concedido. Seus dados biométricos estão protegidos pela LGPD.</div>
                </div>
                <button class="btn btn-outline-danger btn-sm" id="btnRevokeConsent">
                    <i class="bi bi-x-circle me-1"></i>Revogar consentimento
                </button>
                <?php else: ?>
                <div class="alert alert-info mb-3">
                    <p class="fw-semibold mb-1"><i class="bi bi-shield-lock-fill me-2"></i>Termos de Uso — Dados Biométricos</p>
                    <ul class="mb-0 small ps-3">
                        <li><strong>Finalidade:</strong> Registro de ponto por reconhecimento facial.</li>
                        <li><strong>Base legal:</strong> Consentimento (Art. 7º, I da LGPD).</li>
                        <li><strong>Armazenamento:</strong> Criptografado, acessado somente para autenticação.</li>
                        <li><strong>Seus direitos:</strong> Acesso, correção e revogação a qualquer momento.</li>
                    </ul>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="acceptLgpd">
                    <label class="form-check-label small" for="acceptLgpd">
                        Autorizo o tratamento dos meus dados biométricos para fins de controle de ponto.
                    </label>
                </div>
                <button class="btn btn-primary" id="btnGrantConsent" disabled>
                    <i class="bi bi-check-circle me-1"></i>Aceitar e continuar
                </button>
                <div id="lgpdResult" class="mt-2"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ╔══ PASSO 2 — FACIAL ════════════════════════════════╗ -->
        <div class="card shadow-sm sp-step <?= ($hasConsent && $hasFace) ? 'sp-step--done' : ($hasConsent ? 'sp-step--active' : '') ?>"
             id="stepFace">
            <div class="sp-step-header" onclick="spToggleStep('stepFace')">
                <div class="sp-step-number">2</div>
                <div>
                    <div class="fw-semibold">Reconhecimento Facial</div>
                    <div class="small text-muted">Cadastre seu rosto com a câmera</div>
                </div>
                <?php if ($hasFace): ?>
                <span class="badge text-bg-success ms-auto">Cadastrado</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down ms-auto"></i>
            </div>
            <div class="sp-step-body">
                <?php if (!$hasConsent): ?>
                <div class="alert alert-warning">Complete o passo 1 (consentimento LGPD) antes de prosseguir.</div>
                <?php else: ?>

                <?php if ($hasFace): ?>
                <!-- Já tem facial -->
                <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                    <i class="bi bi-camera-video-fill fs-5"></i>
                    <div>Biometria facial cadastrada em <?= date('d/m/Y', strtotime($faceTemplates[0]->created_at ?? 'now')) ?>.</div>
                </div>
                <button class="btn btn-outline-primary me-2" id="btnTestFace">
                    <i class="bi bi-play-circle me-1"></i>Testar reconhecimento
                </button>
                <button class="btn btn-outline-danger" id="btnDeleteFace" data-id="<?= (int)($faceTemplates[0]->id ?? 0) ?>">
                    <i class="bi bi-trash me-1"></i>Recadastrar
                </button>
                <div id="testFaceResult" class="mt-3"></div>
                <?php else: ?>
                <!-- Formulário de cadastro facial -->
                <p class="text-muted small mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    Posicione o rosto dentro da moldura oval. Mantenha boa iluminação e olhe diretamente para a câmera.
                </p>

                <!-- Dicas para uma boa foto -->
                <div class="alert alert-light border small mb-3" id="faceTipsBox">
                    <div class="fw-semibold mb-1"><i class="bi bi-lightbulb me-1"></i>Dicas para uma boa foto</div>
                    <ul class="mb-0 ps-3">
                        <li>Fique a uma distância de braço da câmera (cerca de 40-50cm)</li>
                        <li>Olhe diretamente para a câmera, sem inclinar ou girar a cabeça</li>
                        <li>Centralize o rosto dentro da moldura oval</li>
                        <li>Ambiente bem iluminado, sem luz forte atrás de você</li>
                        <li>Retire óculos escuros, boné ou qualquer objeto cobrindo o rosto</li>
                    </ul>
                </div>

                <!-- Câmera com overlay oval -->
                <div class="sp-camera-wrap mb-3" id="cameraWrap">
                    <video id="spVideo" autoplay muted playsinline></video>
                    <canvas id="spOverlay"></canvas>
                    <div class="sp-camera-placeholder" id="spPlaceholder">
                        <i class="bi bi-camera-video fs-1"></i>
                        <span class="small">Clique em <strong>Iniciar câmera</strong></span>
                    </div>
                    <div id="spGuideText"></div>
                </div>

                <!-- Qualidade -->
                <div class="mb-3 d-none" id="qualityRow">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Qualidade da imagem</span>
                        <span id="qualityLabel">—</span>
                    </div>
                    <div class="bg-light rounded overflow-hidden" style="height:6px">
                        <div class="sp-quality-bar" id="qualityBar" style="width:0%;background:#dc3545"></div>
                    </div>
                </div>

                <!-- Passos de progresso -->
                <div class="d-none mb-3" id="faceSteps">
                    <div class="d-flex align-items-center gap-1">
                        <?php foreach (['Câmera', 'Captura', 'Envio', 'Salvo'] as $i => $label): ?>
                        <div class="d-flex flex-column align-items-center" style="flex:1">
                            <div class="rounded-circle border border-2 d-flex align-items-center justify-content-center mb-1"
                                 style="width:28px;height:28px;font-size:.75rem"
                                 id="fs<?= $i+1 ?>">
                                <?= $i+1 ?>
                            </div>
                            <span style="font-size:.7rem;color:var(--sp-text-muted)"><?= $label ?></span>
                        </div>
                        <?php if ($i < 3): ?>
                        <div style="flex:2;height:2px;background:var(--sp-border);margin-bottom:1.2rem"></div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div id="faceStatusMsg" class="d-none mb-3"></div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" id="btnStartCamera">
                        <i class="bi bi-camera-video me-2"></i>Iniciar câmera
                    </button>
                    <button class="btn btn-success btn-lg d-none" id="btnCapture">
                        <i class="bi bi-camera me-2"></i>Capturar foto
                    </button>
                    <button class="btn btn-outline-secondary d-none" id="btnRetake">
                        <i class="bi bi-arrow-clockwise me-1"></i>Tentar novamente
                    </button>
                    <button class="btn btn-success btn-lg d-none" id="btnEnroll">
                        <i class="bi bi-check-circle me-2"></i>Cadastrar biometria facial
                    </button>
                </div>
                <div id="enrollResult" class="mt-3"></div>
                <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- ╔══ PASSO 3 — DIGITAL ═══════════════════════════════╗ -->
        <div class="card shadow-sm sp-step <?= ($hasConsent && $hasFingerprint) ? 'sp-step--done' : ($hasConsent && $hasFace ? 'sp-step--active' : '') ?>"
             id="stepFp">
            <div class="sp-step-header" onclick="spToggleStep('stepFp')">
                <div class="sp-step-number">3</div>
                <div>
                    <div class="fw-semibold">Biometria Digital</div>
                    <div class="small text-muted">Verificar suporte e testar leitor de impressão digital</div>
                </div>
                <?php if ($hasFingerprint): ?>
                <span class="badge text-bg-success ms-auto">Cadastrada</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down ms-auto"></i>
            </div>
            <div class="sp-step-body">
                <?php if (!$hasConsent): ?>
                <div class="alert alert-warning">Complete o passo 1 antes de prosseguir.</div>
                <?php else: ?>

                <div class="text-center mb-4">
                    <i class="bi bi-fingerprint sp-fp-icon" id="fpIcon" style="color:var(--sp-text-muted)"></i>
                    <div class="mt-2 small text-muted" id="fpSupportLabel">Verificando suporte...</div>
                </div>

                <!-- Checklist de suporte -->
                <ul class="list-group list-group-flush mb-4" id="fpChecklist">
                    <li class="list-group-item d-flex align-items-center gap-2 px-0" id="chkHttps">
                        <span class="spinner-border spinner-border-sm text-secondary" style="flex-shrink:0"></span>
                        <span>Conexão segura (HTTPS)</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-2 px-0" id="chkWebAuthn">
                        <span class="spinner-border spinner-border-sm text-secondary" style="flex-shrink:0"></span>
                        <span>Suporte a biometria (WebAuthn)</span>
                    </li>
                    <li class="list-group-item d-flex align-items-center gap-2 px-0" id="chkPlatform">
                        <span class="spinner-border spinner-border-sm text-secondary" style="flex-shrink:0"></span>
                        <span>Autenticador de plataforma disponível</span>
                    </li>
                </ul>

                <div id="fpMessage" class="alert alert-info d-none"></div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg d-none" id="btnTestFp">
                        <i class="bi bi-fingerprint me-2"></i>Testar biometria digital
                    </button>
                    <button class="btn btn-success btn-lg d-none" id="btnEnrollFp">
                        <i class="bi bi-plus-circle me-2"></i>Registrar digital neste dispositivo
                    </button>
                </div>

                <div class="alert alert-light border mt-3 small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>Nota:</strong> O leitor de impressão digital dedicado (dispositivo USB) é cadastrado
                    pelo administrador em <a href="<?= site_url('biometric/fingerprint/enroll/' . ($me->id ?? '')) ?>">Gestão Biométrica</a>.
                    Esta verificação testa a biometria do seu próprio dispositivo (celular/notebook).
                </div>

                <?php endif; ?>
            </div>
        </div>

    </div><!-- wizard -->

</div><!-- container -->
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/camera-manager.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?>>
/* ============================================================
   Configuração de URLs e dados
   ============================================================ */
const SP_ENROLL = {
    consentUrl:  '<?= site_url('biometric/consent') ?>',
    revokeUrl:   '<?= site_url('biometric/consent/revoke') ?>',
    enrollUrl:   '<?= site_url('biometric/face/enroll') ?>',
    testUrl:     '<?= site_url('biometric/face/test') ?>',
    deleteUrl:   '<?= site_url('biometric/face/') ?>',
    csrfToken:   '<?= csrf_token() ?>',
    csrfHash:    '<?= csrf_hash() ?>',
    hasConsent:  <?= $hasConsent ? 'true' : 'false' ?>,
    hasFace:     <?= $hasFace ? 'true' : 'false' ?>,
    hasFingerprint: <?= $hasFingerprint ? 'true' : 'false' ?>,
};

/* ============================================================
   Utilitários
   ============================================================ */
function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
}

function showMsg(id, text, type) {
    type = type || 'info';
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'alert alert-' + type + ' mb-0';
    el.innerHTML = text;
    el.classList.remove('d-none');
}

function spToggleStep(id) {
    const card = document.getElementById(id);
    if (!card) return;
    const body = card.querySelector('.sp-step-body');
    if (body) body.style.display = body.style.display === 'block' ? 'none' : 'block';
}

/* ============================================================
   Wizard — step helpers
   ============================================================ */
function markStepDone(stepId) {
    const el = document.getElementById(stepId);
    if (!el) return;
    el.classList.remove('sp-step--active');
    el.classList.add('sp-step--done');
}

function activateStep(stepId) {
    const el = document.getElementById(stepId);
    if (!el) return;
    if (!el.classList.contains('sp-step--done')) {
        el.classList.add('sp-step--active');
    }
    const body = el.querySelector('.sp-step-body');
    if (body) body.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ============================================================
   PASSO 1 — LGPD
   ============================================================ */
(function () {
    const chk = document.getElementById('acceptLgpd');
    const btn = document.getElementById('btnGrantConsent');
    if (chk && btn) {
        chk.addEventListener('change', () => { btn.disabled = !chk.checked; });
    }

    if (btn) {
        btn.addEventListener('click', async function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Salvando...';
            try {
                const body = SP_ENROLL.csrfToken + '=' + encodeURIComponent(SP_ENROLL.csrfHash);
                const r = await spFetch(SP_ENROLL.consentUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body,
                });
                if (r.ok) {
                    markStepDone('stepLgpd');
                    activateStep('stepFace');
                    location.reload();
                } else {
                    showMsg('lgpdResult', 'Erro ao salvar consentimento. Tente novamente.', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aceitar e continuar';
                }
            } catch (e) {
                showMsg('lgpdResult', 'Erro de comunicação.', 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Aceitar e continuar';
            }
        });
    }

    const revokeBtn = document.getElementById('btnRevokeConsent');
    if (revokeBtn) {
        revokeBtn.addEventListener('click', async function () {
            if (!confirm('Tem certeza que deseja revogar o consentimento? Seus dados biométricos serão excluídos.')) return;
            revokeBtn.disabled = true;
            try {
                const r = await spFetch(SP_ENROLL.revokeUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: SP_ENROLL.csrfToken + '=' + encodeURIComponent(SP_ENROLL.csrfHash),
                });
                if (r.ok) location.reload();
                else { alert('Erro ao revogar. Tente novamente.'); revokeBtn.disabled = false; }
            } catch (e) { alert('Erro de comunicação.'); revokeBtn.disabled = false; }
        });
    }
})();

/* ============================================================
   PASSO 2 — FACIAL
   ============================================================ */
(function () {
    const video      = document.getElementById('spVideo');
    const overlay    = document.getElementById('spOverlay');
    const placeholder= document.getElementById('spPlaceholder');
    const guideText  = document.getElementById('spGuideText');
    const qualityRow = document.getElementById('qualityRow');
    const qualityBar = document.getElementById('qualityBar');
    const qualityLbl = document.getElementById('qualityLabel');

    if (!video) return; // Já tem facial cadastrado

    const cam = CameraManager.getInstance();
    let capturedDataUrl = null;
    let captureCanvas = null;
    let overlayRaf = null;

    // Detecção de posição do rosto (afastar/aproximar/centralizar) via Shape
    // Detection API — disponível em navegadores Chromium. Quando indisponível,
    // a análise cai de volta para só o heurístico de brilho (comportamento
    // anterior), sem quebrar nada.
    const hasFaceDetector = 'FaceDetector' in window;
    const faceDetector = hasFaceDetector ? new FaceDetector({ fastMode: true, maxDetectedFaces: 1 }) : null;
    let faceDetectBusy = false;
    let lastFaceHint = null;
    let lastFaceHintAt = 0;

    function detectFacePositionAsync() {
        if (!faceDetector || faceDetectBusy || !captureCanvas || captureCanvas.width === 0) return;
        faceDetectBusy = true;
        faceDetector.detect(captureCanvas).then(faces => {
            faceDetectBusy = false;
            const W = captureCanvas.width, H = captureCanvas.height;
            if (!faces || faces.length === 0) {
                lastFaceHint = { guide: 'Nenhum rosto detectado — posicione seu rosto dentro do oval', ok: false };
                return;
            }
            const box = faces[0].boundingBox;
            const faceCx = box.x + box.width / 2;
            const faceCy = box.y + box.height / 2;
            const ovalCx = W / 2, ovalCy = H * 0.46;
            const offsetX = Math.abs(faceCx - ovalCx) / W;
            const offsetY = Math.abs(faceCy - ovalCy) / H;
            const faceHeightRatio = box.height / H;

            if (faceHeightRatio < 0.32) {
                lastFaceHint = { guide: 'Aproxime-se mais da câmera', ok: false };
            } else if (faceHeightRatio > 0.70) {
                lastFaceHint = { guide: 'Afaste-se um pouco da câmera', ok: false };
            } else if (offsetX > 0.14 || offsetY > 0.14) {
                lastFaceHint = { guide: 'Centralize o rosto dentro da moldura oval', ok: false };
            } else {
                lastFaceHint = { guide: null, ok: true };
            }
            lastFaceHintAt = Date.now();
        }).catch(() => {
            faceDetectBusy = false;
            // Shape Detection pode falhar silenciosamente em alguns navegadores
            // (ex.: modelo ainda não carregado) — apenas ignora e segue com o
            // heurístico de brilho.
        });
    }

    /* ── Overlay oval na câmera ────────────────────────── */
    function drawOval(ovalColor, quality) {
        if (!overlay || !video) return;
        const W = overlay.width  = video.videoWidth  || video.clientWidth  || 400;
        const H = overlay.height = video.videoHeight || video.clientHeight || 300;
        const ctx = overlay.getContext('2d');
        ctx.clearRect(0, 0, W, H);

        // Fundo escurecido fora do oval
        ctx.save();
        ctx.fillStyle = 'rgba(0,0,0,.45)';
        ctx.fillRect(0, 0, W, H);
        ctx.globalCompositeOperation = 'destination-out';
        const rx = W * 0.33, ry = H * 0.42;
        const cx = W / 2,    cy = H * 0.46;
        ctx.beginPath();
        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.restore();

        // Borda oval colorida
        ctx.save();
        ctx.strokeStyle = ovalColor;
        ctx.lineWidth = 3;
        ctx.shadowColor = ovalColor;
        ctx.shadowBlur = 8;
        ctx.beginPath();
        ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
        ctx.stroke();

        // Marcadores de canto (guia de posição)
        const corners = [
            [cx - rx * 0.7, cy - ry * 0.9],
            [cx + rx * 0.7, cy - ry * 0.9],
            [cx - rx * 0.7, cy + ry * 0.9],
            [cx + rx * 0.7, cy + ry * 0.9],
        ];
        ctx.lineWidth = 2; ctx.shadowBlur = 0;
        corners.forEach(([x, y]) => {
            ctx.beginPath();
            ctx.arc(x, y, 4, 0, Math.PI * 2);
            ctx.fillStyle = ovalColor;
            ctx.fill();
        });
        ctx.restore();
    }

    /* ── Análise de qualidade em tempo real ────────────── */
    function analyzeFrame() {
        if (!video || video.paused || video.ended) return;
        const W = video.videoWidth || 400;
        const H = video.videoHeight || 300;
        if (!captureCanvas) {
            captureCanvas = document.createElement('canvas');
        }
        captureCanvas.width = W;
        captureCanvas.height = H;
        const ctx = captureCanvas.getContext('2d');
        ctx.drawImage(video, 0, 0, W, H);

        // Amostragem da região central (oval)
        const sX = Math.floor(W * 0.25), sY = Math.floor(H * 0.15);
        const sW = Math.floor(W * 0.5),  sH = Math.floor(H * 0.6);
        const imgData = ctx.getImageData(sX, sY, sW, sH).data;
        let brightness = 0, samples = 0;
        for (let i = 0; i < imgData.length; i += 16) { // every 4th pixel
            brightness += (imgData[i] + imgData[i+1] + imgData[i+2]) / 3;
            samples++;
        }
        brightness = brightness / (samples || 1);

        let score = 0;
        let color = '#ef4444';
        let label = 'Ruim';
        let guide = 'Melhore a iluminação — fique em local mais claro';

        if (brightness >= 40 && brightness <= 220) {
            score = Math.round(((brightness - 40) / 180) * 60 + 40);
            if (score >= 75) { color = '#22c55e'; label = 'Ótima'; guide = 'Perfeito! Clique em Capturar foto'; }
            else if (score >= 55) { color = '#f59e0b'; label = 'Boa'; guide = 'Boa iluminação — continue assim'; }
            else { color = '#ef4444'; label = 'Ruim'; guide = brightness < 40 ? 'Muito escuro — acenda a luz' : 'Muito claro — evite luz direta'; }
        }

        // Dispara detecção de posição do rosto de tempos em tempos (não a cada
        // frame, para não sobrecarregar) — quando disponível no navegador.
        if (hasFaceDetector) {
            detectFacePositionAsync();
            // Hint de posição (distância/centralização) tem prioridade sobre o
            // de iluminação enquanto for recente (< 1.5s) — evita competir com
            // a mensagem de brilho quando ambos teriam algo a dizer.
            if (lastFaceHint && (Date.now() - lastFaceHintAt) < 1500) {
                if (!lastFaceHint.ok) {
                    guide = lastFaceHint.guide;
                    color = '#ef4444';
                    score = Math.min(score, 30);
                    label = 'Reposicione';
                } else if (score >= 75) {
                    guide = 'Perfeito! Clique em Capturar foto';
                }
            }
        }

        drawOval(color, score);
        if (qualityBar) { qualityBar.style.width = score + '%'; qualityBar.style.background = color; }
        if (qualityLbl) qualityLbl.textContent = label + ' (' + score + '%)';
        if (guideText)  guideText.textContent = guide;

        overlayRaf = requestAnimationFrame(analyzeFrame);
    }

    /* ── Atualiza step visual ─────────────────────────── */
    function setFaceStep(n, ok) {
        document.getElementById('faceSteps').classList.remove('d-none');
        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById('fs' + i);
            if (!el) continue;
            el.className = 'rounded-circle border border-2 d-flex align-items-center justify-content-center mb-1';
            if (i < n) { el.style.background='#198754'; el.style.color='#fff'; el.style.borderColor='#198754'; el.textContent='✓'; }
            else if (i === n) {
                el.style.background = ok === false ? '#dc3545' : '#0d6efd';
                el.style.borderColor = ok === false ? '#dc3545' : '#0d6efd';
                el.style.color = '#fff';
                el.textContent = ok === false ? '✕' : i;
            }
            else { el.style.background=''; el.style.color=''; el.style.borderColor=''; el.textContent=i; }
        }
    }

    /* ── Iniciar câmera ─────────────────────────────── */
    document.getElementById('btnStartCamera')?.addEventListener('click', async function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Iniciando...';
        try {
            await cam.start(video, { facingMode: 'user', width: 640, height: 480 });
            placeholder.style.display = 'none';
            qualityRow.classList.remove('d-none');
            this.classList.add('d-none');
            document.getElementById('btnCapture').classList.remove('d-none');
            setFaceStep(1, true);
            analyzeFrame();
        } catch (e) {
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-camera-video me-2"></i>Iniciar câmera';
            showMsg('faceStatusMsg', '<i class="bi bi-exclamation-triangle me-2"></i>Não foi possível acessar a câmera: ' + escHtml(e.message) + '. Verifique as permissões do navegador.', 'danger');
            document.getElementById('faceStatusMsg').classList.remove('d-none');
            setFaceStep(1, false);
        }
    });

    /* ── Capturar foto ─────────────────────────────── */
    document.getElementById('btnCapture')?.addEventListener('click', function () {
        cancelAnimationFrame(overlayRaf);
        if (!captureCanvas || captureCanvas.width === 0) {
            captureCanvas = document.createElement('canvas');
            captureCanvas.width = video.videoWidth || 640;
            captureCanvas.height = video.videoHeight || 480;
            captureCanvas.getContext('2d').drawImage(video, 0, 0);
        }
        capturedDataUrl = captureCanvas.toDataURL('image/jpeg', 0.88);
        cam.stop();
        cancelAnimationFrame(overlayRaf);

        // Congela o overlay com um snapshot
        const ctx = overlay.getContext('2d');
        ctx.drawImage(captureCanvas, 0, 0, overlay.width, overlay.height);
        if (guideText) guideText.textContent = 'Foto capturada!';

        this.classList.add('d-none');
        document.getElementById('btnRetake').classList.remove('d-none');
        document.getElementById('btnEnroll').classList.remove('d-none');
        setFaceStep(2, true);
        showMsg('faceStatusMsg', '<i class="bi bi-check-circle me-2"></i>Foto capturada! Confira a imagem e clique em <strong>Cadastrar biometria facial</strong>.', 'success');
        document.getElementById('faceStatusMsg').classList.remove('d-none');
    });

    /* ── Tentar novamente ──────────────────────────── */
    document.getElementById('btnRetake')?.addEventListener('click', async function () {
        capturedDataUrl = null;
        document.getElementById('btnEnroll').classList.add('d-none');
        this.classList.add('d-none');
        document.getElementById('btnCapture').classList.remove('d-none');
        document.getElementById('enrollResult').innerHTML = '';
        document.getElementById('enrollResult').classList.add('d-none');
        try {
            await cam.start(video, { facingMode: 'user', width: 640, height: 480 });
            analyzeFrame();
            setFaceStep(1, true);
            showMsg('faceStatusMsg', 'Câmera reiniciada. Posicione o rosto e clique em <strong>Capturar foto</strong>.', 'info');
        } catch (e) {
            showMsg('faceStatusMsg', 'Erro ao reiniciar câmera: ' + escHtml(e.message), 'danger');
        }
    });

    /* ── Cadastrar biometria ────────────────────────── */
    document.getElementById('btnEnroll')?.addEventListener('click', async function () {
        if (!capturedDataUrl) { showMsg('enrollResult', 'Capture uma foto primeiro.', 'warning'); return; }
        const orig = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
        setFaceStep(3, null);
        const base64 = capturedDataUrl.split(',')[1] || capturedDataUrl;
        try {
            const r = await spFetch(SP_ENROLL.enrollUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'photo=' + encodeURIComponent(base64) + '&' + SP_ENROLL.csrfToken + '=' + encodeURIComponent(SP_ENROLL.csrfHash),
            });
            let data = {};
            try { data = await r.json(); } catch(_) {}

            if (data.success) {
                setFaceStep(4, true);
                const jobId = data.data?.job_id;
                if (jobId) {
                    showMsg('enrollResult', '<span class="spinner-border spinner-border-sm me-2"></span>Processando biometria... aguarde.', 'info');
                    pollJob(jobId);
                } else {
                    showMsg('enrollResult', '<i class="bi bi-check-circle me-2"></i>Biometria facial cadastrada com sucesso!', 'success');
                    setTimeout(() => location.reload(), 2500);
                }
            } else {
                setFaceStep(3, false);
                showMsg('enrollResult', '<i class="bi bi-exclamation-triangle me-2"></i>' + escHtml(data.message || 'Erro ao cadastrar. Tente novamente.'), 'danger');
            }
        } catch (e) {
            setFaceStep(3, false);
            showMsg('enrollResult', 'Erro de comunicação com o servidor.', 'danger');
        }
        this.disabled = false;
        this.innerHTML = orig;
    });

    /* ── Polling de job assíncrono ─────────────────── */
    function pollJob(jobId, attempt) {
        attempt = attempt || 1;
        if (attempt > 30) {
            showMsg('enrollResult', 'Processamento em andamento. Recarregue a página em breve.', 'warning');
            return;
        }
        setTimeout(async function () {
            try {
                const r = await spFetch('<?= site_url('jobs/status/') ?>' + jobId);
                const d = await r.json();
                const st = d?.job?.status || d?.data?.status || d?.status;
                if (st === 'completed' || st === 'success') {
                    setFaceStep(4, true);
                    showMsg('enrollResult', '<i class="bi bi-check-circle me-2"></i>Biometria cadastrada com sucesso!', 'success');
                    setTimeout(() => location.reload(), 2000);
                } else if (st === 'failed' || st === 'error') {
                    setFaceStep(3, false);
                    const em = d?.job?.last_error || d?.data?.result?.message || 'Falha no processamento.';
                    showMsg('enrollResult', '<i class="bi bi-x-circle me-2"></i>' + escHtml(em), 'danger');
                } else {
                    pollJob(jobId, attempt + 1);
                }
            } catch (_) { pollJob(jobId, attempt + 1); }
        }, 2200);
    }

    /* ── Testar reconhecimento ─────────────────────── */
    document.getElementById('btnTestFace')?.addEventListener('click', async function () {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Abrindo câmera...';
        const testVideo = document.createElement('video');
        testVideo.autoplay = true; testVideo.muted = true; testVideo.playsInline = true;
        testVideo.style.cssText = 'display:none';
        document.body.appendChild(testVideo);
        try {
            await cam.start(testVideo, { facingMode: 'user' });
            await new Promise(r => setTimeout(r, 1800));
            const tc = document.createElement('canvas');
            tc.width = testVideo.videoWidth || 640;
            tc.height = testVideo.videoHeight || 480;
            tc.getContext('2d').drawImage(testVideo, 0, 0);
            const testB64 = tc.toDataURL('image/jpeg', 0.88).split(',')[1];
            cam.stop();
            document.body.removeChild(testVideo);
            const r = await spFetch(SP_ENROLL.testUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'photo=' + encodeURIComponent(testB64) + '&' + SP_ENROLL.csrfToken + '=' + encodeURIComponent(SP_ENROLL.csrfHash),
            });
            let d = {}; try { d = await r.json(); } catch(_) {}
            if (d.success && d.data?.test_passed) {
                showMsg('testFaceResult', '<i class="bi bi-check-circle me-2 text-success"></i>Reconhecido! Similaridade: <strong>' + Math.round((d.data.similarity || 0) * 100) + '%</strong>', 'success');
            } else {
                showMsg('testFaceResult', '<i class="bi bi-exclamation-triangle me-2"></i>' + escHtml(d.message || 'Rosto não reconhecido. Considere recadastrar.'), 'warning');
            }
        } catch (e) {
            if (testVideo.parentNode) { cam.stop(); document.body.removeChild(testVideo); }
            showMsg('testFaceResult', 'Erro ao acessar câmera para teste: ' + escHtml(e.message), 'danger');
        }
        this.disabled = false;
        this.innerHTML = '<i class="bi bi-play-circle me-1"></i>Testar reconhecimento';
    });

    /* ── Deletar / recadastrar ──────────────────────── */
    document.getElementById('btnDeleteFace')?.addEventListener('click', async function () {
        if (!confirm('Isso removerá seu cadastro facial e você precisará refazer. Confirmar?')) return;
        const id = this.dataset.id;
        try {
            const r = await spFetch(SP_ENROLL.deleteUrl + id, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (r.ok) location.reload();
            else showMsg('testFaceResult', 'Erro ao remover. Tente novamente.', 'danger');
        } catch (e) { showMsg('testFaceResult', 'Erro de comunicação.', 'danger'); }
    });

    window.addEventListener('beforeunload', () => { cancelAnimationFrame(overlayRaf); cam.stop(); });
})();

/* ============================================================
   PASSO 3 — DIGITAL (checklist WebAuthn)
   ============================================================ */
(function () {
    const fpIcon  = document.getElementById('fpIcon');
    const fpLabel = document.getElementById('fpSupportLabel');
    const btnTest = document.getElementById('btnTestFp');
    const btnEnrollFp = document.getElementById('btnEnrollFp');

    function setCheck(id, ok, text) {
        const el = document.getElementById(id);
        if (!el) return;
        const icon = el.querySelector('.spinner-border, .bi');
        if (icon) {
            icon.className = ok ? 'bi bi-check-circle-fill text-success' : 'bi bi-x-circle-fill text-danger';
            icon.style.flexShrink = '0';
        }
        if (text) el.querySelector('span:last-child').textContent = text;
    }

    async function runChecklist() {
        await new Promise(r => setTimeout(r, 600));

        // HTTPS check
        const isHttps = location.protocol === 'https:' || location.hostname === 'localhost';
        setCheck('chkHttps', isHttps, isHttps ? 'Conexão segura (HTTPS) ✓' : 'HTTPS necessário para biometria');

        await new Promise(r => setTimeout(r, 400));

        // WebAuthn check
        const hasWebAuthn = !!window.PublicKeyCredential;
        setCheck('chkWebAuthn', hasWebAuthn, hasWebAuthn ? 'WebAuthn suportado ✓' : 'Navegador não suporta WebAuthn');

        await new Promise(r => setTimeout(r, 400));

        // Platform authenticator
        let hasPlatform = false;
        if (hasWebAuthn) {
            try {
                hasPlatform = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            } catch (_) { hasPlatform = false; }
        }
        setCheck('chkPlatform', hasPlatform, hasPlatform ? 'Biometria do dispositivo disponível ✓' : 'Nenhum autenticador de plataforma detectado');

        // Resultado
        if (isHttps && hasWebAuthn && hasPlatform) {
            if (fpIcon) fpIcon.style.color = '#22c55e';
            if (fpLabel) fpLabel.textContent = 'Dispositivo compatível com biometria digital!';
            if (btnTest) btnTest.classList.remove('d-none');
            if (!SP_ENROLL.hasFingerprint && btnEnrollFp) btnEnrollFp.classList.remove('d-none');
        } else {
            if (fpIcon) fpIcon.style.color = '#ef4444';
            if (fpLabel) fpLabel.textContent = 'Dispositivo não totalmente compatível.';
            const msg = document.getElementById('fpMessage');
            if (msg) {
                msg.classList.remove('d-none');
                if (!isHttps) msg.innerHTML = '<i class="bi bi-lock me-2"></i>Acesse o sistema via HTTPS para usar biometria digital.';
                else if (!hasWebAuthn) msg.innerHTML = '<i class="bi bi-browser-chrome me-2"></i>Use um navegador moderno (Chrome, Firefox, Edge, Safari) para biometria digital.';
                else msg.innerHTML = '<i class="bi bi-phone me-2"></i>Seu dispositivo não possui sensor biométrico compatível. O leitor USB pode ser usado pelo administrador.';
            }
        }
    }

    // Testar via WebAuthn assertion (não cria credencial, apenas verifica)
    if (btnTest) {
        btnTest.addEventListener('click', async function () {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aguardando biometria...';
            const msgEl = document.getElementById('fpMessage');
            if (msgEl) { msgEl.classList.add('d-none'); msgEl.innerHTML = ''; }
            try {
                // Gera um challenge aleatório para o teste
                const challenge = new Uint8Array(32);
                crypto.getRandomValues(challenge);
                const cred = await navigator.credentials.get({
                    publicKey: {
                        challenge: challenge,
                        timeout: 30000,
                        userVerification: 'required',
                        rpId: location.hostname,
                    }
                });
                if (cred) {
                    if (fpIcon) fpIcon.style.color = '#22c55e';
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-success'; msgEl.innerHTML = '<i class="bi bi-check-circle me-2"></i><strong>Biometria verificada com sucesso!</strong> Seu dispositivo está pronto.'; }
                }
            } catch (e) {
                if (e.name === 'NotAllowedError') {
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-warning'; msgEl.innerHTML = '<i class="bi bi-hand-index me-2"></i>Verificação cancelada ou tempo esgotado.'; }
                } else if (e.name === 'InvalidStateError' || e.name === 'NotSupportedError') {
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-info'; msgEl.innerHTML = '<i class="bi bi-info-circle me-2"></i>Nenhuma credencial registrada ainda. Clique em <strong>Registrar digital</strong> para cadastrar.'; }
                    if (btnEnrollFp) btnEnrollFp.classList.remove('d-none');
                } else {
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-danger'; msgEl.innerHTML = '<i class="bi bi-x-circle me-2"></i>' + escHtml(e.message); }
                }
            }
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-fingerprint me-2"></i>Testar biometria digital';
        });
    }

    if (btnEnrollFp) {
        btnEnrollFp.addEventListener('click', async function () {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Aguardando...';
            const msgEl = document.getElementById('fpMessage');
            try {
                const challenge = new Uint8Array(32);
                crypto.getRandomValues(challenge);
                const userId = new Uint8Array(8);
                crypto.getRandomValues(userId);
                const cred = await navigator.credentials.create({
                    publicKey: {
                        challenge: challenge,
                        rp: { name: 'SupportPONTO', id: location.hostname },
                        user: { id: userId, name: '<?= addslashes($me->email ?? 'user') ?>', displayName: '<?= addslashes($me->name ?? 'Usuário') ?>' },
                        pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
                        authenticatorSelection: { authenticatorAttachment: 'platform', userVerification: 'required' },
                        timeout: 60000,
                    }
                });
                if (cred) {
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-success'; msgEl.innerHTML = '<i class="bi bi-check-circle me-2"></i><strong>Digital registrada neste dispositivo!</strong> Você pode usar biometria digital para bater ponto neste aparelho.'; }
                    if (fpIcon) fpIcon.style.color = '#22c55e';
                    if (btnEnrollFp) btnEnrollFp.classList.add('d-none');
                }
            } catch (e) {
                if (e.name === 'NotAllowedError') {
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-warning'; msgEl.innerHTML = 'Cadastro cancelado.'; }
                } else {
                    if (msgEl) { msgEl.classList.remove('d-none'); msgEl.className = 'alert alert-danger'; msgEl.innerHTML = escHtml(e.message); }
                }
            }
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Registrar digital neste dispositivo';
        });
    }

    runChecklist();
})();
</script>
<?= $this->endSection() ?>
