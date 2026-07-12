<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-xl-5">
            <div class="card shadow-lg">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h3 class="mb-0">
                        <i class="fas fa-qrcode me-2"></i>
                        Meu QR Code
                    </h3>
                    <p class="mb-0 mt-2 opacity-75">Use para registrar seu ponto</p>
                </div>
                
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <h5 class="text-muted mb-1">Colaborador</h5>
                        <h4 class="text-primary"><?= esc($employee->name) ?></h4>
                        <?php if (!empty($employee->employee_code)): ?>
                        <small class="text-muted">Código: <?= esc($employee->employee_code) ?></small>
                        <?php endif; ?>
                    </div>

                    <div id="qrcode-container" class="mb-4">
                        <img id="qrcode-image" src="<?= sp_safe_url($qr_image) ?>" alt="QR Code" 
                             class="img-fluid border rounded shadow-sm sp-media-max-w-300">
                    </div>

                    <div class="alert alert-info mb-4">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="fas fa-clock me-2"></i>
                            <span>
                                Expira em: <strong id="countdown"><?= (int) $expiration_seconds ?></strong> segundos
                            </span>
                        </div>
                        <div class="progress mt-2 sp-progress-thin">
                            <div id="countdown-bar" class="progress-bar bg-info sp-progress-full" role="progressbar"></div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button id="btn-regenerate" class="btn btn-primary btn-lg">
                            <i class="fas fa-sync-alt me-2"></i>
                            Gerar Novo QR Code
                        </button>
                        <a href="<?= sp_route_url('qrcode.download') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-download me-2"></i>
                            Baixar QR Code
                        </a>
                    </div>
                </div>

                <div class="card-footer bg-light">
                    <div class="row text-center small">
                        <div class="col-6">
                            <i class="fas fa-shield-alt text-success me-1"></i>
                            Token único e seguro
                        </div>
                        <div class="col-6">
                            <i class="fas fa-ban text-warning me-1"></i>
                            Uso único
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        Como usar
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">Abra o <strong>Terminal de Ponto</strong> na empresa</li>
                        <li class="mb-2">Posicione seu QR Code em frente à câmera</li>
                        <li class="mb-2">Aguarde a confirmação do registro</li>
                        <li class="mb-0">Após o uso, gere um novo QR Code para o próximo registro</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function() {
    const expirationSeconds = <?= (int) $expiration_seconds ?>;
    let remainingSeconds = expirationSeconds;
    let countdownInterval;

    function updateCountdown() {
        remainingSeconds--;
        
        if (remainingSeconds <= 0) {
            clearInterval(countdownInterval);
            document.getElementById('countdown').textContent = 'Expirado';
            document.getElementById('countdown-bar').style.width = '0%';
            document.getElementById('countdown-bar').classList.remove('bg-info');
            document.getElementById('countdown-bar').classList.add('bg-danger');
            document.querySelector('.alert-info').classList.remove('alert-info');
            document.querySelector('.alert-info, .alert-danger').classList.add('alert-danger');
            regenerateQRCode();
        } else {
            document.getElementById('countdown').textContent = remainingSeconds;
            const percentage = (remainingSeconds / expirationSeconds) * 100;
            document.getElementById('countdown-bar').style.width = percentage + '%';
            
            if (remainingSeconds <= 60) {
                document.getElementById('countdown-bar').classList.remove('bg-info');
                document.getElementById('countdown-bar').classList.add('bg-warning');
            }
            if (remainingSeconds <= 30) {
                document.getElementById('countdown-bar').classList.remove('bg-warning');
                document.getElementById('countdown-bar').classList.add('bg-danger');
            }
        }
    }

    countdownInterval = setInterval(updateCountdown, 1000);

    async function regenerateQRCode() {
        const btn = document.getElementById('btn-regenerate');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando...';

        try {
            const response = await spFetch('/qrcode/regenerate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const result = await response.json();

            if (result.success) {
                document.getElementById('qrcode-image').src = result.qr_image;
                remainingSeconds = result.expiration_seconds;
                
                clearInterval(countdownInterval);
                countdownInterval = setInterval(updateCountdown, 1000);
                
                document.getElementById('countdown').textContent = remainingSeconds;
                document.getElementById('countdown-bar').style.width = '100%';
                document.getElementById('countdown-bar').classList.remove('bg-warning', 'bg-danger');
                document.getElementById('countdown-bar').classList.add('bg-info');
                
                const alertEl = document.querySelector('.alert-danger');
                if (alertEl) {
                    alertEl.classList.remove('alert-danger');
                    alertEl.classList.add('alert-info');
                }
            } else {
                alert('Erro ao gerar QR Code: ' + result.error);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Erro de comunicação com o servidor');
        }

        btn.disabled = false;
        btn.innerHTML = originalText;
    }

    document.getElementById('btn-regenerate').addEventListener('click', regenerateQRCode);
});
</script>
<?= $this->endSection() ?>
