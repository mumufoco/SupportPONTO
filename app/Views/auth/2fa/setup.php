<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurar 2FA<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-page-header">
    <div class="sp-page-header-left">
        <h1 class="sp-page-title"><i class="bi bi-shield-lock-fill"></i> Autenticação em duas etapas</h1>
        <p class="sp-page-subtitle">Configure o 2FA para aumentar a segurança da sua conta.</p>
    </div>
    <div class="sp-page-header-right">
        <a href="<?= site_url('auth/2fa/manage') ?>" class="sp-btn sp-btn-secondary">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>
</div>


<div class="sp-card">
    <div class="sp-card-header">
        <h5 class="sp-card-title"><i class="bi bi-qr-code"></i> Configure seu autenticador</h5>
    </div>
    <div class="sp-card-body">
        <p class="text-muted mb-4">
            Escaneie o QR Code com seu aplicativo autenticador e confirme com um código válido.
            Caso a imagem não carregue, use a chave manual abaixo.
        </p>

        <div class="sp-grid-2" style="max-width:760px;">
            <div style="text-align:center;">
                <img src="<?= sp_safe_url($qr_code_data) ?>"
                     alt="QR Code para configurar 2FA"
                     style="width:200px;height:200px;border:1px solid var(--sp-border);border-radius:var(--sp-radius-md);padding:.75rem;background:#fff;"
                     loading="eager" referrerpolicy="no-referrer">
                <p class="text-muted small mt-2" style="max-width:200px;margin:0 auto;">
                    Compatível com Google Authenticator, Microsoft Authenticator e Authy.
                </p>
            </div>

            <div>
                <div class="sp-card" style="margin-bottom:1rem;">
                    <div class="sp-card-body" style="padding:1rem;">
                        <p class="sp-info-label mb-1">Chave manual (caso o QR não funcione)</p>
                        <code style="font-size:1rem;font-weight:600;color:var(--sp-primary-dark);
                                     word-break:break-all;user-select:all;"><?= esc($secret) ?></code>

                        <?php if (!empty($otpauth_url ?? null)): ?>
                            <div class="mt-3">
                                <p class="sp-info-label mb-1">URI TOTP</p>
                                <textarea class="sp-textarea" rows="2" readonly
                                          style="font-size:.75rem;min-height:auto;"><?= esc($otpauth_url) ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="post" action="<?= site_url('auth/2fa/enable') ?>" class="auth-form">
                    <?= csrf_field() ?>

                    <div class="sp-form-group">
                        <label class="sp-label" for="code">
                            Código gerado pelo aplicativo <span class="sp-required">*</span>
                        </label>
                        <input type="text" class="sp-input" id="code" name="code"
                               inputmode="numeric" maxlength="6" required
                               placeholder="000000"
                               style="letter-spacing:.2em;font-size:1.25rem;text-align:center;font-weight:600;">
                        <span class="sp-field-hint">Digite o código de 6 dígitos exibido no aplicativo.</span>
                    </div>

                    <div style="display:flex;gap:.5rem;">
                        <button type="submit" class="sp-btn sp-btn-primary">
                            <i class="bi bi-shield-check"></i> Ativar 2FA
                        </button>
                        <a href="<?= site_url('auth/2fa/manage') ?>" class="sp-btn sp-btn-secondary">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
