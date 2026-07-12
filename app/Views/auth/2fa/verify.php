<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Verificação em duas etapas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup" style="flex-direction:column;align-items:center;gap:.75rem;">
            <img src="<?= support_logo_url('auth') ?>" alt="Support Solo Sondagens" style="width:64px;height:64px;border-radius:12px;">
            <div class="sp-brand-text" style="text-align:center;">
                <strong>SupportPONTO</strong>
                <span>Verificação em duas etapas</span>
            </div>
        </div>
        <h1 class="h5 mb-1 mt-3">Confirme seu acesso</h1>
        <p class="text-muted mb-0 small">Digite o código do aplicativo autenticador ou use um código de backup.</p>
    </div>

    <div class="sp-auth-card-body">
        <?= view('components/flash_messages') ?>

        <form method="post" action="<?= site_url('auth/2fa/verify') ?>" class="auth-form">
            <?= csrf_field() ?>

            <div class="sp-form-group">
                <label class="sp-label" for="code">Código de autenticação</label>
                <input type="text" class="sp-input" id="code" name="code"
                       inputmode="numeric" autocomplete="one-time-code"
                       maxlength="12" required autofocus
                       placeholder="000000"
                       style="text-align:center;font-size:1.5rem;letter-spacing:.25em;font-weight:600;">
                <span class="sp-field-hint">
                    Código TOTP de 6 dígitos ou código de backup (formato: 1234-5678).
                </span>
            </div>

            <label class="sp-checkbox-label mb-3">
                <input type="checkbox" value="1" id="use_backup_code" name="use_backup_code">
                Usar código de backup
            </label>

            <button type="submit" class="sp-btn sp-btn-primary sp-btn-full">
                <i class="bi bi-shield-check"></i> Validar e entrar
            </button>
        </form>

        <form method="post" action="<?= site_url('auth/logout') ?>" class="mt-3">
            <?= csrf_field() ?>
            <button type="submit" class="sp-btn sp-btn-secondary sp-btn-full">
                <i class="bi bi-box-arrow-left"></i> Cancelar e sair
            </button>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
