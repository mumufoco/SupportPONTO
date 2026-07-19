<?php
helper(['session_context', 'navigation_context']);
$pageTitle = trim((string) $this->renderSection('title')) ?: ($title ?? 'Dashboard');
$employee = sp_session_user();
$isAuthenticated = sp_session_is_authenticated();

// Lembrete flutuante de consentimento LGPD pendente: só computado nas
// páginas de dashboard (rota de pouso pós-login), para não disparar em
// toda página autenticada do sistema.
$__dashboardPaths = ['dashboard', 'dashboard/admin', 'dashboard/manager', 'dashboard/dpo', 'dashboard/employee', 'inicio', 'painel'];
$__pendingGateConsents = ($isAuthenticated && in_array(trim(uri_string(), '/'), $__dashboardPaths, true))
    ? sp_pending_gate_consents((int) ($employee['id'] ?? 0))
    : [];
?>
<!DOCTYPE html>
<?php
    $_mainTheme = 'dark';
    try {
        $_mainTheme = model(\App\Models\SettingModel::class)->get('theme_mode', 'dark') ?: 'dark';
        if (!in_array($_mainTheme, ['light', 'dark', 'auto'], true)) $_mainTheme = 'dark';
        if ($_mainTheme === 'auto') $_mainTheme = 'dark'; // server-side default for auto
    } catch (\Throwable $_thEx) {}
?>
<html lang="pt-BR" data-theme="<?= esc($_mainTheme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="author" content="Support Solo Sondagens">
        <meta name="csrf-hash" content="<?= sp_attr(csrf_hash()) ?>">
    <meta name="csrf-token-name" content="<?= sp_attr(csrf_token()) ?>">
    <meta name="csrf-cookie-name" content="<?= sp_attr(config('Security')->cookieName) ?>">
    <meta name="csrf-header" content="<?= sp_attr(config('Security')->headerName) ?>">
    <title><?= esc($pageTitle) ?> - SupportPONTO</title>

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

    <?= $this->include('layouts/includes/design_system_vars') ?>

<?= $this->renderSection('styles') ?>

    <?php if (getenv('SENTRY_DSN_JS') && getenv('CI_ENVIRONMENT') === 'production'): ?>
    <!-- Sentry Error Tracking -->
    <script <?= csp_script_nonce_attr() ?>
      src="https://browser.sentry-cdn.com/7.99.0/bundle.tracing.min.js"
      integrity="sha384-VJqbG4y9aRtaWQ9rYzKp6h6cPVJbLYhCL9kSHcxEMjL1NjSfRQNEJLNFzSPvKNV2"
      crossorigin="anonymous"
    ></script>
    <script <?= csp_script_nonce_attr() ?>>
      Sentry.init({
        dsn: "<?= sp_js(getenv('SENTRY_DSN_JS')) ?>",
        environment: "<?= sp_js(getenv('CI_ENVIRONMENT') ?: 'production') ?>",
        integrations: [
          new Sentry.BrowserTracing(),
          new Sentry.Replay({
            maskAllText: true,
            blockAllMedia: true,
          })
        ],
        tracesSampleRate: 0.1, // 10% of transactions
        replaysSessionSampleRate: 0.1, // 10% of sessions
        replaysOnErrorSampleRate: 1.0, // 100% of errors
        beforeSend(event, hint) {
          // Filter sensitive data
          if (event.request) {
            delete event.request.cookies;
            if (event.request.headers) {
              delete event.request.headers['Authorization'];
              delete event.request.headers['Cookie'];
            }
          }
          return event;
        },
        ignoreErrors: [
          // Common browser errors we can't control
          'ResizeObserver loop limit exceeded',
          'Non-Error promise rejection captured',
          'Network request failed',
        ],
      });
      
      <?php if ($isAuthenticated && !empty($employee)): ?>
      // Set user context
      Sentry.setUser({
        id: "<?= sp_js($employee['id'] ?? '') ?>",
        email: "<?= sp_js($employee['email'] ?? '') ?>",
        username: "<?= sp_js($employee['name'] ?? '') ?>",
        role: "<?= sp_js($employee['role'] ?? '') ?>",
      });
      <?php endif; ?>
    </script>
    <?php endif; ?>

</head>
<body>
<!-- Bootstrap JS carregado cedo (antes do conteudo da pagina) para que scripts
     inline de paginas (ex.: new bootstrap.Modal(...)) nao rodem antes dele existir -->
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<a class="sp-skip-link" href="#sp-main-content">Ir para o conteúdo principal</a>
<?php if ($isAuthenticated): ?>
    <div class="app-shell">
        <?= view('partials/sidebar', ['employee' => $employee]) ?>
        <main class="app-main" id="sp-main-content" tabindex="-1">
            <?= view('partials/topbar', ['employee' => $employee, 'pageTitle' => $pageTitle]) ?>
            <div class="app-content">
                <?= view('components/flash_messages') ?>
                <?= view('components/action_feedback_region', ['id' => 'sp-global-action-feedback', 'label' => 'Feedback global da aplicação']) ?>
                <?= view('components/mobile_action_bar', ['employee' => $employee]) ?>

                <?= $this->renderSection('content') ?>
            </div>
    
        <?php
        $__role = $employee['role'] ?? '';
        if ($isAuthenticated && in_array($__role, ['admin','gestor','rh'], true)):
        ?>
        <div class="position-fixed d-flex gap-2" style="bottom:2rem;right:2rem;z-index:1050;">
            <a href="<?= site_url('chat') ?>"
               class="d-flex align-items-center gap-2 btn btn-success shadow-lg px-4 py-3"
               style="border-radius:2.5rem;font-size:1rem;"
               title="Chat interno">
                <i class="bi bi-chat-dots-fill fs-5"></i>
                <span class="d-none d-sm-inline fw-semibold">Chat</span>
            </a>
            <a href="<?= site_url('admin/employees/overview') ?>"
               class="d-flex align-items-center gap-2 btn btn-primary shadow-lg px-4 py-3"
               style="border-radius:2.5rem;font-size:1rem;"
               title="Dados de Colaboradores">
                <i class="bi bi-people-fill fs-5"></i>
                <span class="d-none d-sm-inline fw-semibold">Dados Colaboradores</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($__pendingGateConsents)): ?>
            <?= view('partials/consent_gate_modal', ['pending' => $__pendingGateConsents]) ?>
        <?php endif; ?>
    </main>
    </div>
<?php else: ?>
    <?= $this->renderSection('content') ?>
<?php endif; ?>

<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('js/supportponto.js')) ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
