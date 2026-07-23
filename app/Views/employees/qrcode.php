<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>QR Code - <?= esc($employee->name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h2 class="mb-1 fw-semibold h4 text-dark">
                    <i class="fas fa-qrcode me-2"></i>QR Code
                </h2>
                <p class="text-muted mb-0 sp-text-sm-muted">
                    Colaborador: <?= esc($employee->name) ?>
                </p>
            </div>
            <div>
                <a href="<?= site_url('employees/' . $employee->id) ?>" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Voltar
                </a>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <!-- QR Code Card -->
            <div class="card text-center">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Code do Colaborador</h5>
                </div>
                <div class="card-body">
                    <!-- QR Code Image from API -->
                    <div class="mb-4">
                        <img id="qrcode-full-image" 
                             src="https://api.qrserver.com/v1/create-qr-code/?size=256x256&data=<?= urlencode($employee->unique_code) ?>&format=png&color=000000&bgcolor=FFFFFF&margin=4&ecc=M"
                             alt="QR Code"
                             class="img-fluid rounded shadow"
                             onload="showQRCodeSuccess()"
                             onerror="showQRCodeError()">
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="text-muted">Código Único</h6>
                        <code class="fs-4 bg-light p-3 rounded d-block" id="unique-code-display"><?= esc($employee->unique_code) ?></code>
                    </div>

                    <div class="mb-4">
                        <h6 class="text-muted">Colaborador</h6>
                        <p class="mb-0 fw-bold"><?= esc($employee->name) ?></p>
                        <p class="text-muted small mb-0">CPF: <?= function_exists('format_cpf') ? format_cpf($employee->cpf ?? '') : esc($employee->cpf) ?></p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Instruções:</strong> Este QR Code contém o código único do colaborador. 
                        Use-o para registro de ponto rápido via aplicativo móvel ou terminal.
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                        <a href="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($employee->unique_code) ?>&format=png&color=000000&bgcolor=FFFFFF&margin=2&ecc=M&download=1"
                           class="btn btn-primary"
                           download="qrcode_<?= esc($employee->unique_code) ?>.png"
                           target="_blank">
                            <i class="fas fa-download me-2"></i>Download PNG
                        </a>
                        <button type="button" class="btn btn-outline-secondary" id="print-full-btn">
                            <i class="fas fa-print me-2"></i>Imprimir
                        </button>
                        <button type="button" class="btn btn-outline-info" onclick="copyToClipboard()">
                            <i class="fas fa-copy me-2"></i>Copiar Código
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
// QR Code functions
function showQRCodeSuccess() {
    console.log('QR Code carregado com sucesso');
    document.getElementById('print-full-btn').disabled = false;
}

function showQRCodeError() {
    console.error('Erro ao carregar QR Code');
    const container = document.querySelector('#qrcode-full-image').parentElement;
    container.innerHTML = '<div class="text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Erro ao carregar QR Code<br><small>Tente recarregar a página</small></div>';
}

// Print functionality
document.addEventListener('DOMContentLoaded', function() {
    const printBtn = document.getElementById('print-full-btn');
    
    printBtn.addEventListener('click', function() {
        // Open print-optimized image in new window
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Imprimir QR Code</title>
                <style>
                    body { text-align: center; font-family: Arial, sans-serif; margin: 20px; }
                    img { max-width: 100%; height: auto; }
                    .info { margin-top: 20px; }
                </style>
            </head>
            <body>
                <h2>QR Code do Colaborador</h2>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=<?= urlencode($employee->unique_code) ?>&format=png&color=000000&bgcolor=FFFFFF&margin=4&ecc=H" alt="QR Code">
                <div class="info">
                    <h3><?= str_replace('`', '&#96;', esc($employee->name)) ?></h3>
                    <p><strong>Código Único:</strong> <?= str_replace('`', '&#96;', esc($employee->unique_code)) ?></p>
                    <p><strong>CPF:</strong> <?= function_exists('format_cpf') ? format_cpf($employee->cpf ?? '') : esc($employee->cpf) ?></p>
                    <p><em>Gerado em: <?= date('d/m/Y H:i') ?></em></p>
                </div>
                <button onclick="window.print()">Imprimir</button>
            </body>
            </html>
        `);
        printWindow.document.close();
    });
});

// Copy unique code to clipboard
function copyToClipboard() {
    const code = <?= json_encode($employee->unique_code) ?>;
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(code).then(function() {
            alert('Código copiado para a área de transferência!');
        }).catch(function(err) {
            console.error('Erro ao copiar: ', err);
            fallbackCopyTextToClipboard(code);
        });
    } else {
        fallbackCopyTextToClipboard(code);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.top = '0';
    textArea.style.left = '0';
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            alert('Código copiado para a área de transferência!');
        } else {
            alert('Erro ao copiar código');
        }
    } catch (err) {
        console.error('Erro ao copiar: ', err);
        alert('Erro ao copiar código');
    }
    
    document.body.removeChild(textArea);
}
</script>
<?= $this->endSection() ?>
