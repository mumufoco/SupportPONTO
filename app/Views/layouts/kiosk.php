<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

        <meta name="csrf-hash" content="<?= esc(csrf_hash()) ?>">
    <meta name="csrf-token-name" content="<?= esc(csrf_token()) ?>">
    <meta name="csrf-cookie-name" content="<?= esc(config('Security')->cookieName) ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName) ?>">
    <title><?= $this->renderSection('title') ?> - SupportPONTO</title>
    <?= $this->include('layouts/includes/pwa-meta') ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<?= $this->renderSection('styles') ?>

    <!-- SupportPONTO CSS -->
    <link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/supportponto.css')) ?>">
    <link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/supportponto-v2.css')) ?>">
</head>
<body>
<div class="container-lg py-4">
    <?= $this->renderSection('content') ?>
</div>

<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script <?= csp_script_nonce_attr() ?> src="https://unpkg.com/lucide@latest"></script>
<script <?= csp_script_nonce_attr() ?> src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/kiosk-icons.js')) ?>"></script>
<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/supportponto.js')) ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
