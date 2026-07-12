<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Login<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup">
            <img class="sp-auth-logo-full" src="<?= sp_safe_url(support_logo_url('auth')) ?>" alt="Support Solo e Sondagens">
        </div>
        <h1 class="h5 mb-1 mt-3">Acesso ao sistema</h1>
        <p class="text-muted mb-0 small">Entre para gerenciar registros de ponto com segurança.</p>
    </div>

    <div class="sp-auth-card-body">
        <?= view('components/flash_messages') ?>

        <form action="<?= route_to('login.authenticate') ?>" method="POST" class="auth-form">
            <?= csrf_field() ?>

            <div class="sp-form-group">
                <label class="sp-label" for="email">E-mail</label>
                <div class="spa-field">
                    <i class="ph ph-envelope-simple spa-lead" aria-hidden="true"></i>
                    <input type="email" class="sp-input" id="email" name="email"
                           value="<?= esc((string) old('email'), 'attr') ?>"
                           placeholder="seu@email.com" required autofocus autocomplete="email">
                </div>
            </div>

            <div class="sp-form-group">
                <label class="sp-label" for="password">Senha</label>
                <div class="spa-field password-toggle">
                    <i class="ph ph-lock-simple spa-lead" aria-hidden="true"></i>
                    <input type="password" class="sp-input" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                    <button type="button" class="password-toggle-btn"
                            data-password-toggle="password" aria-label="Mostrar ou ocultar senha">
                        <i class="bi bi-eye" id="password-icon"></i>
                    </button>
                </div>
            </div>

            <div class="auth-options">
                <?php if (!empty($rememberMeEnabled ?? true)): ?>
                    <label class="sp-checkbox-label">
                        <input type="checkbox" name="remember" value="1" <?= old('remember') ? 'checked' : '' ?>>
                        Manter conectado
                    </label>
                <?php else: ?>
                    <span class="text-muted small">Permanência desabilitada.</span>
                <?php endif; ?>
                <a href="<?= route_to('forgot-password') ?>" class="sp-link">Esqueci minha senha</a>
            </div>

            <button type="submit" class="sp-btn sp-btn-primary sp-btn-full" style="margin-top:.5rem;">
                <i class="ph ph-sign-in" aria-hidden="true"></i> Entrar
            </button>
        </form>

        <div class="auth-divider"><span>ou</span></div>

        <a href="<?= base_url('registro-rapido') ?>" class="sp-btn sp-btn-outline sp-btn-full">
            <i class="ph-fill ph-lightning" aria-hidden="true"></i> Registro rápido sem login
        </a>
    </div>

    <?php if (!empty($selfRegistrationEnabled ?? false)): ?>
        <div class="sp-auth-card-footer">
            <small>Não tem uma conta? <a href="<?= route_to('register') ?>" class="sp-link">Criar conta</a></small>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
