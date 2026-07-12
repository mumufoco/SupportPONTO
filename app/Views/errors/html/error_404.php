<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página Não Encontrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { background:#f4f6f8; min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif; padding:1rem; }
        .error-container { text-align:center; color:#8a93a0; max-width:600px; }
        .error-card { background:#ffffff; border:1px solid #e4e8ed; border-radius:1rem; padding:3rem 2rem; box-shadow:0 12px 32px rgba(16,24,40,.10); }
        .error-icon { width:96px; height:96px; border-radius:999px; display:inline-flex; align-items:center; justify-content:center; font-size:2.75rem; margin-bottom:1.5rem; background:rgba(31,157,87,.12); color:#1f9d57; animation:float 3s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-12px)} }
        .error-code { font-size:7rem; font-weight:800; line-height:1; color:#18202b; margin-bottom:1rem; letter-spacing:-0.05em; }
        .error-message { font-size:1.6rem; font-weight:700; margin-bottom:0.75rem; color:#18202b; }
        .error-description { font-size:1rem; color:#5a6472; margin-bottom:2rem; line-height:1.6; }
        .btn-home { background:#17834a; color:#fff; padding:0.8rem 1.75rem; border-radius:0.7rem; font-weight:600; text-decoration:none; display:inline-block; transition:background .15s; border:none; }
        .btn-home:hover { background:#1f9d57; color:#fff; }
        .link-back { color:#8a93a0; text-decoration:none; font-weight:600; font-size:0.95rem; transition:color .2s; }
        .link-back:hover { color:#17834a; }
        .footer-note { margin-top:2.5rem; color:#8a93a0; font-size:0.875rem; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">
                <i class="fas fa-search"></i>
            </div>
            <div class="error-code">404</div>
            <div class="error-message">Página Não Encontrada</div>
            <div class="error-description">
                A página que você está procurando não existe, foi removida ou está temporariamente indisponível.
            </div>
            <a href="<?= base_url() ?>" class="btn-home">
                <i class="fas fa-home me-2"></i>Voltar para o Início
            </a>
            <div class="mt-4">
                <a href="javascript:history.back()" class="link-back">
                    <i class="fas fa-arrow-left me-1"></i>Voltar à página anterior
                </a>
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
