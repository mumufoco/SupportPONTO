<?= $this->extend('layouts/kiosk') ?>
<?= $this->section('title') ?>Registro Rápido - SupportPONTO<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$methodReadiness = $methodReadiness ?? [];
$buttonOrder = ['codigo', 'cpf', 'qrcode', 'facial', 'biometria'];
$labels = [
    'codigo'    => ['icon' => 'bi-person-badge',       'title' => 'Código',                'desc' => 'Digite o código do colaborador'],
    'cpf'       => ['icon' => 'bi-card-text',           'title' => 'CPF',                   'desc' => 'Informe o CPF para registrar'],
    'qrcode'    => ['icon' => 'bi-qr-code-scan',        'title' => 'QR Code',               'desc' => 'Escaneie o QR Code do crachá'],
    'facial'    => ['icon' => 'bi-person-bounding-box', 'title' => 'Reconhecimento Facial', 'desc' => 'Identifique-se pelo rosto'],
    'biometria' => ['icon' => 'bi-fingerprint',         'title' => 'Biometria Digital',     'desc' => 'Use a impressão digital'],
];
?>

<style>
.sp-vstepper{display:flex;flex-direction:column}
.sp-vstepper__step{display:flex;gap:1rem}
.sp-vstepper__rail{display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:2.25rem}
.sp-vstepper__num{width:2.25rem;height:2.25rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.88rem;flex-shrink:0;border:2px solid var(--sp-border);background:var(--sp-gray-100);color:var(--sp-text-muted);transition:background .2s,color .2s,border-color .2s;z-index:1}
.sp-vstepper__step.is-active .sp-vstepper__num{background:var(--sp-primary);color:#fff;border-color:var(--sp-primary)}
.sp-vstepper__step.is-done .sp-vstepper__num{background:var(--sp-success);color:#fff;border-color:var(--sp-success)}
.sp-vstepper__line{width:2px;flex:1;min-height:1.25rem;background:var(--sp-border);margin:.2rem 0}
.sp-vstepper__step:last-child .sp-vstepper__line{display:none}
.sp-vstepper__body{flex:1;padding-bottom:1.25rem}
.sp-vstepper__title{font-weight:600;font-size:.95rem;color:var(--sp-text-primary);padding:.4rem 0 .6rem;display:flex;align-items:center;gap:.4rem}
.sp-vstepper__step.is-active .sp-vstepper__title{color:var(--sp-primary-dark)}
.sp-vstepper__step.is-done .sp-vstepper__title{color:var(--sp-success)}
.punch-type-btn{transition:all .15s}
.method-btn{transition:all .15s}
</style>

<div class="container-fluid px-3 py-3" style="max-width:900px;margin:0 auto;">

    <!-- Cabeçalho -->
    <div class="text-center mb-4">
        <img src="<?= support_logo_url('auth') ?>" alt="SupportPONTO" style="width:150px;height:150px;object-fit:contain;">
    </div>

    <!-- Resultado -->
    <div id="quickPunchResult" class="mb-3"></div>

    <div class="sp-vstepper">

        <!-- PASSO 1: Tipo de registro -->
        <div class="sp-vstepper__step is-active" id="qs1">
            <div class="sp-vstepper__rail">
                <div class="sp-vstepper__num" id="qs1Num">1</div>
                <div class="sp-vstepper__line"></div>
            </div>
            <div class="sp-vstepper__body">
                <div class="sp-vstepper__title"><i class="bi bi-clock"></i> Tipo de registro</div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap gap-2" id="punchTypeGroup">
                            <?php foreach ([
                                'entrada'          => ['label' => 'Entrada',            'icon' => 'bi-box-arrow-in-right', 'color' => 'success'],
                                'intervalo_inicio' => ['label' => 'Início Intervalo',   'icon' => 'bi-pause-circle',       'color' => 'warning'],
                                'intervalo_fim'    => ['label' => 'Fim Intervalo',      'icon' => 'bi-play-circle',        'color' => 'info'],
                                'saida'            => ['label' => 'Saída',              'icon' => 'bi-box-arrow-right',    'color' => 'danger'],
                            ] as $value => $item): ?>
                                <button type="button"
                                        class="btn <?= $value === 'entrada' ? 'btn-'.$item['color'] : 'btn-outline-'.$item['color'] ?> flex-grow-1 punch-type-btn<?= $value === 'entrada' ? ' active' : '' ?>"
                                        data-color="<?= $item['color'] ?>"
                                        data-value="<?= $value ?>">
                                    <i class="bi <?= $item['icon'] ?> me-1"></i><?= $item['label'] ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" id="punch_type" value="entrada">
                    </div>
                </div>
            </div>
        </div>

        <!-- PASSO 2: Método de identificação -->
        <div class="sp-vstepper__step" id="qs2">
            <div class="sp-vstepper__rail">
                <div class="sp-vstepper__num" id="qs2Num">2</div>
                <div class="sp-vstepper__line"></div>
            </div>
            <div class="sp-vstepper__body">
                <div class="sp-vstepper__title"><i class="bi bi-grid-3x3-gap"></i> Como deseja se identificar?</div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="row g-2" id="methodSelector">
                            <?php foreach ($buttonOrder as $key):
                                $method  = $methodReadiness[$key] ?? null;
                                $enabled = filter_var($method['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
                                $info    = $labels[$key];
                            ?>
                            <div class="col">
                                <button type="button"
                                        class="btn w-100 h-100 py-3 method-btn border d-flex flex-column align-items-center gap-1
                                               <?= $enabled ? 'btn-outline-primary' : 'btn-outline-secondary opacity-50' ?>"
                                        data-method="<?= $key ?>"
                                        <?= !$enabled ? 'disabled aria-disabled="true"' : '' ?>
                                        title="<?= $enabled ? esc($info['desc']) : 'Método desabilitado nas configurações.' ?>">
                                    <i class="bi <?= $info['icon'] ?> fs-4"></i>
                                    <span class="small fw-semibold"><?= $info['title'] ?></span>
                                    <?php if (!$enabled): ?>
                                        <span class="badge bg-secondary" style="font-size:.62rem">Inativo</span>
                                    <?php endif; ?>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PASSO 3: Identificação -->
        <div class="sp-vstepper__step" id="qs3">
            <div class="sp-vstepper__rail">
                <div class="sp-vstepper__num" id="qs3Num">3</div>
                <div class="sp-vstepper__line"></div>
            </div>
            <div class="sp-vstepper__body">
                <div class="sp-vstepper__title"><i class="bi bi-check2-circle"></i> Identificação</div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">

                        <!-- Por código -->
                        <form id="form-codigo" class="method-form" data-method="codigo">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-person-badge me-1 text-primary"></i>Código do colaborador
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-lg" name="unique_code"
                                       placeholder="Ex.: COL001" autofocus autocomplete="off">
                                <button class="btn btn-success px-4" type="submit">
                                    <i class="bi bi-check-lg me-1"></i>Registrar
                                </button>
                            </div>
                            <div class="form-text">Código único impresso no crachá ou fornecido pelo RH.</div>
                        </form>

                        <!-- Por CPF -->
                        <form id="form-cpf" class="method-form" style="display:none;" data-method="cpf">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-card-text me-1 text-primary"></i>CPF
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-lg" id="cpf" name="cpf"
                                       placeholder="000.000.000-00" inputmode="numeric" autocomplete="off">
                                <button class="btn btn-success px-4" type="submit">
                                    <i class="bi bi-check-lg me-1"></i>Registrar
                                </button>
                            </div>
                        </form>

                        <!-- Por QR Code -->
                        <form id="form-qrcode" class="method-form" style="display:none;" data-method="qrcode">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-qr-code-scan me-1 text-primary"></i>QR Code do crachá
                            </label>
                            <p class="text-muted small mb-2">Posicione o QR Code na frente da câmera.</p>
                            <div id="qr-reader" class="sp-quick-reader rounded border"></div>
                        </form>

                        <!-- Por reconhecimento facial -->
                        <form id="form-facial" class="method-form" style="display:none;" data-method="facial">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-person-bounding-box me-1 text-primary"></i>Reconhecimento Facial
                            </label>
                            <div id="faceCameraStatus" class="alert alert-secondary mb-2" style="font-size:.82rem">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="spinner-border spinner-border-sm flex-shrink-0" role="status" aria-hidden="true"></div>
                                    <span>Iniciando câmera...</span>
                                </div>
                            </div>
                            <div class="text-center">
                                <div style="position:relative;display:inline-block;width:100%;max-width:480px">
                                    <video id="faceVideo" autoplay playsinline class="sp-quick-video rounded border mb-2 w-100"
                                           style="max-width:100%;background:#111;min-height:160px;display:block"></video>
                                    <svg style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;border-radius:.375rem">
                                        <defs><mask id="qfMask"><rect width="100%" height="100%" fill="white"/><ellipse cx="50%" cy="44%" rx="27%" ry="37%" fill="black"/></mask></defs>
                                        <rect width="100%" height="100%" fill="rgba(0,0,0,0.38)" mask="url(#qfMask)"/>
                                        <ellipse cx="50%" cy="44%" rx="27%" ry="37%" fill="none" stroke="#22c55e" stroke-width="2.5"/>
                                    </svg>
                                    <div style="position:absolute;bottom:14%;left:0;right:0;text-align:center;color:#fff;font-size:.75rem;pointer-events:none;text-shadow:0 1px 3px rgba(0,0,0,.9)">
                                        <i class="bi bi-person-bounding-box me-1"></i>Posicione seu rosto no oval
                                    </div>
                                </div>
                                <br>
                                <button type="button" class="btn btn-success px-5" id="captureFace">
                                    <i class="bi bi-camera me-1"></i>Capturar e registrar
                                </button>
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-muted">
                                    Sem biometria cadastrada? <a href="<?= site_url('employees') ?>">Acesse o perfil do colaborador</a>
                                </small>
                            </div>
                        </form>

                        <!-- Por biometria digital -->
                        <form id="form-biometria" class="method-form" style="display:none;" data-method="biometria">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-fingerprint me-1 text-primary"></i>Biometria Digital
                            </label>
                            <div id="bioMessage" class="alert alert-secondary mb-3" style="font-size:.82rem">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="spinner-border spinner-border-sm flex-shrink-0" role="status" aria-hidden="true"></div>
                                    <span>Verificando suporte biométrico do dispositivo...</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-success px-5 w-100 mb-2" id="submitBiometric" disabled>
                                <i class="bi bi-fingerprint me-1"></i>Validar e registrar
                            </button>
                            <div class="text-center">
                                <small class="text-muted">
                                    Sem digital cadastrada? <a href="<?= site_url('employees') ?>">Acesse o perfil do colaborador</a>
                                </small>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.sp-vstepper -->
</div>

<!-- Botao sair do terminal -->
<div style="position:fixed;top:.75rem;right:.75rem;z-index:1050">
    <button type="button" class="btn btn-outline-secondary btn-sm opacity-50"
            data-bs-toggle="modal" data-bs-target="#terminalExitModal"
            style="font-size:.78rem">
        <i class="bi bi-box-arrow-right me-1"></i>Sair do terminal
    </button>
</div>
<div class="modal fade" id="terminalExitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-shield-lock me-2 text-warning"></i>Acesso restrito</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Digite suas credenciais para acessar o sistema.</p>
                <form method="POST" action="<?= site_url('login') ?>">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">E-mail</label>
                        <input type="email" class="form-control form-control-sm" name="email" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Senha</label>
                        <input type="password" class="form-control form-control-sm" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Entrar no sistema
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<script <?= csp_script_nonce_attr() ?>>
(() => {
    const methodStatus   = <?= json_encode($methodReadiness, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const enabledMethods = Object.fromEntries(Object.entries(methodStatus).map(([k, m]) => [k, !!m.enabled]));
    let activeMethod = enabledMethods.codigo ? 'codigo' : (Object.keys(enabledMethods).find(k => enabledMethods[k]) || 'codigo');
    let qrScanner = null, faceStream = null, fpWs = null;

    const endpoints = {
        codigo:    '<?= sp_punch_terminal_code_url() ?>',
        cpf:       '<?= sp_punch_terminal_cpf_url() ?>',
        qrcode:    '<?= sp_timesheet_punch_qr_url() ?>',
        facial:    '<?= sp_punch_terminal_face_url() ?>',
        biometria: '<?= sp_punch_terminal_fingerprint_url() ?>',
    };

    const resultDiv      = document.getElementById('quickPunchResult');
    const punchTypeInput = document.getElementById('punch_type');
    const supportsCamera  = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    const supportsWebAuthn = !!window.PublicKeyCredential;

    /* ── Stepper visual ─────────────────────────── */
    var qsDone = {1: false, 2: false};
    function markQS(n, ok) {
        var s = document.getElementById('qs' + n), m = document.getElementById('qs' + n + 'Num');
        if (!s || !m) return;
        s.classList.remove('is-active', 'is-done');
        if (ok) { s.classList.add('is-done'); m.innerHTML = '<i class="bi bi-check-lg" style="font-size:.8rem"></i>'; }
        else     { s.classList.add('is-active'); m.textContent = String(n); }
    }

    /* ── Tipo de registro ───────────────────────── */
    document.querySelectorAll('.punch-type-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.punch-type-btn').forEach(b => {
                b.classList.remove('active', 'btn-' + b.dataset.color);
                b.classList.add('btn-outline-' + b.dataset.color);
            });
            btn.classList.remove('btn-outline-' + btn.dataset.color);
            btn.classList.add('btn-' + btn.dataset.color, 'active');
            punchTypeInput.value = btn.dataset.value;
            if (!qsDone[1]) { markQS(1, true); markQS(2, false); } qsDone[1] = true;
        });
    });

    /* ── Seletor de método ──────────────────────── */
    document.querySelectorAll('.method-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!btn.disabled) {
                switchMethod(btn.dataset.method);
                if (!qsDone[2]) { markQS(2, true); markQS(3, false); } qsDone[2] = true;
            }
        });
    });

    function switchMethod(method) {
        if (!enabledMethods[method]) return;
        activeMethod = method;
        stopHardware();
        document.querySelectorAll('.method-form').forEach(f => { f.style.display = 'none'; });
        const form = document.getElementById(`form-${method}`);
        if (form) form.style.display = 'block';
        document.querySelectorAll('.method-btn').forEach(b => {
            b.classList.toggle('btn-primary', b.dataset.method === method);
            b.classList.toggle('btn-outline-primary', b.dataset.method !== method && !b.disabled);
        });
        if (method === 'facial')    startFace();
        if (method === 'qrcode')    startQR();
        if (method === 'biometria') startFingerprint();
    }

    function startFingerprint() {
        stopFingerprint();
        var bioBox = document.getElementById('bioMessage');
        var btn = document.getElementById('submitBiometric');
        if (btn) btn.style.display = 'none';
        function setFpStatus(cls, msg, spin) {
            if (!bioBox) return;
            bioBox.className = 'alert alert-' + cls + ' mb-3 d-flex align-items-center gap-2';
            var icon = spin
                ? '<div class="spinner-border spinner-border-sm flex-shrink-0" role="status"></div>'
                : '<i class="bi bi-fingerprint fs-5 flex-shrink-0"></i>';
            bioBox.innerHTML = icon + '<span>' + msg + '</span>';
        }
        setFpStatus('secondary', 'Detectando leitor de impressao digital...', true);
        fpWs = new WebSocket('ws://localhost:8765');
        fpWs.onopen = function() {
            setFpStatus('secondary', 'Verificando hardware...', true);
        };
        fpWs.onmessage = function(ev) {
            try {
                var msg = JSON.parse(ev.data);
                if (msg.status === 'ready') {
                    setFpStatus('info', 'Apoie o dedo no leitor de impressao digital...', true);
                } else if (msg.status === 'captured' && msg.data) {
                    setFpStatus('success', 'Digital capturada! Registrando ponto...', true);
                    var ws = fpWs; fpWs = null;
                    try { ws.close(); } catch(_) {}
                    submit(endpoints.biometria, {
                        fingerprint_data: msg.data,
                        punch_type: punchTypeInput.value,
                    });
                } else if (msg.status === 'timeout') {
                    setFpStatus('warning', 'Tempo esgotado. Selecione Biometria novamente.', false);
                } else if (msg.status === 'error') {
                    setFpStatus('danger', msg.message || 'Erro no leitor de impressao digital.', false);
                }
            } catch(_) {}
        };
        fpWs.onerror = function() {
            setFpStatus('warning',
                'Servico fp_bridge nao encontrado. Verifique se esta em execucao.', false);
        };
    }
    function stopFingerprint() {
        if (fpWs) { try { fpWs.close(); } catch(_) {} fpWs = null; }
    }

    async function showResult(response, result) {
        const ok = response.ok;
        const pending = result?.data?.pending_punch || null;
        let extra = '';
        if (pending?.eligible && pending?.justify_url) {
            const url = /^(https?:\/\/|\/)/.test(String(pending.justify_url)) ? pending.justify_url : '#';
            extra = `<div class="mt-2"><a class="btn btn-sm btn-warning" href="${url}">Abrir justificativa automática</a></div>`;
        } else if (pending?.block_reason) {
            extra = `<div class="small mt-2">${pending.block_reason}</div>`;
        }
        resultDiv.innerHTML = `<div class="alert ${ok ? 'alert-success' : 'alert-danger'} d-flex align-items-start gap-2">
            <i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-exclamation-circle-fill'} fs-5 flex-shrink-0 mt-1"></i>
            <div>${result.message || (ok ? 'Registro efetuado.' : 'Falha ao registrar o ponto.')}${extra}</div>
        </div>`;
        resultDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function submit(endpoint, payload) {
        try {
            const response = await spFetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload),
            });
            const result = await response.json();
            showResult(response, result);
        } catch (_) {
            resultDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-wifi-off me-2"></i>Falha de comunicação com o servidor.</div>';
        }
    }

    document.getElementById('form-codigo').addEventListener('submit', async e => {
        e.preventDefault();
        await submit(endpoints.codigo, { unique_code: e.target.unique_code.value.trim(), punch_type: punchTypeInput.value });
    });

    const cpfEl = document.getElementById('cpf');
    if (cpfEl) {
        cpfEl.addEventListener('input', function () {
            const d = this.value.replace(/\D+/g, '').slice(0, 11);
            this.value = d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        });
    }
    document.getElementById('form-cpf').addEventListener('submit', async e => {
        e.preventDefault();
        await submit(endpoints.cpf, { cpf: (cpfEl?.value || '').trim(), punch_type: punchTypeInput.value });
    });

    async function startFace() {
        const statusBox = document.getElementById('faceCameraStatus');
        if (!supportsCamera) {
            if (statusBox) { statusBox.className = 'alert alert-warning mb-2'; statusBox.innerHTML = '<i class="bi bi-camera-video-off me-2"></i>Câmera indisponível neste dispositivo.'; }
            return;
        }
        try {
            faceStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
            document.getElementById('faceVideo').srcObject = faceStream;
            if (statusBox) { statusBox.className = 'alert alert-success mb-2'; statusBox.innerHTML = '<i class="bi bi-camera-video-fill me-2"></i>Câmera ativa. Posicione o rosto e clique em capturar.'; }
        } catch (e) {
            if (statusBox) { statusBox.className = 'alert alert-danger mb-2'; statusBox.innerHTML = '<i class="bi bi-x-circle-fill me-2"></i>Não foi possível acessar a câmera: ' + (e.message || 'permissão negada') + '.'; }
        }
    }

    document.getElementById('captureFace').addEventListener('click', async () => {
        const video = document.getElementById('faceVideo');
        if (!faceStream || !(video.videoWidth > 0)) {
            resultDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-camera-video-off me-2"></i>Câmera ainda não está pronta.</div>';
            return;
        }
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 640; canvas.height = video.videoHeight || 480;
        canvas.getContext('2d').drawImage(video, 0, 0);
        await submit(endpoints.facial, { photo: canvas.toDataURL('image/jpeg').split(',')[1], punch_type: punchTypeInput.value });
    });

    async function startQR() {
        if (!supportsCamera || typeof Html5Qrcode === 'undefined') {
            resultDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-qr-code me-2"></i>Leitor QR Code indisponível.</div>';
            return;
        }
        try {
            qrScanner = new Html5Qrcode('qr-reader');
            await qrScanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 250 }, async text => {
                await submit(endpoints.qrcode, { token: text, punch_type: punchTypeInput.value });
                try { await qrScanner.stop(); } catch(_) {}
            });
        } catch (_) {
            resultDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-camera-video-off me-2"></i>Não foi possível iniciar o leitor de QR Code.</div>';
        }
    }

    // submitBiometric: auto via WebSocket fp_bridge (sem click necessario)

    async function stopHardware() {
        stopFingerprint();
        if (qrScanner) { try { await qrScanner.stop(); } catch(_) {} qrScanner = null; }
        if (faceStream) { faceStream.getTracks().forEach(t => t.stop()); faceStream = null; }
    }

    switchMethod(activeMethod);
})();
</script>
<?= $this->endSection() ?>
