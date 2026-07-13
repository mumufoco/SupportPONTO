<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>E-mail<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'E-mail',
        'subtitle' => 'Configure o servidor SMTP usado para envio de todas as notificações do sistema.',
        'icon'     => 'bi bi-envelope-fill',
        'actions'  => [
            ['label' => 'Templates de e-mail', 'icon' => 'bi bi-file-earmark-text', 'url' => sp_route_url('admin.settings.email-templates')],
        ],
    ]) ?>


    <!-- Servidor SMTP -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-server"></i> Servidor SMTP</span>
            <span class="text-muted small">Aplicado imediatamente a todos os envios do sistema.</span>
        </div>
        <div class="sp-card-body">
            <form action="<?= sp_safe_url(sp_route_url('admin.settings.email.update')) ?>" method="POST" id="smtp-form">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="smtp-host">Servidor SMTP <span class="text-danger">*</span></label>
                        <input type="text" id="smtp-host" class="form-control" name="smtp_host"
                               value="<?= esc(old('smtp_host', $settings['smtp_host'] ?? '')) ?>"
                               placeholder="smtp.gmail.com" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="smtp-port">Porta <span class="text-danger">*</span></label>
                        <input type="number" id="smtp-port" class="form-control" name="smtp_port"
                               value="<?= esc(old('smtp_port', $settings['smtp_port'] ?? '587')) ?>"
                               placeholder="587" min="1" max="65535" required>
                        <span class="sp-help">587 (TLS) ou 465 (SSL) são as mais comuns.</span>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="smtp-encryption">Segurança</label>
                        <?php $sec = old('smtp_secure', $settings['smtp_secure'] ?? 'tls'); ?>
                        <select id="smtp-encryption" class="form-select" name="smtp_secure">
                            <?php foreach (['tls' => 'TLS (recomendado)', 'ssl' => 'SSL', 'starttls' => 'STARTTLS', 'none' => 'Nenhuma'] as $val => $label): ?>
                                <option value="<?= esc($val) ?>" <?= $sec === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="smtp-user">Usuário (e-mail de autenticação)</label>
                        <input type="text" id="smtp-user" class="form-control" name="smtp_user"
                               value="<?= esc(old('smtp_user', $settings['smtp_user'] ?? '')) ?>"
                               placeholder="usuario@dominio.com" autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="smtp-password">Senha</label>
                        <div class="input-group">
                            <input type="password" id="smtp-password" class="form-control" name="smtp_password"
                                   placeholder="<?= ($settings['smtp_password'] ?? '') !== '' ? '••••••••' : 'Senha SMTP' ?>"
                                   autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="smtp-password-toggle" aria-label="Mostrar/ocultar senha">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <span class="sp-help">Deixe em branco para manter a senha atual salva.</span>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="smtp-from-address">Remetente (e-mail) <span class="text-danger">*</span></label>
                        <input type="email" id="smtp-from-address" class="form-control" name="smtp_from_email"
                               value="<?= esc(old('smtp_from_email', $settings['smtp_from_email'] ?? '')) ?>"
                               placeholder="noreply@empresa.com.br" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="smtp-from-name">Remetente (nome) <span class="text-danger">*</span></label>
                        <input type="text" id="smtp-from-name" class="form-control" name="smtp_from_name"
                               value="<?= esc(old('smtp_from_name', $settings['smtp_from_name'] ?? 'SupportPONTO')) ?>"
                               placeholder="SupportPONTO" required>
                    </div>
                </div>

                <div class="d-flex mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar configurações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Testar envio -->
    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-send-check"></i> Testar envio</span>
            <span class="text-muted small">Valida a conexão com os dados preenchidos acima, mesmo sem salvar.</span>
        </div>
        <div class="sp-card-body">
            <div id="smtp-test-result" class="mb-3" style="display:none;"></div>

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold mb-1" for="smtp-test-to">Enviar e-mail de teste para</label>
                    <input type="email" class="form-control" id="smtp-test-to"
                           value="<?= esc($settings['smtp_from_email'] ?? '') ?>"
                           placeholder="seu@email.com">
                    <span class="sp-help">Usa as configurações atualmente preenchidas no formulário acima (mesmo sem salvar).</span>
                </div>
            </div>

            <div class="d-flex align-items-center mt-3">
                <button type="button" class="btn btn-outline-primary" id="smtp-test-btn">
                    <i class="bi bi-send me-1"></i>Testar envio
                </button>
                <span id="smtp-test-spinner" style="display:none;margin-left:12px;color:var(--sp-text-secondary,#5a6472);font-size:.875rem">
                    <span class="spinner-border spinner-border-sm me-1"></span>Enviando...
                </span>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    // Mostrar/ocultar senha
    var pwdInput  = document.getElementById('smtp-password');
    var pwdToggle = document.getElementById('smtp-password-toggle');
    pwdToggle?.addEventListener('click', function () {
        var isPassword = pwdInput.type === 'password';
        pwdInput.type = isPassword ? 'text' : 'password';
        pwdToggle.querySelector('i').className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
    });

    // Testar envio com os valores atuais do formulário (sem exigir salvar antes)
    var testBtn     = document.getElementById('smtp-test-btn');
    var testSpinner = document.getElementById('smtp-test-spinner');
    var testResult  = document.getElementById('smtp-test-result');

    testBtn?.addEventListener('click', async function () {
        var to = (document.getElementById('smtp-test-to').value || '').trim();
        if (! to) { alert('Informe um e-mail de destino.'); return; }

        var payload = {
            host: document.getElementById('smtp-host').value,
            port: document.getElementById('smtp-port').value,
            encryption: document.getElementById('smtp-encryption').value,
            username: document.getElementById('smtp-user').value,
            password: document.getElementById('smtp-password').value,
            from_address: document.getElementById('smtp-from-address').value,
            from_name: document.getElementById('smtp-from-name').value,
            test_email: to,
        };

        testBtn.disabled = true;
        testSpinner.style.display = '';
        testResult.style.display = 'none';

        try {
            // spFetch lê o CSRF do cookie e injeta automaticamente no header e no body
            var r = await spFetch('<?= sp_safe_url(sp_route_url('settings.smtp.test')) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            var j = await r.json();

            testResult.className = j.success ? 'alert alert-success py-2 mb-3' : 'alert alert-danger py-2 mb-3';
            testResult.innerHTML = '<i class="bi bi-' + (j.success ? 'check-circle-fill' : 'x-circle-fill') + ' me-1"></i>'
                + (j.message || (j.success ? 'E-mail enviado com sucesso!' : 'Falha no envio.'));
            testResult.style.display = '';
        } catch (e) {
            testResult.className = 'alert alert-danger py-2 mb-3';
            testResult.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Erro de comunicação com o servidor.';
            testResult.style.display = '';
        } finally {
            testBtn.disabled = false;
            testSpinner.style.display = 'none';
        }
    });
})();
</script>
<?= $this->endSection() ?>
