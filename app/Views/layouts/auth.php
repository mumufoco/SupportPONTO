<?php
helper(['custom']);
$loginBgUrl = support_login_background_url();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="author" content="Support Solo Sondagens">
        <meta name="csrf-hash" content="<?= esc(csrf_hash()) ?>">
    <meta name="csrf-token-name" content="<?= esc(csrf_token()) ?>">
    <meta name="csrf-cookie-name" content="<?= esc(config('Security')->cookieName) ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName) ?>">
    <title><?= $this->renderSection('title') ?> - SupportPONTO</title>

    <?= $this->include('layouts/includes/pwa-meta') ?>

    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons 1.11.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- SupportPONTO CSS -->
    <link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/supportponto.css')) ?>">
    <!-- SupportPONTO v2 — Enhancement Layer -->
    <link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/supportponto-v2.css')) ?>">

    <?= $this->renderSection('styles') ?>
</head>
<body>
    <a class="sp-skip-link" href="#sp-auth-content">Ir para o formulário de acesso</a>
    <div class="sp-auth-wrap" id="sp-auth-content" tabindex="-1"<?php if ($loginBgUrl !== ''): ?> style="background-image:url('<?= esc($loginBgUrl, 'attr') ?>');background-size:cover;background-position:center;"<?php endif; ?>>
        <div style="width:min(100%,460px);">
            <?= $this->renderSection('content') ?>
            <p class="text-center mt-3 mb-0 small text-muted" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                &copy; <?= date('Y') ?> SupportPONTO &mdash; Support Solo e Sondagens
            </p>
        </div>
    </div>

    <script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/supportponto.js')) ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
