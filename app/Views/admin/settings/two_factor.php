<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Autenticação de Dois Fatores (2FA)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Autenticação de Dois Fatores (2FA)',
        'subtitle' => 'Configure, ative e monitore a autenticação em duas etapas para maior segurança do sistema.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [
            ['label' => 'Autenticação', 'icon' => 'bi bi-key-fill', 'url' => sp_route_url('admin.settings.authentication')],
        ],
    ]) ?>


    <!-- Status -->
    <?php
    $enabled = ($settings['enable_2fa'] ?? '0') === '1';
    $forced  = ($settings['2fa_force_all_users'] ?? '0') === '1';
    $method  = strtoupper($settings['2fa_method'] ?? 'TOTP');
    $backupCodes = (int) ($settings['2fa_backup_codes_count'] ?? 8);
    ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => $enabled ? 'bi bi-shield-check-fill' : 'bi bi-shield-slash-fill',
                'iconColor' => $enabled ? 'success' : 'warning',
                'value' => $enabled ? 'Ativo' : 'Inativo',
                'label' => '2FA',
                'indicator' => $method,
                'indicatorType' => $enabled ? 'success' : 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-phone-fill',
                'iconColor' => 'info',
                'value' => $method,
                'label' => 'Método',
                'indicator' => $method === 'TOTP' ? 'App autenticador' : 'Código por e-mail',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-people-fill',
                'iconColor' => $forced ? 'warning' : 'primary',
                'value' => $forced ? 'Sim' : 'Não',
                'label' => 'Forçado para todos',
                'indicator' => $forced ? 'Obrigatório no próximo login' : 'Opcional por usuário',
                'indicatorType' => $forced ? 'warning' : 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-key-fill',
                'iconColor' => 'primary',
                'value' => (string) $backupCodes,
                'label' => 'Backup codes',
                'indicator' => 'Por usuário',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
    </div>

    <!-- Configurações de 2FA -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-toggles"></i> Configurações de 2FA</span>
        </div>
        <div class="sp-card-body">
            <form action="<?= sp_safe_url(sp_route_url('admin.settings.two-factor.update')) ?>" method="POST" id="two-factor-form">
                <?= csrf_field() ?>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="enable_2fa" value="1"
                                   id="enable_2fa" <?= $enabled ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enable_2fa">Habilitar 2FA</label>
                        </div>
                        <span class="sp-help">Ativa autenticação em duas etapas. Usuários precisarão de um segundo fator no login.</span>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" name="2fa_force_all_users" value="1"
                                   id="2fa_force_all_users" <?= $forced ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="2fa_force_all_users">Forçar 2FA para todos</label>
                        </div>
                        <span class="sp-help">Todos os usuários serão obrigados a configurar 2FA no próximo login.</span>
                    </div>
                    <div class="col-md-6">
                        <label for="2fa_method" class="form-label fw-semibold">Método</label>
                        <select class="form-select" id="2fa_method" name="2fa_method">
                            <option value="totp" <?= ($settings['2fa_method'] ?? 'totp') === 'totp' ? 'selected' : '' ?>>
                                TOTP — App autenticador (Google Authenticator, Authy…)
                            </option>
                            <option value="email" <?= ($settings['2fa_method'] ?? '') === 'email' ? 'selected' : '' ?>>
                                E-mail — Código enviado por e-mail
                            </option>
                        </select>
                        <span class="sp-help">TOTP é recomendado: mais seguro e independente do e-mail.</span>
                    </div>
                    <div class="col-md-6">
                        <label for="2fa_backup_codes_count" class="form-label fw-semibold">Códigos de backup por usuário</label>
                        <input type="number" class="form-control" id="2fa_backup_codes_count"
                               name="2fa_backup_codes_count"
                               value="<?= $backupCodes ?>"
                               min="4" max="20">
                        <span class="sp-help">Códigos de uso único para acesso de emergência (4 a 20).</span>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="<?= sp_safe_url(sp_route_url('admin.settings.authentication')) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Voltar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-1"></i>Salvar configurações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Testar TOTP -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-qr-code"></i> Testar TOTP</span>
            <span class="text-muted small">Gere um QR Code e confirme que o código funciona antes de ativar.</span>
        </div>
        <div class="sp-card-body">
            <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-4 small">
                <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                <span>Esta seção é para <strong>testar</strong> a integração TOTP. Gere um QR Code, escaneie com Google Authenticator ou Authy, e verifique o código gerado antes de habilitar o 2FA para todos os usuários.</span>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <p class="fw-semibold small mb-2">
                        <span class="badge bg-primary me-1">1</span> Gerar QR Code
                    </p>
                    <button type="button" class="btn btn-outline-primary mb-3" id="btn_gen_qr">
                        <i class="bi bi-qr-code-scan me-2"></i>Gerar novo QR Code
                    </button>
                    <div id="qr_wrapper" style="display:none">
                        <img id="qr_img" src="" alt="QR Code 2FA"
                             style="width:200px;height:200px;border:1px solid var(--sp-border,#e4e8ed);padding:8px;background:#fff;display:block;">
                        <label class="form-label small fw-semibold mt-2 mb-1">Chave manual:</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control font-monospace" id="qr_secret" readonly placeholder="Chave TOTP">
                            <button class="btn btn-outline-secondary" type="button" id="btn_copy_secret" title="Copiar">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <p class="fw-semibold small mb-2">
                        <span class="badge bg-primary me-1">2</span> Verificar código
                    </p>
                    <p class="small text-muted mb-3">Após escanear o QR Code, insira o código de 6 dígitos exibido pelo app.</p>
                    <div class="d-flex gap-2 align-items-end mb-2">
                        <div>
                            <label for="verify_code" class="form-label small fw-semibold">Código TOTP</label>
                            <input type="text" class="form-control font-monospace" id="verify_code"
                                   maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                                   style="max-width:140px;letter-spacing:.2em;font-size:1.1rem;">
                        </div>
                        <button type="button" class="btn btn-success" id="btn_verify_code">
                            <i class="bi bi-check-lg me-1"></i>Verificar
                        </button>
                    </div>
                    <div id="verify_result"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Eventos recentes -->
    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-clock-history"></i> Eventos recentes de 2FA</span>
        </div>
        <?php if (! empty($recent_events)): ?>
            <div class="sp-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3" style="width:140px">Evento</th>
                                <th style="width:100px">Usuário</th>
                                <th style="width:130px">IP</th>
                                <th class="pe-3">Data/Hora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $ev): ?>
                                <?php
                                $act = $ev['action'] ?? '';
                                $badge = match ($act) {
                                    '2fa_success'  => '<span class="badge bg-success">Sucesso</span>',
                                    '2fa_failure'  => '<span class="badge bg-danger">Falha</span>',
                                    '2fa_setup'    => '<span class="badge bg-primary">Configurado</span>',
                                    '2fa_disabled' => '<span class="badge bg-secondary">Desativado</span>',
                                    default        => '<span class="badge bg-secondary">' . esc($act) . '</span>',
                                };
                                ?>
                                <tr>
                                    <td class="ps-3"><?= $badge ?></td>
                                    <td class="small text-muted"><?= ! empty($ev['user_id']) ? 'ID: ' . (int) $ev['user_id'] : '<em>Sistema</em>' ?></td>
                                    <td class="small font-monospace text-muted"><?= esc($ev['ip_address'] ?? '-') ?></td>
                                    <td class="small text-muted pe-3"><?= esc($ev['created_at'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="sp-card-body">
                <div class="sp-callout-neutral mb-0">
                    <i class="bi bi-info-circle me-2"></i>Nenhum evento de 2FA registrado. Os eventos aparecem após ativações, verificações ou falhas.
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';
    const GEN_URL    = '<?= sp_safe_url(sp_route_url('admin.settings.two-factor.generate-qr')) ?>';
    const VERIFY_URL = '<?= sp_safe_url(sp_route_url('admin.settings.two-factor.verify')) ?>';
    const CSRF_NAME  = '<?= csrf_token() ?>';
    let   csrfHash   = '<?= csrf_hash() ?>';
    let   currentSecret = '';

    function post(url, data) {
        data[CSRF_NAME] = csrfHash;
        return spFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(data).toString(),
        }).then(function (r) {
            return r.json().then(function (j) {
                if (j.csrf_hash) csrfHash = j.csrf_hash;
                return j;
            });
        });
    }

    // Generate QR
    document.getElementById('btn_gen_qr').addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Gerando…';
        post(GEN_URL, {}).then(function (d) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-qr-code-scan me-2"></i>Gerar novo QR Code';
            if (d.success) {
                document.getElementById('qr_img').src      = d.qr_url;
                document.getElementById('qr_secret').value = d.secret;
                document.getElementById('qr_wrapper').style.display = '';
                currentSecret = d.secret;
                document.getElementById('verify_result').innerHTML = '';
                document.getElementById('verify_code').value = '';
            } else { alert(d.message || 'Erro ao gerar QR Code.'); }
        }).catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-qr-code-scan me-2"></i>Gerar novo QR Code';
            alert('Erro de comunicação com o servidor.');
        });
    });

    // Copy secret
    document.getElementById('btn_copy_secret').addEventListener('click', function () {
        const val = document.getElementById('qr_secret').value;
        if (!val) return;
        const btn = this;
        navigator.clipboard.writeText(val).then(function () {
            btn.innerHTML = '<i class="bi bi-check-lg text-success"></i>';
            setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 2000);
        }).catch(function () { document.getElementById('qr_secret').select(); document.execCommand('copy'); });
    });

    // Verify code
    document.getElementById('btn_verify_code').addEventListener('click', function () {
        const code    = document.getElementById('verify_code').value.trim();
        const resultEl = document.getElementById('verify_result');
        if (!currentSecret) {
            resultEl.innerHTML = '<div class="alert alert-warning py-2 small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Gere um QR Code primeiro (passo 1).</div>';
            return;
        }
        if (!/^\d{6}$/.test(code)) {
            resultEl.innerHTML = '<div class="alert alert-warning py-2 small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>Digite exatamente 6 dígitos numéricos.</div>';
            return;
        }
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verificando…';
        post(VERIFY_URL, { code: code, secret: currentSecret }).then(function (d) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Verificar';
            resultEl.innerHTML = d.success
                ? '<div class="alert alert-success py-2 small mb-0 d-flex gap-2"><i class="bi bi-check-circle-fill mt-1 flex-shrink-0"></i><span><strong>Código válido!</strong> O TOTP está funcionando corretamente.</span></div>'
                : '<div class="alert alert-danger py-2 small mb-0 d-flex gap-2"><i class="bi bi-x-circle-fill mt-1 flex-shrink-0"></i><span><strong>Código inválido.</strong> ' + (d.message || 'Verifique se o app está sincronizado.') + '</span></div>';
        }).catch(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Verificar';
            resultEl.innerHTML = '<div class="alert alert-danger py-2 small mb-0"><i class="bi bi-x-circle me-1"></i>Erro de comunicação.</div>';
        });
    });

    document.getElementById('verify_code').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') document.getElementById('btn_verify_code').click();
    });
})();
</script>
<?= $this->endSection() ?>
