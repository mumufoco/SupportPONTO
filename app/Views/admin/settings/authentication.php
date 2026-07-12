<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurações de Autenticação<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Configurações de Autenticação',
        'subtitle' => 'Sessão, proteção de login, 2FA e políticas de senha.',
        'icon'     => 'bi bi-key-fill',
        'actions'  => [
            ['label' => '2FA Avançado', 'icon' => 'bi bi-shield-lock-fill', 'url' => route_to('admin.settings.two-factor')],
            ['label' => 'Segurança',    'icon' => 'bi bi-shield-fill',      'url' => route_to('admin.settings.security')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.authentication.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- 1. Sessão e permanência -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-clock-fill"></i>Sessão e Permanência</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="session_timeout" class="form-label fw-semibold">
                            Tempo de sessão (segundos) <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control" id="session_timeout" name="session_timeout"
                               value="<?= esc($settings['session_timeout'] ?? 3600) ?>"
                               min="300" max="86400" required>
                        <div class="form-text">Expira por inatividade. Padrão: 3600 = 1 hora.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-block">Lembrar-me</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox"
                                   id="enable_remember_me" name="enable_remember_me" value="1"
                                   <?= ($settings['enable_remember_me'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_remember_me">Permitir permanência prolongada</label>
                        </div>
                        <div class="form-text">Mantém o usuário logado entre sessões.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="remember_me_duration" class="form-label fw-semibold">Duração do lembrar-me (segundos)</label>
                        <input type="number" class="form-control" id="remember_me_duration" name="remember_me_duration"
                               value="<?= esc($settings['remember_me_duration'] ?? 2592000) ?>"
                               min="86400" max="31536000">
                        <div class="form-text">Padrão: 2592000 = 30 dias.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Proteção de Login -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-shield-fill-exclamation"></i>Proteção de Login</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="max_login_attempts" class="form-label fw-semibold">Máx. tentativas de login</label>
                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts"
                               value="<?= esc($settings['max_login_attempts'] ?? 5) ?>"
                               min="3" max="20">
                        <div class="form-text">Bloqueio após este número de tentativas consecutivas.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="lockout_duration" class="form-label fw-semibold">Duração do bloqueio (segundos)</label>
                        <input type="number" class="form-control" id="lockout_duration" name="lockout_duration"
                               value="<?= esc($settings['lockout_duration'] ?? 900) ?>"
                               min="60" max="86400">
                        <div class="form-text">Padrão: 900 = 15 minutos.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Autenticação em Duas Etapas (2FA) -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-phone-fill"></i>Autenticação em Duas Etapas (2FA)</h2>
                <a href="<?= sp_safe_url(route_to('admin.settings.two-factor')) ?>" class="btn btn-sm btn-outline-primary ms-auto">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Configuração avançada
                </a>
            </div>
            <div class="sp-data-card__body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   id="enable_2fa" name="enable_2fa" value="1"
                                   <?= ($settings['enable_2fa'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enable_2fa">Habilitar 2FA</label>
                        </div>
                        <div class="form-text">TOTP (Google Authenticator, Authy) ou e-mail.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="2fa_method" class="form-label fw-semibold">Método</label>
                        <select class="form-select" id="2fa_method" name="2fa_method">
                            <option value="totp"  <?= ($settings['2fa_method'] ?? 'totp') === 'totp'  ? 'selected' : '' ?>>TOTP — App autenticador</option>
                            <option value="email" <?= ($settings['2fa_method'] ?? '') === 'email' ? 'selected' : '' ?>>E-mail</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Política de Senha -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-lock-fill"></i>Política de Senha</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="password_min_length" class="form-label fw-semibold">Tamanho mínimo de senha</label>
                        <input type="number" class="form-control" id="password_min_length" name="password_min_length"
                               value="<?= esc($settings['password_min_length'] ?? 8) ?>"
                               min="6" max="64">
                        <div class="form-text">Mínimo recomendado: 8 caracteres.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="password_reset_expiry" class="form-label fw-semibold">Expiração do link de reset (segundos)</label>
                        <input type="number" class="form-control" id="password_reset_expiry" name="password_reset_expiry"
                               value="<?= esc($settings['password_reset_expiry'] ?? 3600) ?>"
                               min="300" max="86400">
                        <div class="form-text">Padrão: 3600 = 1 hora.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. CAPTCHA -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-puzzle-fill"></i>CAPTCHA no Login</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox"
                               id="enable_captcha" name="enable_captcha" value="1"
                               <?= ($settings['enable_captcha'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="enable_captcha">Habilitar CAPTCHA</label>
                    </div>
                    <div class="form-text">Exige verificação humana após tentativas suspeitas de login.</div>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small">Provedor</label>
                        <select class="form-select" name="captcha_provider">
                            <option value="recaptcha_v2" <?= ($settings['captcha_provider'] ?? '') === 'recaptcha_v2' ? 'selected' : '' ?>>Google reCAPTCHA v2</option>
                            <option value="recaptcha_v3" <?= ($settings['captcha_provider'] ?? '') === 'recaptcha_v3' ? 'selected' : '' ?>>Google reCAPTCHA v3</option>
                            <option value="hcaptcha"     <?= ($settings['captcha_provider'] ?? '') === 'hcaptcha'     ? 'selected' : '' ?>>hCaptcha</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="captcha_site_key" class="form-label fw-semibold small">Site Key</label>
                        <input type="text" class="form-control form-control-sm" id="captcha_site_key"
                               name="captcha_site_key"
                               value="<?= esc($settings['captcha_site_key'] ?? '') ?>"
                               placeholder="Chave pública do CAPTCHA">
                    </div>
                    <div class="col-md-4">
                        <label for="captcha_secret_key" class="form-label fw-semibold small">Secret Key</label>
                        <input type="password" class="form-control form-control-sm" id="captcha_secret_key"
                               name="captcha_secret_key"
                               placeholder="<?= !empty($settings['captcha_secret_key']) ? '••••• configurada' : 'Chave privada' ?>">
                        <div class="form-text">Deixe em branco para manter a chave atual.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Auto-Cadastro -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-person-plus-fill"></i>Auto-Cadastro de Funcionários</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox"
                           id="self_registration_enabled" name="self_registration_enabled" value="1"
                           <?= !empty($settings['self_registration_enabled']) && ($settings['self_registration_enabled'] === '1' || $settings['self_registration_enabled'] === 1 || $settings['self_registration_enabled'] === true) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="self_registration_enabled">Permitir auto-cadastro</label>
                </div>
                <div class="form-text mb-2">
                    Quando ativado, o botão <strong>"Criar conta"</strong> aparece na tela de login. Cadastros ficam <strong>pendentes de aprovação</strong> pelo administrador.
                </div>
                <?php if (!empty($settings['self_registration_enabled']) && ($settings['self_registration_enabled'] === '1' || $settings['self_registration_enabled'] === true)): ?>
                <div class="alert alert-success py-2 mb-0 small">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Auto-cadastro <strong>ativado</strong> — <a href="<?= site_url('register') ?>" target="_blank" class="alert-link"><?= site_url('register') ?></a>
                </div>
                <?php else: ?>
                <div class="alert alert-secondary py-2 mb-0 small">
                    <i class="bi bi-x-circle me-1"></i>
                    Auto-cadastro <strong>desativado</strong> — o link "Criar conta" redireciona para o login.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botões -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(sp_settings_center_url()) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-1"></i>Salvar configurações
                </button>
            </div>
        </div>

    </form>
</div>
<?= $this->endSection() ?>
