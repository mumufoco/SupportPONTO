<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurações de Autenticação<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Configurações de Autenticação',
        'subtitle' => 'Sessão, proteção de login e auto-cadastro.',
        'icon'     => 'bi bi-key-fill',
        'actions'  => [
            ['label' => 'Segurança', 'icon' => 'bi bi-shield-fill', 'url' => route_to('admin.settings.security')],
        ],
    ]) ?>


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

        <!-- 3. Auto-Cadastro -->
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
            <div class="sp-data-card__body d-flex gap-2 justify-content-between align-items-center flex-wrap">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetAuthenticationDefaults()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar padrão
                </button>
                <div class="d-flex gap-2">
                    <a href="<?= sp_safe_url(sp_settings_center_url()) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-1"></i>Salvar configurações
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
function resetAuthenticationDefaults() {
    if (!confirm('Deseja restaurar as configurações de autenticação para o padrão? Esta ação não pode ser desfeita.')) return;

    const currentPassword = prompt('Confirme sua senha atual para restaurar as configurações:');
    if (currentPassword === null) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= sp_route_url('admin.settings.authentication.reset') ?>';
    form.innerHTML = '<?= csrf_field() ?>';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'current_password';
    input.value = currentPassword;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>
<?= $this->endSection() ?>
