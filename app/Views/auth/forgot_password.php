<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Recuperar senha<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup">
            <img class="sp-auth-logo-full" src="<?= sp_safe_url(support_logo_url('auth')) ?>" alt="Support Solo e Sondagens">
        </div>
        <h1 class="h5 mb-1 mt-3">Recuperação de senha</h1>
        <p class="text-muted mb-0 small">Informe seu e-mail para receber as instruções de redefinição.</p>
    </div>

    <div class="sp-auth-card-body">
        <?php if (session()->has('error')): ?>
            <div class="sp-alert sp-alert-danger">
                <i class="ph-fill ph-warning-circle" aria-hidden="true"></i>
                <span><?= esc(session('error')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->has('success')): ?>
            <div class="sp-alert sp-alert-success">
                <i class="ph-fill ph-check-circle" aria-hidden="true"></i>
                <span><?= esc(session('success')) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= route_to('forgot-password.send') ?>" method="POST" class="auth-form">
            <?= csrf_field() ?>

            <div class="sp-form-group">
                <label class="sp-label" for="email">E-mail cadastrado</label>
                <div class="spa-field">
                    <i class="ph ph-envelope-simple spa-lead" aria-hidden="true"></i>
                    <input type="email" class="sp-input" id="email" name="email"
                           value="<?= esc((string) old('email'), 'attr') ?>"
                           placeholder="seu@email.com" required autofocus autocomplete="email">
                </div>
                <span class="sp-field-hint">Enviaremos um link de recuperação para este e-mail.</span>
            </div>

            <button type="submit" class="sp-btn sp-btn-primary sp-btn-full">
                <i class="ph-fill ph-paper-plane-tilt" aria-hidden="true"></i> Enviar link de recuperação
            </button>
        </form>

        <div class="text-center mt-3">
            <a href="<?= route_to('login') ?>" class="sp-link">
                <i class="ph ph-arrow-left" aria-hidden="true"></i> Voltar ao login
            </a>
        </div>
    </div>

    <?php if (!empty($selfRegistrationEnabled ?? false)): ?>
        <div class="sp-auth-card-footer">
            <small>Sem acesso? <a href="<?= sp_safe_url($forgotPasswordUrl ?? route_to('register')) ?>" class="sp-link">Solicitar cadastro</a></small>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
SupportPontoValidation.bindEmailFormatField(document.getElementById('email'));
</script>
<?= $this->endSection() ?>
