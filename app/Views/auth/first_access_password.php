<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Primeiro acesso<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup" style="flex-direction:column;align-items:center;gap:.75rem;">
            <img src="<?= support_logo_url('auth') ?>" alt="Support Solo Sondagens" style="width:64px;height:64px;border-radius:12px;">
            <div class="sp-brand-text" style="text-align:center;">
                <strong>SupportPONTO</strong>
                <span>Support Solo Sondagens</span>
            </div>
        </div>
        <h1 class="h5 mb-1 mt-3">Defina sua senha definitiva</h1>
        <p class="text-muted mb-0 small">Substitua a senha temporária por uma senha forte para continuar.</p>
    </div>

    <div class="sp-auth-card-body">
        <?php if (session()->has('errors')): ?>
            <div class="sp-alert sp-alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div>
                    <ul class="mb-0 ps-3">
                        <?php foreach (session('errors') as $error): ?>
                            <li><?= esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (session()->has('error')): ?>
            <div class="sp-alert sp-alert-warning">
                <i class="bi bi-shield-lock-fill"></i>
                <span><?= esc(session('error')) ?></span>
            </div>
        <?php endif; ?>

        <form action="<?= route_to('first-access-password.update') ?>" method="post" class="auth-form">
            <?= csrf_field() ?>

            <div class="sp-form-group">
                <label class="sp-label" for="password">Nova senha</label>
                <div class="password-toggle">
                    <input type="password" class="sp-input" id="password" name="password"
                           placeholder="Crie uma senha forte" required minlength="8"
                           autocomplete="new-password">
                    <button type="button" class="password-toggle-btn"
                            data-password-toggle="password" aria-label="Mostrar ou ocultar senha">
                        <i class="bi bi-eye" id="password-icon"></i>
                    </button>
                </div>
            </div>

            <div class="sp-form-group">
                <label class="sp-label" for="confirm_password">Confirmar nova senha</label>
                <div class="password-toggle">
                    <input type="password" class="sp-input" id="confirm_password" name="confirm_password"
                           placeholder="Repita a nova senha" required autocomplete="new-password">
                    <button type="button" class="password-toggle-btn"
                            data-password-toggle="confirm_password"
                            aria-label="Mostrar ou ocultar confirmação">
                        <i class="bi bi-eye" id="confirm_password-icon"></i>
                    </button>
                </div>
            </div>

            <div class="sp-alert sp-alert-info">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    <strong>Requisitos mínimos:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        <li>Pelo menos 12 caracteres</li>
                        <li>Letras maiúsculas e minúsculas</li>
                        <li>Números e caracteres especiais</li>
                    </ul>
                </div>
            </div>

            <button type="submit" class="sp-btn sp-btn-primary sp-btn-full">
                <i class="bi bi-shield-check"></i> Salvar senha definitiva
            </button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
