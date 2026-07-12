<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Erro Interno do Servidor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background:#f4f6f8; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; padding:1rem; }
        .error-container { text-align:center; color:#8a93a0; max-width:600px; }
        .error-card { background:#ffffff; border:1px solid #e4e8ed; border-radius:1rem; padding:3rem 2rem; box-shadow:0 12px 32px rgba(16,24,40,.10); }
        .error-icon { width:96px; height:96px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:2.75rem; margin-bottom:1.5rem; background:rgba(229,72,77,.12); color:#e5484d; animation:pulse 2s ease-in-out infinite; }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.05);opacity:.85} }
        .error-code { font-size:7rem; font-weight:800; line-height:1; color:#18202b; margin-bottom:1rem; letter-spacing:-0.05em; }
        .error-message { font-size:1.6rem; font-weight:700; margin-bottom:0.75rem; color:#18202b; }
        .error-description { font-size:1rem; color:#5a6472; margin-bottom:2rem; line-height:1.6; }
        .btn-home { background:#17834a; color:#fff; padding:0.8rem 1.75rem; border-radius:0.7rem; font-weight:600; text-decoration:none; display:inline-block; transition:background .15s; border:none; }
        .btn-home:hover { background:#1f9d57; color:#fff; }
        .btn-retry { background:#fff; color:#5a6472; padding:0.8rem 1.75rem; border-radius:0.7rem; font-weight:600; text-decoration:none; display:inline-block; transition:background .15s; border:1px solid #e4e8ed; margin-left:1rem; }
        .btn-retry:hover { background:#f4f6f8; color:#18202b; }
        .reference-code { margin-top:2rem; padding:0.75rem; background:#f4f6f8; border:1px solid #e4e8ed; border-radius:0.5rem; font-size:0.75rem; color:#8a93a0; font-family:'SFMono-Regular',ui-monospace,Menlo,Consolas,monospace; }
        .footer-note { margin-top:2.5rem; color:#8a93a0; font-size:0.875rem; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="error-code">500</div>
            <div class="error-message">Erro Interno do Servidor</div>
            <div class="error-description">
                Ops! Algo deu errado no servidor. Nossa equipe foi notificada e está trabalhando para resolver o problema.
            </div>
            <a href="<?= base_url() ?>" class="btn-home">
                <i class="fas fa-home me-2"></i>Voltar para o Início
            </a>
            <a href="javascript:location.reload()" class="btn-retry">
                <i class="fas fa-redo me-2"></i>Tentar Novamente
            </a>
            <div class="reference-code">
                <i class="fas fa-code me-1"></i>
                Referência: <?= date('YmdHis') . '-' . uniqid() ?>
            </div>
        </div>
        <div class="footer-note">
            <p class="mb-0">
                <i class="fas fa-shield-alt me-1"></i>
                Sistema de Ponto Eletrônico - Support Solo Sondagens
            </p>
        </div>
    </div>
</body>
</html>
