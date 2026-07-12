<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h3 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i>
                        Terminal de Ponto - QR Code
                    </h3>
                    <p class="mb-0 mt-2 opacity-75">Posicione o QR Code do colaborador na câmera</p>
                </div>
                <div class="card-body p-4">
                    <div id="permission-request" class="text-center py-5">
                        <i class="fas fa-video fa-4x text-muted mb-4"></i>
                        <h5>Permissão de Câmera Necessária</h5>
                        <p class="text-muted">Para escanear QR Codes, precisamos acessar sua câmera.</p>
                        <button id="btn-start-scanner" class="btn btn-primary btn-lg">
                            <i class="fas fa-camera me-2"></i>
                            Permitir Acesso à Câmera
                        </button>
                    </div>

                    <div id="scanner-container" class="d-none">
                        <div class="text-center mb-3">
                            <video id="qr-video" width="400" height="300" autoplay muted playsinline class="border rounded"></video>
                            <canvas id="qr-canvas" width="400" height="300" class="d-none"></canvas>
                        </div>
                        
                        <div class="text-center mt-3">
                            <button id="btn-toggle-camera" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sync-alt me-1"></i>
                                Trocar Câmera
                            </button>
                            <button id="btn-stop-scanner" class="btn btn-outline-danger btn-sm ms-2">
                                <i class="fas fa-stop me-1"></i>
                                Parar Scanner
                            </button>
                        </div>
                    </div>

                    <div id="error-container" class="d-none">
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <h5 id="error-title">Erro</h5>
                            <p id="error-message" class="mb-3"></p>
                            <button id="btn-retry" class="btn btn-danger">
                                <i class="fas fa-redo me-1"></i>
                                Tentar Novamente
                            </button>
                        </div>
                    </div>

                    <div id="result-container" class="d-none">
                        <div id="result-success" class="d-none">
                            <div class="alert alert-success text-center">
                                <i class="fas fa-check-circle fa-4x mb-3 text-success"></i>
                                <h4 id="success-employee-name"></h4>
                                <p class="lead mb-2" id="success-punch-type"></p>
                                <p class="mb-0" id="success-punch-time"></p>
                            </div>
                        </div>
                        
                        <div id="result-error" class="d-none">
                            <div class="alert alert-danger text-center">
                                <i class="fas fa-times-circle fa-4x mb-3 text-danger"></i>
                                <h4>Falha no Registro</h4>
                                <p id="result-error-message" class="mb-0"></p>
                            </div>
                        </div>
                        
                        <div class="text-center mt-3">
                            <button id="btn-scan-again" class="btn btn-primary btn-lg">
                                <i class="fas fa-qrcode me-2"></i>
                                Escanear Novo QR Code
                            </button>
                        </div>
                    </div>

                    <div id="processing-container" class="d-none text-center py-5">
                        <div class="spinner-border text-primary sp-spinner-lg" role="status">
                            <span class="visually-hidden">Processando...</span>
                        </div>
                        <h5 class="mt-3">Validando QR Code...</h5>
                        <p class="text-muted">Aguarde enquanto verificamos os dados</p>
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
                            <small class="d-block text-muted" id="location-status">Verificando...</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluindo bibliotecas -->
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/camera-manager.js')) ?>"></script>

