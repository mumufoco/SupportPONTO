<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Redefinir senha<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup">
            <img class="sp-auth-logo-full" src="<?= sp_safe_url(support_logo_url('auth')) ?>" alt="Support Solo e Sondagens">
        </div>
        <h1 class="h5 mb-1 mt-3">Redefinir senha</h1>
        <p class="text-muted mb-0 small">Defina sua nova senha para acessar o SupportPONTO.</p>
    </div>

    <div class="sp-auth-card-body">
        <?php if (session()->has('errors')): ?>
            <div class="sp-alert sp-alert-danger">
                <i class="ph-fill ph-warning-circle" aria-hidden="true"></i>
                <div>
                    <ul class="mb-0 ps-3">
                        <?php foreach (session('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <form action="<?= route_to('reset-password.reset') ?>" method="post" class="auth-form" id="resetForm">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= esc($token ?? '', 'attr') ?>">

            <div class="sp-form-group">
                <label class="sp-label" for="password">Nova senha</label>
                <div class="spa-field password-toggle">
                    <i class="ph ph-lock-simple spa-lead" aria-hidden="true"></i>
                    <input type="password" class="sp-input" id="password" name="password"
                           placeholder="Mínimo 12 caracteres" required minlength="12"
                           autocomplete="new-password">
                    <button type="button" class="password-toggle-btn"
                            data-password-toggle="password" aria-label="Mostrar ou ocultar senha">
                        <i class="bi bi-eye" id="password-icon"></i>
                    </button>
                </div>
            </div>

            <div class="password-strength sp-hidden" id="passwordStrength">
                <span class="password-strength-label">Força da senha: <strong id="strengthText">Fraca</strong></span>
                <div class="password-strength-bar">
                    <div class="password-strength-fill weak" id="strengthBar"></div>
                </div>
            </div>

            <div class="password-requirements">
                <ul id="passwordRequirements">
                    <li id="req-length"><i class="bi bi-circle"></i> Mínimo de 12 caracteres</li>
                    <li id="req-upper"><i class="bi bi-circle"></i> Pelo menos uma letra maiúscula</li>
                    <li id="req-lower"><i class="bi bi-circle"></i> Pelo menos uma letra minúscula</li>
                    <li id="req-number"><i class="bi bi-circle"></i> Pelo menos um número</li>
                </ul>
            </div>

            <div class="sp-form-group">
                <label class="sp-label" for="confirm_password">Confirmar nova senha</label>
                <div class="spa-field password-toggle">
                    <i class="ph ph-lock-simple spa-lead" aria-hidden="true"></i>
                    <input type="password" class="sp-input" id="confirm_password" name="confirm_password"
                           placeholder="Digite a senha novamente" required autocomplete="new-password">
                    <button type="button" class="password-toggle-btn"
                            data-password-toggle="confirm_password"
                            aria-label="Mostrar ou ocultar confirmação">
                        <i class="bi bi-eye" id="confirm_password-icon"></i>
                    </button>
                </div>
                <span class="sp-field-hint" id="confirmMatch"></span>
            </div>

            <div style="display:flex;gap:.5rem;">
                <button type="submit" class="sp-btn sp-btn-primary" style="flex:1;">
                    <i class="ph ph-check-circle" aria-hidden="true"></i> Salvar nova senha
                </button>
                <a href="<?= route_to('login') ?>" class="sp-btn sp-btn-danger-soft sp-btn-icon"
                   title="Cancelar">
                    <i class="ph ph-x-circle" aria-hidden="true"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
