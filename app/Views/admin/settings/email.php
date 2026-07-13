<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>E-mail<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'E-mail',
        'subtitle' => 'Configurações de servidor SMTP e remetente.',
        'icon'     => 'bi bi-envelope-fill',
        'actions'  => [
            ['label' => 'Templates de e-mail', 'icon' => 'bi bi-file-earmark-text', 'url' => sp_route_url('admin.settings.email-templates')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.email.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- SMTP -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-server"></i> Servidor SMTP</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Host SMTP</label>
                        <input type="text" class="form-control" name="smtp_host"
                               value="<?= esc($settings['smtp_host'] ?? '') ?>"
                               placeholder="smtp.gmail.com">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Porta</label>
                        <input type="number" class="form-control" name="smtp_port"
                               value="<?= esc($settings['smtp_port'] ?? '587') ?>"
                               placeholder="587">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Segurança</label>
                        <select class="form-select" name="smtp_secure">
                            <?php $sec = $settings['smtp_secure'] ?? 'tls'; ?>
                            <option value="tls"  <?= $sec === 'tls'  ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl"  <?= $sec === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $sec === 'none' ? 'selected' : '' ?>>Nenhuma</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Usuário SMTP</label>
                        <input type="text" class="form-control" name="smtp_user"
                               value="<?= esc($settings['smtp_user'] ?? '') ?>"
                               placeholder="usuario@dominio.com">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Senha SMTP</label>
                        <input type="password" class="form-control" name="smtp_password"
                               placeholder="Deixe em branco para manter a atual"
                               autocomplete="new-password">
                        <div class="form-text">Não é exibida por segurança. Preencha apenas para alterar.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remetente -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-fill"></i> Remetente</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">E-mail do remetente</label>
                        <input type="email" class="form-control" name="smtp_from_email"
                               value="<?= esc($settings['smtp_from_email'] ?? '') ?>"
                               placeholder="noreply@empresa.com.br">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nome do remetente</label>
                        <input type="text" class="form-control" name="smtp_from_name"
                               value="<?= esc($settings['smtp_from_name'] ?? '') ?>"
                               placeholder="SupportPONTO">
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-card mt-4">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-send-check"></i> Testar conexão SMTP</span>
            </div>
            <div class="sp-card-body">
                <p class="text-muted small mb-3">Salve as configurações antes de testar. O e-mail será enviado para o endereço abaixo.</p>
                <div class="d-flex gap-2 align-items-end flex-wrap">
                    <div style="flex:1;min-width:220px;">
                        <label class="form-label fw-semibold mb-1">Destinatário do teste</label>
                        <input type="email" class="form-control" id="test-smtp-email"
                               value="<?= esc($settings['smtp_from_email'] ?? '') ?>"
                               placeholder="seu@email.com">
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="btn-test-smtp" style="white-space:nowrap;">
                        <i class="bi bi-send me-1"></i> Enviar e-mail de teste
                    </button>
                </div>
                <div id="smtp-test-result" class="mt-2" style="display:none;"></div>
            </div>
        </div>

        <div class="sp-card mt-3">
            <div class="sp-card-body d-flex gap-2 justify-content-end align-items-center">
                <a href="<?= sp_safe_url(sp_admin_settings_index_url()) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar E-mail
                </button>
            </div>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
document.getElementById('btn-test-smtp')?.addEventListener('click', async function() {
    const btn      = this;
    const resultEl = document.getElementById('smtp-test-result');
    const toEmail  = (document.getElementById('test-smtp-email')?.value || '').trim();

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Enviando...';
    resultEl.style.display = 'none';
    resultEl.innerHTML = '';

    try {
        const body = new FormData();
        if (toEmail) body.append('test_email', toEmail);

        // spFetch lê o CSRF do cookie e injeta automaticamente no header e no body
        const r = await spFetch('<?= sp_safe_url(sp_route_url('settings.smtp.test')) ?>', {
            method: 'POST',
            body,
        });
        const j = await r.json();

        resultEl.className = j.success
            ? 'alert alert-success py-2 mt-2'
            : 'alert alert-danger py-2 mt-2';
        resultEl.innerHTML = '<i class="bi bi-'
            + (j.success ? 'check-circle-fill' : 'x-circle-fill')
            + ' me-1"></i>'
            + (j.message || (j.success ? 'E-mail enviado com sucesso!' : 'Falha no envio.'));
        resultEl.style.display = '';
    } catch (e) {
        resultEl.className = 'alert alert-danger py-2 mt-2';
        resultEl.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Erro de comunicação com o servidor.';
        resultEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send me-1"></i> Enviar e-mail de teste';
    }
});
</script>
<?= $this->endSection() ?>
