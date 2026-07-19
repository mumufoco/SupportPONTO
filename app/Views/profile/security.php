<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Segurança da conta<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Segurança da conta',
        'subtitle' => 'Revise sessões ativas, dispositivos recentes, autenticação de dois fatores e confirme ações críticas com senha recente.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [
            ['label' => 'Alterar senha', 'icon' => 'bi bi-key-fill', 'url' => site_url(route_to('profile.changePassword'))],
        ],
    ]) ?>

    <div class="sp-grid-2">

        <!-- Autenticação de dois fatores -->
        <div class="sp-card">
            <div class="sp-card-header">
                <h5 class="sp-card-title">
                    <i class="bi bi-shield-check"></i>Autenticação de dois fatores
                </h5>
                <span class="sp-badge <?= $two_factor_enabled ? 'sp-badge-success' : 'sp-badge-neutral' ?>">
                    <?= $two_factor_enabled ? 'Ativo' : 'Inativo' ?>
                </span>
            </div>
            <div class="sp-card-body">
                <?php if ($two_factor_enabled): ?>
                    <div class="sp-alert sp-alert-success" style="margin-bottom:1rem;">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>O 2FA está protegendo o login da sua conta.</span>
                    </div>
                    <p style="font-size:.875rem;color:var(--sp-text-secondary);margin-bottom:1rem;">
                        Códigos de backup restantes: <strong><?= (int) $two_factor_backup_codes_remaining ?></strong>
                    </p>

                    <button type="button" class="sp-btn sp-btn-sm sp-btn-outline sp-btn-full" id="toggleRegenerateBtn" style="margin-bottom:.5rem;">
                        <i class="bi bi-arrow-repeat"></i> Gerar novos códigos de backup
                    </button>
                    <form method="post" action="<?= site_url(route_to('2fa.regenerate-backup-codes')) ?>" id="regenerateForm" style="display:none;margin-bottom:1rem;">
                        <?= csrf_field() ?>
                        <div class="sp-form-group">
                            <label class="sp-label" for="regenerate_password">Confirme sua senha</label>
                            <input type="password" class="sp-input" id="regenerate_password"
                                   name="password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="sp-btn sp-btn-sm sp-btn-primary sp-btn-full">Confirmar geração</button>
                    </form>

                    <button type="button" class="sp-btn sp-btn-sm sp-btn-outline sp-btn-full" id="toggleDisableBtn"
                            style="border-color:var(--sp-danger);color:var(--sp-danger);"
                            onmouseover="this.style.background='var(--sp-danger-light)'"
                            onmouseout="this.style.background=''">
                        <i class="bi bi-shield-x"></i> Desativar 2FA
                    </button>
                    <form method="post" action="<?= site_url(route_to('2fa.disable')) ?>" id="disableForm" style="display:none;margin-top:.75rem;">
                        <?= csrf_field() ?>
                        <div class="sp-form-group">
                            <label class="sp-label" for="disable_password">Confirme sua senha</label>
                            <input type="password" class="sp-input" id="disable_password"
                                   name="password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="sp-btn sp-btn-sm sp-btn-danger sp-btn-full">Confirmar desativação</button>
                    </form>
                <?php else: ?>
                    <div class="sp-alert sp-alert-warning" style="margin-bottom:1rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>O 2FA está desativado. Ative para proteger o login com um código adicional do seu celular.</span>
                    </div>
                    <a href="<?= site_url(route_to('2fa.setup')) ?>" class="sp-btn sp-btn-primary sp-btn-full">
                        <i class="bi bi-shield-plus"></i> Ativar 2FA
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Confirmação reforçada -->
        <div class="sp-card">
            <div class="sp-card-header">
                <h5 class="sp-card-title">
                    <i class="bi bi-unlock-fill"></i>Confirmação reforçada
                </h5>
            </div>
            <div class="sp-card-body">
                <p style="font-size:.875rem;color:var(--sp-text-secondary);margin-bottom:.75rem;">
                    Ações críticas exigem confirmação recente da sua senha.
                </p>

                <div class="sp-alert <?= $step_up_active ? 'sp-alert-success' : 'sp-alert-warning' ?>"
                     style="margin-bottom:1rem;">
                    <i class="bi <?= $step_up_active ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i>
                    <span>
                        <?= $step_up_active ? 'Confirmação recente ativa.' : 'Nenhuma confirmação recente ativa.' ?>
                    </span>
                </div>

                <form id="stepUpForm">
                    <?= csrf_field() ?>
                    <div class="sp-form-group">
                        <label class="sp-label" for="current_password">Confirme sua senha</label>
                        <input type="password" class="sp-input" id="current_password"
                               name="current_password" autocomplete="current-password" required>
                        <span class="sp-field-hint">
                            Validade: <?= esc((string) $step_up_ttl) ?> segundos.
                        </span>
                    </div>
                    <button type="submit" class="sp-btn sp-btn-primary sp-btn-full">
                        <i class="bi bi-check2-circle"></i> Confirmar ações críticas
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sessões ativas -->
    <div class="sp-card">
        <div class="sp-card-header">
            <h5 class="sp-card-title">
                <i class="bi bi-phone-fill"></i>Sessões ativas
            </h5>
            <div class="sp-card-actions">
                <button type="button" class="sp-btn sp-btn-sm sp-btn-outline sp-btn-danger-outline"
                        id="logoutOthersBtn"
                        style="border-color:var(--sp-danger);color:var(--sp-danger);"
                        onmouseover="this.style.background='var(--sp-danger-light)'"
                        onmouseout="this.style.background=''">
                    <i class="bi bi-box-arrow-right"></i> Encerrar outras sessões
                </button>
            </div>
        </div>
        <div class="sp-card-body" style="padding:0;">
            <div class="sp-table-container">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>IP</th>
                            <th>Início</th>
                            <th>Última atividade</th>
                            <th style="text-align:right;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td>
                                    <strong><?= esc($session['device_label'] ?? 'Dispositivo web') ?></strong>
                                    <?php if (!empty($session['is_current'])): ?>
                                        <span class="sp-badge sp-badge-success" style="margin-left:.35rem;">Atual</span>
                                    <?php endif; ?>
                                    <div style="font-size:.75rem;color:var(--sp-text-muted);word-break:break-all;margin-top:.125rem;">
                                        <?= esc($session['user_agent'] ?? '-') ?>
                                    </div>
                                </td>
                                <td><code style="font-size:.8rem;"><?= esc($session['ip'] ?? '-') ?></code></td>
                                <td><?= esc($session['started_at'] ?? '-') ?></td>
                                <td><?= esc($session['last_activity'] ?? '-') ?></td>
                                <td>
                                    <div class="sp-table-actions" style="justify-content:flex-end;">
                                        <?php if (empty($session['is_current'])): ?>
                                            <button type="button"
                                                    class="sp-btn sp-btn-sm revoke-session-btn"
                                                    data-session-key="<?= sp_attr($session['session_key']) ?>"
                                                    style="border:1px solid var(--sp-danger);color:var(--sp-danger);"
                                                    onmouseover="this.style.background='var(--sp-danger-light)'"
                                                    onmouseout="this.style.background=''">
                                                Encerrar
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:.8rem;color:var(--sp-text-muted);">Sessão atual</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
    // 2FA: revela o formulário de confirmação de senha ao clicar nas ações
    document.getElementById('toggleRegenerateBtn')?.addEventListener('click', function () {
        document.getElementById('regenerateForm')?.style.setProperty('display', 'block');
        this.style.display = 'none';
    });
    document.getElementById('toggleDisableBtn')?.addEventListener('click', function () {
        document.getElementById('disableForm')?.style.setProperty('display', 'block');
        this.style.display = 'none';
    });

    // Confirmação de senha (step-up)
    document.getElementById('stepUpForm')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        spFetch('<?= sp_js(site_url(route_to('profile.security.confirm-password'))) ?>', {
            method: 'POST',
            body: formData,
        }).then(r => r.json()).then(data => {
            if (data.success) {
                Toast.success(data.message || 'Confirmação registrada.');
                window.location.reload();
                return;
            }
            Toast.error(data.message || 'Não foi possível validar sua senha.');
        }).catch(() => Toast.error('Falha ao confirmar sua senha.'));
    });

    // Encerrar outras sessões
    document.getElementById('logoutOthersBtn')?.addEventListener('click', function () {
        spFetch('<?= sp_js(site_url(route_to('profile.security.revoke-others'))) ?>', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.success(data.message || 'Sessões encerradas.');
                    window.location.reload();
                    return;
                }
                Toast.error(data.message || 'Ação crítica requer confirmação.');
            }).catch(() => Toast.error('Falha ao encerrar outras sessões.'));
    });

    // Encerrar sessão individual
    document.querySelectorAll('.revoke-session-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const key = this.dataset.sessionKey;
            spFetch(`<?= sp_js(site_url('profile/security/revoke')) ?>/${encodeURIComponent(key)}`, { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Toast.success(data.message || 'Sessão encerrada.');
                        window.location.reload();
                        return;
                    }
                    Toast.error(data.message || 'Ação crítica requer confirmação.');
                }).catch(() => Toast.error('Falha ao encerrar a sessão.'));
        });
    });
</script>
<?= $this->endSection() ?>