<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function() {
    const cameraManager = CameraManager.getInstance();
    const qrVideo = document.getElementById('qr-video');
    const qrCanvas = document.getElementById('qr-canvas');
    const qrCtx = qrCanvas.getContext('2d');
    
    const permissionRequest = document.getElementById('permission-request');
    const scannerContainer = document.getElementById('scanner-container');
    const errorContainer = document.getElementById('error-container');
    const resultContainer = document.getElementById('result-container');
    const processingContainer = document.getElementById('processing-container');
    
    let qrScanner = null;
    let currentLocation = { latitude: null, longitude: null };
    let isScanning = false;

    function updateClock() {
        const now = new Date();
        document.getElementById('current-time').textContent = now.toLocaleTimeString('pt-BR');
    }
    setInterval(updateClock, 1000);
    updateClock();

    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentLocation.latitude = position.coords.latitude;
                    currentLocation.longitude = position.coords.longitude;
                    document.getElementById('location-status').textContent = 'GPS OK';
                    document.getElementById('location-status').parentElement.querySelector('i').classList.remove('text-warning');
                    document.getElementById('location-status').parentElement.querySelector('i').classList.add('text-success');
                },
                function(error) {
                    document.getElementById('location-status').textContent = 'GPS indisponível';
                    document.getElementById('location-status').parentElement.querySelector('i').classList.remove('text-warning');
                    document.getElementById('location-status').parentElement.querySelector('i').classList.add('text-danger');
                }
            );
        }
    }
    getLocation();

    function showView(view) {
        permissionRequest.classList.add('d-none');
        scannerContainer.classList.add('d-none');
        errorContainer.classList.add('d-none');
        resultContainer.classList.add('d-none');
        processingContainer.classList.add('d-none');
        
        view.classList.remove('d-none');
    }

    function showError(title, message) {
        document.getElementById('error-title').textContent = title;
        document.getElementById('error-message').textContent = message;
        showView(errorContainer);
    }

    // Classe QRScanner integrada com CameraManager
    class QRScanner {
        constructor(videoElement, canvasElement) {
            this.video = videoElement;
            this.canvas = canvasElement;
            this.ctx = canvasElement.getContext('2d');
            this.isScanning = false;
            this.callback = null;
        }

        async start(callback) {
            if (cameraManager.isActive) {
                cameraManager.stop();
            }

            try {
                await cameraManager.start(this.video, {
                    video: { facingMode: 'environment' } // Preferir câmera traseira
                });
                this.callback = callback;
                this.isScanning = true;
                this.scan();
                console.log('QR Scanner started');
            } catch (error) {
                throw error;
            }
        }

        scan() {
            if (!this.isScanning) return;

            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
            this.ctx.drawImage(this.video, 0, 0);

            const imageData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height);
            const code = jsQR(imageData.data, this.canvas.width, this.canvas.height);

            if (code) {
                this.stop();
                if (this.callback) {
                    this.callback(code.data);
                }
            } else {
                requestAnimationFrame(() => this.scan());
            }
        }

        stop() {
            this.isScanning = false;
            cameraManager.stop();
        }
    }

    async function startScanner() {
        try {
            qrScanner = new QRScanner(qrVideo, qrCanvas);
            await qrScanner.start(onScanSuccess);
            showView(scannerContainer);
            console.log('QR Scanner initialized successfully');
        } catch (err) {
            console.error('Scanner error:', err);
            
            let title = 'Erro ao iniciar câmera';
            let message = 'Erro desconhecido';
            
            if (err.message.includes('Camera already in use')) {
                title = 'Câmera em uso';
                message = 'A câmera já está sendo usada por outro módulo. Feche outras abas ou recarregue a página.';
            } else if (err.message.includes('HTTPS required')) {
                title = 'HTTPS Necessário';
                message = 'O acesso à câmera requer conexão segura. Certifique-se de estar em HTTPS.';
            } else if (err.message.includes('permission denied')) {
                title = 'Permissão Negada';
                message = 'Permita o acesso à câmera nas configurações do navegador.';
            } else if (err.message.includes('not supported')) {
                title = 'Navegador Incompatível';
                message = 'Seu navegador não suporta acesso à câmera. Use Chrome, Firefox ou Safari.';
            } else {
                message = err.message;
            }
            
            showError(title, message);
        }
    }

    async function onScanSuccess(decodedText) {
        showView(processingContainer);
        
        try {
            const response = await spFetch('/qrcode/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    token: decodedText,
                    latitude: currentLocation.latitude,
                    longitude: currentLocation.longitude
                })
            });

            const result = await response.json();
            
            showView(resultContainer);
            
            if (result.success) {
                document.getElementById('result-success').classList.remove('d-none');
                document.getElementById('result-error').classList.add('d-none');
                document.getElementById('success-employee-name').textContent = result.data.employee_name;
                document.getElementById('success-punch-type').textContent = result.data.punch_type_label;
                document.getElementById('success-punch-time').textContent = result.data.punch_time;
                
                playSuccessSound();
            } else {
                document.getElementById('result-success').classList.add('d-none');
                document.getElementById('result-error').classList.remove('d-none');
                document.getElementById('result-error-message').textContent = result.error;
                
                playErrorSound();
            }

        } catch (error) {
            console.error('Validation error:', error);
            showView(resultContainer);
            document.getElementById('result-success').classList.add('d-none');
            document.getElementById('result-error').classList.remove('d-none');
            document.getElementById('result-error-message').textContent = 'Erro de comunicação com o servidor';
            playErrorSound();
        }
    }

    function playSuccessSound() {
        try {
            const audio = new Audio('data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAABhgC7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7u7//////////////////////////////////////////////////////////////////8AAAAATGF2YzU4LjEzAAAAAAAAAAAAAAAAJAAAAAAAAAAAAYbD+kBLAAAAAAAAAAAAAAAAAAAA//tQZAAP8AAAaQAAAAgAAA0gAAABAAABpAAAACAAADSAAAAETEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/7UGQAD/AAAGkAAAAgAAA0gAAABAAABpAAAACAAADSAAAAETEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV');
            audio.play().catch(() => {});
        } catch (e) {}
    }

    function playErrorSound() {
        try {
            const audio = new Audio('data:audio/mp3;base64,SUQzBAAAAAAAI1RTU0UAAAAPAAADTGF2ZjU4Ljc2LjEwMAAAAAAAAAAAAAAA//tQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAWGluZwAAAA8AAAACAAABhgDMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzMzM//////////////////////////////////////////////////////////////////8AAAAATGF2YzU4LjEzAAAAAAAAAAAAAAAAJAAAAAAAAAAAAYZAAAAAAAAAAAAAAAAAAAAA//tQZAAP8AAAaQAAAAgAAA0gAAABAAABpAAAACAAADSAAAAETEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVf/7UGQAD/AAAGkAAAAgAAA0gAAABAAABpAAAACAAADSAAAAETEFNRTMuMTAwVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVVV');
            audio.play().catch(() => {});
        } catch (e) {}
    }

    document.getElementById('btn-start-scanner').addEventListener('click', startScanner);
    
    document.getElementById('btn-stop-scanner').addEventListener('click', function() {
        if (qrScanner) {
            qrScanner.stop();
        }
        showView(permissionRequest);
    });

    document.getElementById('btn-toggle-camera').addEventListener('click', async function() {
        if (qrScanner) {
            qrScanner.stop();
        }
        
        // Alternar entre front/back (simplificado)
        const facingMode = qrVideo.style.transform ? 'user' : 'environment';
        qrVideo.style.transform = facingMode === 'user' ? 'scaleX(-1)' : '';
        
        try {
            await startScanner();
        } catch (error) {
            showError('Erro ao trocar câmera', error.message);
        }
    });

    document.getElementById('btn-retry').addEventListener('click', function() {
        showView(permissionRequest);
    });

    document.getElementById('btn-scan-again').addEventListener('click', function() {
        startScanner();
    });

    // Cleanup
    window.addEventListener('beforeunload', () => {
        cameraManager.stop();
        if (qrScanner) {
            qrScanner.stop();
        }
    });
});
</script>
<?= $this->endSection() ?>
