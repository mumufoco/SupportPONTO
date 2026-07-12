<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Sistema<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Configurações do Sistema',
        'subtitle' => 'Dados corporativos, configurações regionais e preferências operacionais.',
        'icon'     => 'bi bi-cpu-fill',
        'actions'  => [
            ['label' => 'Segurança',    'icon' => 'bi bi-shield-lock-fill', 'url' => route_to('admin.settings.security')],
            ['label' => 'Autenticação', 'icon' => 'bi bi-key-fill',         'url' => route_to('admin.settings.authentication')],
        ],
    ]) ?>


    <form action="<?= sp_safe_url(sp_route_url('admin.settings.system.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- 1. Dados da Empresa -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-building-fill"></i> Informações da Empresa</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="company_cnpj" class="form-label fw-semibold">CNPJ</label>
                        <input type="text" class="form-control" id="company_cnpj" name="company_cnpj"
                               value="<?= esc($settings['company_cnpj'] ?? '') ?>"
                               placeholder="00.000.000/0000-00" maxlength="18">
                    </div>
                    <div class="col-md-4">
                        <label for="company_phone" class="form-label fw-semibold">Telefone</label>
                        <input type="text" class="form-control" id="company_phone" name="company_phone"
                               value="<?= esc($settings['company_phone'] ?? '') ?>"
                               placeholder="(00) 0000-0000">
                    </div>
                    <div class="col-md-4">
                        <label for="company_email" class="form-label fw-semibold">E-mail corporativo</label>
                        <input type="email" class="form-control" id="company_email" name="company_email"
                               value="<?= esc($settings['company_email'] ?? '') ?>"
                               placeholder="contato@empresa.com.br">
                    </div>
                    <div class="col-12">
                        <label for="company_address" class="form-label fw-semibold">Endereço completo</label>
                        <textarea class="form-control" id="company_address" name="company_address" rows="2"
                                  placeholder="Rua, número, complemento, bairro, cidade - UF, CEP"><?= esc($settings['company_address'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Configurações Regionais -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-globe2"></i> Configurações Regionais</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="timezone" class="form-label fw-semibold">Fuso horário <span class="text-danger">*</span></label>
                        <select class="form-select" id="timezone" name="timezone" required>
                            <optgroup label="Brasil">
                                <?php
                                $timezones = [
                                    'America/Sao_Paulo' => 'São Paulo / Brasília (UTC-3)',
                                    'America/Manaus'    => 'Manaus (UTC-4)',
                                    'America/Belem'     => 'Belém (UTC-3)',
                                    'America/Fortaleza' => 'Fortaleza (UTC-3)',
                                    'America/Recife'    => 'Recife (UTC-3)',
                                    'America/Porto_Velho' => 'Porto Velho (UTC-4)',
                                    'America/Boa_Vista' => 'Boa Vista (UTC-4)',
                                    'America/Rio_Branco'=> 'Rio Branco (UTC-5)',
                                    'America/Noronha'   => 'Fernando de Noronha (UTC-2)',
                                ];
                                $currentTz = $settings['timezone'] ?? 'America/Sao_Paulo';
                                foreach ($timezones as $tz => $label):
                                ?>
                                    <option value="<?= esc($tz) ?>" <?= $currentTz === $tz ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_format" class="form-label fw-semibold">Formato de data</label>
                        <select class="form-select" id="date_format" name="date_format">
                            <?php
                            $dateFormats = ['d/m/Y' => 'DD/MM/AAAA', 'Y-m-d' => 'AAAA-MM-DD', 'm/d/Y' => 'MM/DD/AAAA'];
                            $currentDf = $settings['date_format'] ?? 'd/m/Y';
                            foreach ($dateFormats as $k => $l):
                            ?>
                                <option value="<?= esc($k) ?>" <?= $currentDf === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="time_format" class="form-label fw-semibold">Formato de hora</label>
                        <select class="form-select" id="time_format" name="time_format">
                            <option value="24h" <?= ($settings['time_format'] ?? '24h') === '24h' ? 'selected' : '' ?>>24 horas (14:30)</option>
                            <option value="12h" <?= ($settings['time_format'] ?? '') === '12h'  ? 'selected' : '' ?>>12 horas (2:30 PM)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Preferências Operacionais -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-sliders"></i> Preferências Operacionais</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="company_code" class="form-label fw-semibold">Código da empresa</label>
                        <input type="text" class="form-control" id="company_code" name="company_code"
                               value="<?= esc($settings['company_code'] ?? '') ?>">
                        <div class="form-text">Identificador interno para relatórios</div>
                    </div>
                    <div class="col-md-4">
                        <label for="default_language" class="form-label fw-semibold">Idioma padrão</label>
                        <select class="form-select" id="default_language" name="default_language">
                            <option value="pt_BR" <?= ($settings['default_language'] ?? 'pt_BR') === 'pt_BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                            <option value="en_US" <?= ($settings['default_language'] ?? '') === 'en_US' ? 'selected' : '' ?>>English (US)</option>
                            <option value="es_ES" <?= ($settings['default_language'] ?? '') === 'es_ES' ? 'selected' : '' ?>>Español</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="currency" class="form-label fw-semibold">Moeda</label>
                        <input type="text" class="form-control" id="currency" name="currency"
                               value="<?= esc($settings['currency'] ?? 'BRL') ?>"
                               placeholder="BRL">
                        <div class="form-text">Código ISO 4217 (ex: BRL, USD, EUR)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões — bem separados do conteúdo -->
        <div class="sp-card mt-4">
            <div class="sp-card-body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(sp_settings_center_url()) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar configurações
                </button>
            </div>
        </div>

    </form>
</div>
<?= $this->endSection() ?>
