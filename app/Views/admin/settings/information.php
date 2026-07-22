<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Informações da Empresa<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Informações da Empresa',
        'subtitle' => 'Dados cadastrais, fiscais e operacionais exigidos pelo MTE e demais órgãos.',
        'icon'     => 'bi bi-building-fill',
        'actions'  => [
                                ],
    ]) ?>


    <!-- Logo da empresa -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-image-fill"></i> Logo da empresa</span>
            <span class="text-muted small">JPG, PNG ou WEBP · usada em e-mails, termos e nos demais lugares do sistema.</span>
        </div>
        <div class="sp-card-body">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div id="infoLogoPreviewWrap" style="width:96px;height:96px;display:flex;align-items:center;justify-content:center;background:var(--sp-gray-100,#eef1f4);border-radius:.6rem;overflow:hidden;flex-shrink:0">
                    <img id="infoLogoPreview" src="<?= sp_safe_url(support_logo_url('small')) ?>" alt="Logo da empresa" style="max-width:100%;max-height:100%;object-fit:contain">
                </div>
                <div class="flex-grow-1" style="min-width:220px">
                    <input type="file" class="form-control form-control-sm mb-2" id="infoLogoFile" accept="image/png,image/jpeg,image/webp">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="infoLogoUploadBtn">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar logo
                        </button>
                        <?php if (!empty($settings['logo_path'] ?? '')): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger" id="infoLogoRemoveBtn">
                                <i class="bi bi-trash me-1"></i>Remover
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="form-text mt-1">Também é usada nas telas de Personalização — atualizar aqui atualiza lá também.</div>
                </div>
            </div>
            <div id="infoLogoMsg" class="mt-2"></div>
        </div>
    </div>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.information.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- Identificação -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-building-fill"></i> Identificação</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Razão Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="company_name"
                               value="<?= esc($settings['company_name'] ?? '') ?>"
                               required placeholder="Razão social conforme CNPJ">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nome Fantasia</label>
                        <input type="text" class="form-control" name="company_trade_name"
                               value="<?= esc($settings['company_trade_name'] ?? '') ?>"
                               placeholder="Nome comercial / fantasia">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CNPJ</label>
                        <input type="text" class="form-control" name="company_cnpj"
                               value="<?= esc($settings['company_cnpj'] ?? '') ?>"
                               placeholder="00.000.000/0000-00" maxlength="18">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Inscrição Estadual (IE)</label>
                        <input type="text" class="form-control" name="company_ie"
                               value="<?= esc($settings['company_ie'] ?? '') ?>"
                               placeholder="Isento ou número">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CEI / CAEPF</label>
                        <input type="text" class="form-control" name="company_cei"
                               value="<?= esc($settings['company_cei'] ?? '') ?>"
                               placeholder="Cadastro Específico do INSS">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Inscrição Municipal</label>
                        <input type="text" class="form-control" name="company_municipal_registration"
                               value="<?= esc($settings['company_municipal_registration'] ?? '') ?>"
                               placeholder="Número da inscrição municipal">
                    </div>
                </div>
            </div>
        </div>

        <!-- Contato -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-telephone-fill"></i> Contato</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Telefone</label>
                        <input type="text" class="form-control" name="company_phone"
                               value="<?= esc($settings['company_phone'] ?? '') ?>"
                               placeholder="(00) 0000-0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">WhatsApp</label>
                        <input type="text" class="form-control" name="company_whatsapp"
                               value="<?= esc($settings['company_whatsapp'] ?? '') ?>"
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">E-mail corporativo</label>
                        <input type="email" class="form-control" id="company_email" name="company_email"
                               value="<?= esc($settings['company_email'] ?? '') ?>"
                               placeholder="contato@empresa.com.br">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Site</label>
                        <input type="text" class="form-control" name="company_website"
                               value="<?= esc($settings['company_website'] ?? '') ?>"
                               placeholder="https://www.empresa.com.br">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Código interno</label>
                        <input type="text" class="form-control" name="company_code"
                               value="<?= esc($settings['company_code'] ?? '') ?>"
                               placeholder="Identificador para relatórios">
                    </div>
                </div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-geo-alt-fill"></i> Endereço</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">CEP</label>
                        <input type="text" class="form-control" id="company_cep" name="company_cep"
                               value="<?= esc($settings['company_cep'] ?? '') ?>"
                               placeholder="00000-000" maxlength="9">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Logradouro, número e complemento</label>
                        <input type="text" class="form-control" id="company_address" name="company_address"
                               value="<?= esc($settings['company_address'] ?? '') ?>"
                               placeholder="Rua, número, complemento, bairro">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cidade</label>
                        <input type="text" class="form-control" id="company_city" name="company_city"
                               value="<?= esc($settings['company_city'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-semibold">UF</label>
                        <input type="text" class="form-control" id="company_state" name="company_state"
                               value="<?= esc($settings['company_state'] ?? '') ?>"
                               maxlength="2" placeholder="SP">
                    </div>
                </div>
            </div>
        </div>

        <!-- Responsável Legal -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-badge-fill"></i> Responsável Legal</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nome</label>
                        <input type="text" class="form-control" name="legal_rep_name"
                               value="<?= esc($settings['legal_rep_name'] ?? '') ?>"
                               placeholder="Nome completo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cargo</label>
                        <input type="text" class="form-control" name="legal_rep_position"
                               value="<?= esc($settings['legal_rep_position'] ?? '') ?>"
                               placeholder="Ex.: Sócio-administrador">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CPF</label>
                        <input type="text" class="form-control" id="legal_rep_cpf" name="legal_rep_cpf"
                               value="<?= esc($settings['legal_rep_cpf'] ?? '') ?>"
                               placeholder="000.000.000-00" maxlength="14">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Telefone</label>
                        <input type="text" class="form-control" name="legal_rep_phone"
                               value="<?= esc($settings['legal_rep_phone'] ?? '') ?>"
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">E-mail</label>
                        <input type="email" class="form-control" name="legal_rep_email"
                               value="<?= esc($settings['legal_rep_email'] ?? '') ?>"
                               placeholder="responsavel@empresa.com.br">
                    </div>
                </div>
            </div>
        </div>

        <!-- Responsável Técnico -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-gear"></i> Responsável Técnico</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nome</label>
                        <input type="text" class="form-control" name="tech_rep_name"
                               value="<?= esc($settings['tech_rep_name'] ?? '') ?>"
                               placeholder="Nome completo">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Cargo</label>
                        <input type="text" class="form-control" name="tech_rep_position"
                               value="<?= esc($settings['tech_rep_position'] ?? '') ?>"
                               placeholder="Ex.: Engenheiro de Segurança do Trabalho">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CREA</label>
                        <input type="text" class="form-control" name="tech_rep_crea"
                               value="<?= esc($settings['tech_rep_crea'] ?? '') ?>"
                               placeholder="Número do registro CREA">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">CPF</label>
                        <input type="text" class="form-control" id="tech_rep_cpf" name="tech_rep_cpf"
                               value="<?= esc($settings['tech_rep_cpf'] ?? '') ?>"
                               placeholder="000.000.000-00" maxlength="14">
                    </div>
                </div>
            </div>
        </div>

        <!-- Regional e Operacional -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-globe2"></i> Regional e Operacional</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Fuso horário <span class="text-danger">*</span></label>
                        <select class="form-select" name="timezone" required>
                            <optgroup label="Brasil">
                                <?php
                                $timezones = [
                                    'America/Sao_Paulo'   => 'São Paulo / Brasília (UTC-3)',
                                    'America/Manaus'      => 'Manaus (UTC-4)',
                                    'America/Belem'       => 'Belém (UTC-3)',
                                    'America/Fortaleza'   => 'Fortaleza (UTC-3)',
                                    'America/Recife'      => 'Recife (UTC-3)',
                                    'America/Porto_Velho' => 'Porto Velho (UTC-4)',
                                    'America/Boa_Vista'   => 'Boa Vista (UTC-4)',
                                    'America/Rio_Branco'  => 'Rio Branco (UTC-5)',
                                    'America/Noronha'     => 'Fernando de Noronha (UTC-2)',
                                ];
                                $curTz = $settings['timezone'] ?? 'America/Sao_Paulo';
                                foreach ($timezones as $tz => $lbl): ?>
                                    <option value="<?= esc($tz) ?>" <?= $curTz === $tz ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Formato de data</label>
                        <select class="form-select" name="date_format">
                            <?php
                            $curDf = $settings['date_format'] ?? 'd/m/Y';
                            foreach (['d/m/Y' => 'DD/MM/AAAA', 'Y-m-d' => 'AAAA-MM-DD', 'm/d/Y' => 'MM/DD/AAAA'] as $k => $l): ?>
                                <option value="<?= esc($k) ?>" <?= $curDf === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Formato de hora</label>
                        <select class="form-select" name="time_format">
                            <option value="24h" <?= ($settings['time_format'] ?? '24h') === '24h' ? 'selected' : '' ?>>24h (14:30)</option>
                            <option value="12h" <?= ($settings['time_format'] ?? '') === '12h'  ? 'selected' : '' ?>>12h (2:30 PM)</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Idioma padrão</label>
                        <select class="form-select" name="default_language">
                            <?php
                            $langs = ['pt_BR' => 'Português (Brasil)', 'en_US' => 'English (US)', 'es_ES' => 'Español'];
                            $curL = $settings['default_language'] ?? 'pt_BR';
                            foreach ($langs as $k => $l): ?>
                                <option value="<?= esc($k) ?>" <?= $curL === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Moeda</label>
                        <input type="text" class="form-control" name="currency"
                               value="<?= esc($settings['currency'] ?? 'BRL') ?>"
                               placeholder="BRL" maxlength="3">
                        <div class="form-text">Código ISO 4217</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-card mt-4">
            <div class="sp-card-body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(sp_admin_settings_index_url()) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar Informações
                </button>
            </div>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
