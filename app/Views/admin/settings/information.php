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

    <?= view('components/flash_messages') ?>

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
                        <label class="form-label fw-semibold">E-mail corporativo</label>
                        <input type="email" class="form-control" name="company_email"
                               value="<?= esc($settings['company_email'] ?? '') ?>"
                               placeholder="contato@empresa.com.br">
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
                        <input type="text" class="form-control" name="company_cep"
                               value="<?= esc($settings['company_cep'] ?? '') ?>"
                               placeholder="00000-000" maxlength="9">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Logradouro, número e complemento</label>
                        <input type="text" class="form-control" name="company_address"
                               value="<?= esc($settings['company_address'] ?? '') ?>"
                               placeholder="Rua, número, complemento, bairro">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cidade</label>
                        <input type="text" class="form-control" name="company_city"
                               value="<?= esc($settings['company_city'] ?? '') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label fw-semibold">UF</label>
                        <input type="text" class="form-control" name="company_state"
                               value="<?= esc($settings['company_state'] ?? '') ?>"
                               maxlength="2" placeholder="SP">
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
