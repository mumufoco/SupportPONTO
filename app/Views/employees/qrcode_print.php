<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - <?= esc($employee->name) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }
        .qr-container {
            margin: 20px auto;
            display: inline-block;
            padding: 20px;
            border: 2px solid #000;
            background: white;
        }
        .qr-placeholder {
            width: 300px;
            height: 300px;
            border: 1px dashed #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-size: 14px;
        }
        .info {
            margin-top: 20px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
            .qr-placeholder { border: none; }
        }
    </style>
</head>
<body>
    <h2>QR Code do Colaborador</h2>
    
    <div class="qr-container">
        <div class="qr-placeholder">
            <div>
                <strong>QR Code</strong><br>
                Código: <?= esc($employee->unique_code) ?><br>
                <small>Use um leitor de QR Code para escanear</small>
            </div>
        </div>
        
        <!-- Fallback image if JS fails -->
        <img id="qr-fallback" 
             src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($employee->unique_code) ?>&format=png"
             alt="QR Code"
             class="sp-qr-fallback"
             onload="document.querySelector('.qr-placeholder').style.display='none'; this.style.display='block';">
    </div>
    
    <div class="info">
        <h3><?= esc($employee->name) ?></h3>
        <p><strong>Código Único:</strong> <?= esc($employee->unique_code) ?></p>
        <p><strong>CPF:</strong> <?= function_exists('format_cpf') ? format_cpf($employee->cpf ?? '') : esc($employee->cpf) ?></p>
        <p><em>Gerado em: <?= date('d/m/Y H:i') ?></em></p>
    </div>
    
    <button class="no-print" onclick="window.print()">Imprimir</button>

    <script <?= csp_script_nonce_attr() ?>>
        // Try to load QRCode library and generate
        (function() {
            const placeholder = document.querySelector('.qr-placeholder');
            const fallbackImg = document.getElementById('qr-fallback');
            
            function generateQR() {
                if (typeof QRCode === 'undefined') {
                    // Load library
                    const script = document.createElement('script');
                    script.src = '<?= asset_url('assets/js/qrcode.min.js') ?>';
                    script.onload = function() {
                        createQR();
                    };
                    script.onerror = function() {
                        fallbackImg.style.display = 'block';
                        placeholder.style.display = 'none';
                    };
                    document.head.appendChild(script);
                } else {
                    createQR();
                }
            }
            
            function createQR() {
                const uniqueCode = <?= json_encode($employee->unique_code) ?>;
                QRCode.toCanvas(uniqueCode, {
                    width: 300,
                    height: 300,
                    color: { dark: '#000000', light: '#FFFFFF' },
                    errorCorrectionLevel: 'H'
                }, function (error, canvas) {
                    if (error) {
                        fallbackImg.style.display = 'block';
                        placeholder.style.display = 'none';
                        return;
                    }
                    placeholder.parentNode.replaceChild(canvas, placeholder);
                });
            }
            
            generateQR();
        })();
    </script>
</body>
</html>