SupportPontoValidation.bindEmailFormatField(document.getElementById('company_email'));
SupportPontoValidation.bindCpfField(document.getElementById('legal_rep_cpf'));
SupportPontoValidation.bindCpfField(document.getElementById('tech_rep_cpf'));
SupportPontoValidation.bindCepField(document.getElementById('company_cep'), {
    fields: {
        logradouroCombined: 'company_address',
        municipio: 'company_city',
        uf: 'company_state'
    }
});

/* Logo da empresa -- mesmo endpoint já usado em Personalização
   (admin.settings.personalization.upload-logo), então as duas telas ficam
   sempre em sincronia (mesma configuração company_logo). */
(function () {
    var fileInput = document.getElementById('infoLogoFile');
    var uploadBtn = document.getElementById('infoLogoUploadBtn');
    var removeBtn = document.getElementById('infoLogoRemoveBtn');
    var preview   = document.getElementById('infoLogoPreview');
    var msgEl     = document.getElementById('infoLogoMsg');

    function showMsg(text, ok) {
        msgEl.innerHTML = '<div class="alert alert-' + (ok === false ? 'danger' : 'success') + ' py-2 mb-0">' + text + '</div>';
    }

    uploadBtn?.addEventListener('click', async function () {
        if (!fileInput.files[0]) { showMsg('Selecione um arquivo primeiro.', false); return; }
        var fd = new FormData();
        fd.append('logo', fileInput.files[0]);
        uploadBtn.disabled = true;
        try {
            var r = await spFetch('<?= esc(sp_route_url('admin.settings.personalization.upload-logo')) ?>', { method: 'POST', body: fd });
            var j = await r.json();
            showMsg(j.message || (j.success ? 'Logo enviada!' : 'Erro ao enviar.'), j.success);
            if (j.success && j.url) {
                preview.src = j.url + '?t=' + Date.now();
            }
        } catch (e) {
            showMsg('Erro de comunicação com o servidor.', false);
        }
        uploadBtn.disabled = false;
    });

    removeBtn?.addEventListener('click', async function () {
        if (!confirm('Remover a logo da empresa?')) return;
        removeBtn.disabled = true;
        try {
            var r = await spFetch('<?= esc(sp_route_url('admin.settings.personalization.images.delete', 'logo')) ?>', { method: 'POST' });
            var j = await r.json();
            showMsg(j.message || (j.success ? 'Logo removida!' : 'Erro ao remover.'), j.success);
            if (j.success) {
                location.reload();
            }
        } catch (e) {
            showMsg('Erro de comunicação com o servidor.', false);
        }
        removeBtn.disabled = false;
    });
})();
</script>
<?= $this->endSection() ?>
