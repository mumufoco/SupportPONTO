<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h3 class="mb-1">
                        <i class="fas fa-user-check me-2"></i>
                        Terminal de Ponto &mdash; Reconhecimento Facial
                    </h3>
                    <p class="mb-0 opacity-75">Posicione seu rosto na c&#226;mera para registrar o ponto</p>
                    <small class="opacity-60"><em>Posizionare il viso davanti alla fotocamera per registrare la presenza</em></small>
                </div>

                <div class="card-body p-4">

                    <!-- Estado inicial: solicitar permissao da camera -->
                    <div id="permission-request" class="text-center py-4">
                        <i class="fas fa-video fa-4x text-muted mb-3"></i>
                        <h5>Permiss&#227;o de C&#226;mera Necess&#225;ria</h5>
                        <p class="text-muted">Para registrar ponto por reconhecimento facial, precisamos acessar sua c&#226;mera.</p>
                        <div class="alert alert-light border mb-3 text-start">
                            <i class="bi bi-translate text-primary me-1"></i>
                            <small><em>Per registrare la presenza tramite riconoscimento facciale, è necessario consentire l'accesso alla fotocamera. Premere il pulsante qui sotto.</em></small>
                        </div>
                        <button id="btn-start" class="btn btn-primary btn-lg px-5">
                            <i class="fas fa-camera me-2"></i>Permitir Acesso &#224; C&#226;mera
                            <small class="d-block" style="font-size:.8em;opacity:.85;"><em>Consenti accesso alla fotocamera</em></small>
                        </button>
                    </div>

                    <!-- Scanner ativo -->
                    <div id="scanner-container" class="d-none text-center">
                        <div class="alert alert-info py-2 mb-3">
                            <i class="bi bi-translate me-1"></i>
                            <small><em>Guardare direttamente nella fotocamera e attendere il riconoscimento automatico.</em></small>
                        </div>
                        <div class="position-relative d-inline-block mb-3">
                            <video id="video" width="400" height="300" autoplay muted playsinline
                                   class="border rounded" style="max-width:100%;"></video>
                            <canvas id="overlay" width="400" height="300"
                                    class="position-absolute top-0 start-0" style="pointer-events:none;"></canvas>
                            <!-- Face guide overlay -->
                            <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:180px;height:220px;border:3px dashed rgba(255,255,255,.6);border-radius:50%;pointer-events:none;"></div>
                        </div>

                        <div class="mt-2 mb-3">
                            <p class="text-muted mb-1" id="status-text">
                                <i class="fas fa-search me-2"></i>Procurando rosto...
                                <small class="d-block text-muted"><em>Ricerca del viso in corso...</em></small>
                            </p>
                        </div>

                        <div class="d-grid gap-2 mt-2">
                            <button id="btn-capture" class="btn btn-success btn-lg">
                                <i class="fas fa-fingerprint me-2"></i>Registrar Ponto
                                <small class="d-block" style="font-size:.8em;opacity:.85;"><em>Registra la presenza ora</em></small>
                            </button>
                            <button id="btn-stop" class="btn btn-outline-secondary">
                                <i class="fas fa-stop me-2"></i>Cancelar
                            </button>
                        </div>

                        <!-- Sem biometria cadastrada? -->
                        <div class="alert alert-warning mt-3 text-start py-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <small>Caso n&#227;o tenha biometria cadastrada, <a href="<?= site_url('biometric') ?>" class="fw-bold">clique aqui para cadastrar</a>.</small><br>
                            <small class="text-muted"><em>Se non hai ancora registrato la biometria, clicca qui per registrarti.</em></small>
                        </div>
                    </div>

                    <!-- Processando -->
                    <div id="processing-container" class="d-none text-center py-4">
                        <div class="spinner-border text-primary sp-spinner-xl" role="status">
                            <span class="visually-hidden">Processando...</span>
                        </div>
                        <h5 class="mt-4">Reconhecendo...</h5>
                        <p class="text-muted mb-1">Aguarde enquanto verificamos sua identidade</p>
                        <small class="text-muted"><em>Attendere mentre il sistema analizza la biometria.</em></small>
                    </div>

                    <!-- Resultado -->
                    <div id="result-container" class="d-none">
                        <div id="result-success" class="d-none text-center">
                            <div class="alert alert-success py-4">
                                <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                                <h4 id="success-name" class="mb-2"></h4>
                                <p class="lead mb-1" id="success-punch-type"></p>
                                <p class="mb-0" id="success-time"></p>
                                <small class="text-muted" id="success-similarity"></small>
                                <hr class="my-2">
                                <small class="text-muted"><em>Presenza registrata con successo!</em></small>
                            </div>
                        </div>

                        <div id="result-error" class="d-none text-center">
                            <div class="alert alert-danger py-4">
                                <i class="fas fa-times-circle fa-4x mb-3 text-danger"></i>
                                <h4>Falha no Reconhecimento</h4>
                                <p id="error-message" class="mb-1"></p>
                                <small class="text-muted"><em>Riconoscimento non riuscito. Riprovare o contattare o RH.</em></small>
                            </div>
                        </div>

                        <div class="text-center mt-3">
                            <button id="btn-retry" class="btn btn-primary btn-lg">
                                <i class="fas fa-redo me-2"></i>Tentar Novamente
                                <small class="d-block" style="font-size:.8em;opacity:.85;"><em>Riprovare</em></small>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <div class="row text-center">
                        <div class="col-4">
                            <i class="fas fa-shield-alt text-success"></i>
                            <small class="d-block text-muted">Seguro</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-clock text-primary"></i>
                            <small class="d-block text-muted" id="current-time">--:--:--</small>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-map-marker-alt text-warning"></i>
                            <small class="d-block text-muted" id="location-status">GPS...</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-body text-center">
                    <p class="text-muted mb-2">Outros m&#233;todos de registro:</p>
                    <a href="<?= site_url('qrcode/scanner') ?>" class="btn btn-outline-primary me-2">
                        <i class="fas fa-qrcode me-1"></i> QR Code
                    </a>
                    <a href="<?= site_url('timesheet/punch') ?>" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-keyboard me-1"></i> C&#243;digo
                    </a>
                    <a href="<?= site_url('biometric') ?>" class="btn btn-outline-info">
                        <i class="fas fa-fingerprint me-1"></i> Cadastrar Biometria
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('video');
    let stream = null;
    let geoLocation = { latitude: null, longitude: null };

    // Relógio
    function updateClock() {
        document.getElementById('current-time').textContent = new Date().toLocaleTimeString('pt-BR');
    }
    setInterval(updateClock, 1000);
    updateClock();

    // GPS
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => {
                geoLocation.latitude  = pos.coords.latitude;
                geoLocation.longitude = pos.coords.longitude;
                document.getElementById('location-status').textContent = 'GPS OK';
            },
            () => { document.getElementById('location-status').textContent = 'GPS indisponível'; }
        );
    }

    function showView(viewId) {
        ['permission-request', 'scanner-container', 'processing-container', 'result-container'].forEach(id => {
            document.getElementById(id)?.classList.add('d-none');
        });
        document.getElementById(viewId)?.classList.remove('d-none');
    }

    async function startCamera() {
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: 400, height: 300 } });
            video.srcObject = stream;
            document.getElementById('status-text').innerHTML = '<i class="fas fa-circle text-danger me-2 fa-pulse"></i>C&#226;mera ativa. Posicione o rosto.<small class="d-block text-muted"><em>Fotocamera attiva. Posizionare il viso nel cerchio.</em></small>';
            showView('scanner-container');
        } catch (err) {
            alert('Erro ao acessar câmera: ' + err.message + '\n(Errore fotocamera: verificare i permessi del browser.)');
        }
    }

    function stopCamera() {
        if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    }

    let kioskToken = null;

    async function getKioskToken() {
        try {
            const r = await spFetch('/kiosk/token');
            const d = await r.json();
            if (d.success) kioskToken = d.data.kiosk_token;
        } catch (e) { console.error('Kiosk token error:', e); }
    }
    getKioskToken();
    setInterval(getKioskToken, 55 * 60 * 1000);

    async function captureAndRecognize() {
        showView('processing-container');
        if (!kioskToken) await getKioskToken();

        const canvas = document.createElement('canvas');
        canvas.width = 400; canvas.height = 300;
        canvas.getContext('2d').drawImage(video, 0, 0, 400, 300);
        const photo = canvas.toDataURL('image/jpeg', 0.9);
        stopCamera();

        try {
            const response = await spFetch('/punch-terminal/face', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-Kiosk-Token': kioskToken || ''
                },
                body: 'photo=' + encodeURIComponent(photo)
                    + '&latitude=' + (geoLocation.latitude || '')
                    + '&longitude=' + (geoLocation.longitude || '')
                    + '&kiosk_token=' + encodeURIComponent(kioskToken || '')
            });

            const result = await response.json();
            showView('result-container');

            if (result.success) {
                document.getElementById('result-success').classList.remove('d-none');
                document.getElementById('result-error').classList.add('d-none');
                document.getElementById('success-name').textContent = result.data?.employee_name || 'Colaborador';
                document.getElementById('success-punch-type').textContent = result.data?.punch_type_label || 'Ponto registrado';
                document.getElementById('success-time').textContent = result.data?.punch_time || new Date().toLocaleString('pt-BR');
                document.getElementById('success-similarity').textContent =
                    result.data?.similarity ? 'Similaridade: ' + Math.round(result.data.similarity * 100) + '%' : '';
            } else {
                document.getElementById('result-success').classList.add('d-none');
                document.getElementById('result-error').classList.remove('d-none');
                document.getElementById('error-message').textContent =
                    result.message || result.error || 'Erro no reconhecimento';
            }
        } catch (error) {
            showView('result-container');
            document.getElementById('result-success').classList.add('d-none');
            document.getElementById('result-error').classList.remove('d-none');
            document.getElementById('error-message').textContent = 'Erro de comunicação com o servidor';
        }
    }

    document.getElementById('btn-start')?.addEventListener('click', startCamera);
    document.getElementById('btn-capture')?.addEventListener('click', captureAndRecognize);
    document.getElementById('btn-stop')?.addEventListener('click', () => { stopCamera(); showView('permission-request'); });
    document.getElementById('btn-retry')?.addEventListener('click', startCamera);

    window.addEventListener('beforeunload', stopCamera);
});
</script>
<?= $this->endSection() ?>
