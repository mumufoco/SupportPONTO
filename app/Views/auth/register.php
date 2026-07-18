<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Solicitar cadastro<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card sp-auth-card--wide">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup">
            <img src="<?= support_logo_url('auth') ?>" alt="Support Solo Sondagens">
            <div class="sp-brand-text">
                <strong>SupportPONTO</strong>
                <span>Support Solo Sondagens</span>
            </div>
        </div>
        <h1 class="h5 mb-1 mt-3">Solicitar cadastro</h1>
        <p class="text-muted mb-0">Preencha seus dados pessoais, profissionais e os consentimentos obrigatórios para enviar seu cadastro para aprovação.</p>
    </div>

    <div class="sp-auth-card-body">
        <?= view('components/flash_messages') ?>

        <?php if (!empty($formOptions['warnings'] ?? [])): ?>
            <div class="alert alert-warning border-0" role="alert">
                <strong>Configuração incompleta.</strong>
                Alguns catálogos obrigatórios ainda não estão disponíveis para cadastro.
                <ul class="mb-0 mt-2 ps-3">
                    <?php foreach (($formOptions['warnings'] ?? []) as $warning): ?>
                        <li><?= esc($warning) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= esc($formAction ?? route_to('register.store')) ?>" method="POST" id="registerForm" class="sp-register-form">
            <?= csrf_field() ?>

            <div class="sp-auth-section">
                <h2 class="sp-auth-section__title">Conta e identificação</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nome completo *</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= esc((string) old('name'), 'attr') ?>" required autofocus>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">E-mail *</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= esc((string) old('email'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="cpf" class="form-label">CPF *</label>
                        <input type="text" class="form-control" id="cpf" name="cpf" value="<?= esc((string) old('cpf'), 'attr') ?>" maxlength="14" required>
                    </div>
                    <div class="col-md-4">
                        <label for="birth_date" class="form-label">Data de nascimento *</label>
                        <input type="date" class="form-control" id="birth_date" name="birth_date" value="<?= esc((string) old('birth_date'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="telefone" class="form-label">Telefone *</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" value="<?= esc((string) old('telefone'), 'attr') ?>" placeholder="(00) 00000-0000" required>
                    </div>
                    <div class="col-md-6">
                        <label for="password" class="form-label">Senha *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">Use pelo menos 12 caracteres com letra maiúscula, minúscula, número e caractere especial.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="password_confirm" class="form-label">Confirmar senha *</label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                    </div>
                </div>
            </div>

            <div class="sp-auth-section">
                <h2 class="sp-auth-section__title">Documentação pessoal</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="rg" class="form-label">RG *</label>
                        <input type="text" class="form-control" id="rg" name="rg" value="<?= esc((string) old('rg'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="rg_orgao_emissor" class="form-label">Órgão emissor *</label>
                        <input type="text" class="form-control" id="rg_orgao_emissor" name="rg_orgao_emissor" value="<?= esc((string) old('rg_orgao_emissor'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="rg_data_expedicao" class="form-label">Data de expedição *</label>
                        <input type="date" class="form-control" id="rg_data_expedicao" name="rg_data_expedicao" value="<?= esc((string) old('rg_data_expedicao'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="estado_civil" class="form-label">Estado civil *</label>
                        <?php $estadoCivil = (string) old('estado_civil'); ?>
                        <select class="form-select" id="estado_civil" name="estado_civil" required>
                            <option value="">Selecione...</option>
                            <?php foreach (['solteiro' => 'Solteiro(a)', 'casado' => 'Casado(a)', 'divorciado' => 'Divorciado(a)', 'viuvo' => 'Viúvo(a)', 'uniao_estavel' => 'União estável'] as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $estadoCivil === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="nacionalidade" class="form-label">Nacionalidade *</label>
                        <input type="text" class="form-control" id="nacionalidade" name="nacionalidade" value="<?= esc((string) old('nacionalidade', 'Brasileira'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="sexo" class="form-label">Sexo *</label>
                        <?php $sexo = (string) old('sexo'); ?>
                        <select class="form-select" id="sexo" name="sexo" required>
                            <option value="">Selecione...</option>
                            <option value="masculino" <?= $sexo === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="feminino" <?= $sexo === 'feminino' ? 'selected' : '' ?>>Feminino</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="cor_raca" class="form-label">Cor/Raça *</label>
                        <?php $corRaca = (string) old('cor_raca'); ?>
                        <select class="form-select" id="cor_raca" name="cor_raca" required>
                            <option value="">Selecione...</option>
                            <?php foreach (['branca' => 'Branca', 'preta' => 'Preta', 'parda' => 'Parda', 'amarela' => 'Amarela', 'indigena' => 'Indígena'] as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $corRaca === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="grau_instrucao" class="form-label">Grau de instrução *</label>
                        <?php $grauInstrucao = (string) old('grau_instrucao'); ?>
                        <select class="form-select" id="grau_instrucao" name="grau_instrucao" required>
                            <option value="">Selecione...</option>
                            <?php foreach (['analfabeto' => 'Analfabeto', 'fundamental_incompleto' => 'Fundamental incompleto', 'fundamental_completo' => 'Fundamental completo', 'medio_incompleto' => 'Médio incompleto', 'medio_completo' => 'Médio completo', 'superior_incompleto' => 'Superior incompleto', 'superior_completo' => 'Superior completo', 'pos_graduacao' => 'Pós-graduação'] as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $grauInstrucao === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="sp-auth-section">
                <h2 class="sp-auth-section__title">Endereço</h2>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="logradouro" class="form-label">Logradouro *</label>
                        <input type="text" class="form-control" id="logradouro" name="logradouro" value="<?= esc((string) old('logradouro'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="numero" class="form-label">Número *</label>
                        <input type="text" class="form-control" id="numero" name="numero" value="<?= esc((string) old('numero'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" value="<?= esc((string) old('complemento'), 'attr') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="bairro" class="form-label">Bairro *</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" value="<?= esc((string) old('bairro'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="municipio" class="form-label">Município *</label>
                        <input type="text" class="form-control" id="municipio" name="municipio" value="<?= esc((string) old('municipio'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="uf" class="form-label">UF *</label>
                        <input type="text" class="form-control" id="uf" name="uf" value="<?= esc((string) old('uf'), 'attr') ?>" maxlength="2" required>
                    </div>
                    <div class="col-md-2">
                        <label for="cep" class="form-label">CEP *</label>
                        <input type="text" class="form-control" id="cep" name="cep" value="<?= esc((string) old('cep'), 'attr') ?>" maxlength="9" required>
                    </div>
                </div>
            </div>

            <div class="sp-auth-section">
                <h2 class="sp-auth-section__title">Dados profissionais</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="ctps_numero" class="form-label">CTPS número *</label>
                        <input type="text" class="form-control" id="ctps_numero" name="ctps_numero" value="<?= esc((string) old('ctps_numero'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ctps_serie" class="form-label">CTPS série *</label>
                        <input type="text" class="form-control" id="ctps_serie" name="ctps_serie" value="<?= esc((string) old('ctps_serie'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ctps_uf" class="form-label">CTPS UF *</label>
                        <input type="text" class="form-control" id="ctps_uf" name="ctps_uf" value="<?= esc((string) old('ctps_uf'), 'attr') ?>" maxlength="2" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ctps_data_emissao" class="form-label">Data de emissão CTPS *</label>
                        <input type="date" class="form-control" id="ctps_data_emissao" name="ctps_data_emissao" value="<?= esc((string) old('ctps_data_emissao'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="pis_pasep" class="form-label">PIS/PASEP *</label>
                        <input type="text" class="form-control" id="pis_pasep" name="pis_pasep" value="<?= esc((string) old('pis_pasep'), 'attr') ?>" maxlength="14" required>
                    </div>
                    <div class="col-md-4">
                        <label for="admission_date" class="form-label">Data de admissão *</label>
                        <input type="date" class="form-control" id="admission_date" name="admission_date" value="<?= esc((string) old('admission_date'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="work_unit" class="form-label">Unidade de trabalho *</label>
                        <select class="form-select" id="work_unit" name="work_unit" required>
                            <option value="">Selecione...</option>
                            <?php foreach (($formOptions['workUnits'] ?? []) as $item): ?>
                                <option value="<?= esc((string) ($item['id'] ?? '')) ?>" <?= (string) old('work_unit') === (string) ($item['id'] ?? '') ? 'selected' : '' ?>><?= esc($item['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="department" class="form-label">Departamento *</label>
                        <select class="form-select" id="department" name="department" required>
                            <option value="">Selecione...</option>
                            <?php foreach (($formOptions['departments'] ?? []) as $item): ?>
                                <option value="<?= esc((string) ($item['id'] ?? '')) ?>" <?= (string) old('department') === (string) ($item['id'] ?? '') ? 'selected' : '' ?>><?= esc($item['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="position" class="form-label">Cargo do catálogo *</label>
                        <select class="form-select" id="position" name="position" required data-old-value="<?= esc((string) old('position'), 'attr') ?>">
                            <option value="">Selecione o departamento primeiro...</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="cargo" class="form-label">Cargo contratual *</label>
                        <input type="text" class="form-control" id="cargo" name="cargo" value="<?= esc((string) old('cargo'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="setor" class="form-label">Setor *</label>
                        <input type="text" class="form-control" id="setor" name="setor" value="<?= esc((string) old('setor'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="tipo_contrato" class="form-label">Tipo de contrato *</label>
                        <?php $tipoContrato = (string) old('tipo_contrato'); ?>
                        <select class="form-select" id="tipo_contrato" name="tipo_contrato" required>
                            <option value="">Selecione...</option>
                            <?php foreach (['CLT' => 'CLT', 'temporario' => 'Temporário', 'estagio' => 'Estágio', 'terceirizado' => 'Terceirizado'] as $value => $label): ?>
                                <option value="<?= esc($value) ?>" <?= $tipoContrato === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="salario_base" class="form-label">Salário base *</label>
                        <input type="number" class="form-control" id="salario_base" name="salario_base" value="<?= esc((string) old('salario_base'), 'attr') ?>" min="0" step="0.01" required>
                    </div>
                    <div class="col-md-3">
                        <label for="jornada_trabalho" class="form-label">Jornada *</label>
                        <input type="text" class="form-control" id="jornada_trabalho" name="jornada_trabalho" value="<?= esc((string) old('jornada_trabalho', '44 horas semanais'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="horario_entrada" class="form-label">Horário de entrada *</label>
                        <input type="time" class="form-control" id="horario_entrada" name="horario_entrada" value="<?= esc((string) old('horario_entrada', '08:00'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="horario_saida" class="form-label">Horário de saída *</label>
                        <input type="time" class="form-control" id="horario_saida" name="horario_saida" value="<?= esc((string) old('horario_saida', '18:00'), 'attr') ?>" required>
                    </div>
                </div>
            </div>

            <div class="sp-auth-section">
                <h2 class="sp-auth-section__title">Dados bancários e adicionais</h2>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="banco" class="form-label">Banco *</label>
                        <input type="text" class="form-control" id="banco" name="banco" value="<?= esc((string) old('banco'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="agencia" class="form-label">Agência *</label>
                        <input type="text" class="form-control" id="agencia" name="agencia" value="<?= esc((string) old('agencia'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="conta" class="form-label">Conta *</label>
                        <input type="text" class="form-control" id="conta" name="conta" value="<?= esc((string) old('conta'), 'attr') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="deficiencia" class="form-label">Pessoa com deficiência</label>
                        <?php $deficiencia = (string) old('deficiencia', 'nao'); ?>
                        <select class="form-select" id="deficiencia" name="deficiencia">
                            <option value="">Selecione...</option>
                            <option value="nao" <?= $deficiencia === 'nao' ? 'selected' : '' ?>>Não</option>
                            <option value="sim" <?= $deficiencia === 'sim' ? 'selected' : '' ?>>Sim</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="allow_remote_punch" name="allow_remote_punch" value="1" <?= old('allow_remote_punch') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="allow_remote_punch">Permitir registro remoto</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="require_geolocation" name="require_geolocation" value="1" <?= old('require_geolocation') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="require_geolocation">Exigir geolocalização</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="sp-auth-section">
                <h2 class="sp-auth-section__title">Consentimentos</h2>
                <div class="d-grid gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="lgpd_consent" name="lgpd_consent" value="1" <?= old('lgpd_consent') ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="lgpd_consent">Autorizo o tratamento dos meus dados pessoais e biométricos para fins de registro de ponto e obrigações trabalhistas. *</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" value="1" <?= old('terms_accepted') ? 'checked' : '' ?> required>
                        <label class="form-check-label" for="terms_accepted">Declaro que as informações informadas são verdadeiras e aceito os termos de uso do sistema. *</label>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center pt-2">
                <div class="small text-muted">Seu cadastro ficará pendente até aprovação administrativa.</div>
                <div class="d-flex gap-2">
                    <a href="<?= sp_safe_url($loginUrl ?? route_to('login')) ?>" class="btn btn-outline-secondary">Voltar ao login</a>
                    <button type="submit" class="btn btn-primary">Enviar cadastro</button>
                </div>
            </div>
        </form>
    </div>

    <div class="sp-auth-card-footer">
        <small>Já possui conta? <a href="<?= sp_safe_url($loginUrl ?? route_to('login')) ?>">Faça login</a> · <a href="<?= sp_safe_url($forgotPasswordUrl ?? route_to('forgot-password')) ?>">Recuperar senha</a></small>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {
    const cpfInput = document.getElementById('cpf');
    const phoneInput = document.getElementById('telefone');
    const cepInput = document.getElementById('cep');
    const pisInput = document.getElementById('pis_pasep');
    const departmentSelect = document.getElementById('department');
    const positionSelect = document.getElementById('position');
    const positionsEndpoint = '<?= esc($positionsEndpoint ?? route_to('register.positions-by-department')) ?>';
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');

    const applyMask = (input, formatter) => {
        if (!input) return;
        input.addEventListener('input', function (event) {
            event.target.value = formatter(event.target.value);
        });
    };

    SupportPontoValidation.bindCpfField(cpfInput);
    SupportPontoValidation.bindEmailFormatField(document.getElementById('email'));

    applyMask(phoneInput, value => {
        value = value.replace(/\D/g, '').slice(0, 11);
        if (value.length <= 10) {
            return value.replace(/(\d{2})(\d{0,4})(\d{0,4})/, (_, ddd, a, b) => {
                let output = ddd ? `(${ddd}` : '';
                if (ddd.length === 2) output += ') ';
                output += a;
                if (b) output += `-${b}`;
                return output;
            });
        }
        return value.replace(/(\d{2})(\d{0,5})(\d{0,4})/, (_, ddd, a, b) => {
            let output = ddd ? `(${ddd}` : '';
            if (ddd.length === 2) output += ') ';
            output += a;
            if (b) output += `-${b}`;
            return output;
        });
    });

    SupportPontoValidation.bindCepField(cepInput, {
        fields: { logradouro: 'logradouro', bairro: 'bairro', municipio: 'municipio', uf: 'uf', numero: 'numero' }
    });

    applyMask(pisInput, value => {
        value = value.replace(/\D/g, '').slice(0, 11);
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{5})(\d)/, '$1.$2');
        return value.replace(/(\d{2})(\d)$/, '$1-$2');
    });

    const populatePositions = (departmentId, selectedValue = '') => {
        if (!departmentId) {
            positionSelect.innerHTML = '<option value="">Selecione o departamento primeiro...</option>';
            return;
        }

        positionSelect.innerHTML = '<option value="">Carregando cargos...</option>';
        spFetch(`${positionsEndpoint}?department_id=${encodeURIComponent(departmentId)}`)
            .then(response => response.ok ? response.json() : Promise.reject(new Error('Falha ao carregar cargos.')))
            .then(data => {
                positionSelect.innerHTML = '<option value="">Selecione...</option>';
                data.forEach(position => {
                    const option = document.createElement('option');
                    option.value = String(position.id ?? '');
                    option.textContent = position.name ?? 'Cargo';
                    if (selectedValue && String(selectedValue) === String(position.id ?? '')) {
                        option.selected = true;
                    }
                    positionSelect.appendChild(option);
                });
            })
            .catch(() => {
                positionSelect.innerHTML = '<option value="">Não foi possível carregar os cargos</option>';
            });
    };

    if (departmentSelect && positionSelect) {
        departmentSelect.addEventListener('change', function () {
            populatePositions(this.value);
        });

        const oldDepartment = departmentSelect.value;
        const oldPosition = positionSelect.dataset.oldValue || '';
        if (oldDepartment) {
            populatePositions(oldDepartment, oldPosition);
        }
    }

    const validatePasswordConfirmation = () => {
        if (!passwordConfirmInput) return;
        passwordConfirmInput.setCustomValidity(passwordInput.value !== passwordConfirmInput.value ? 'As senhas não coincidem.' : '');
    };

    passwordInput?.addEventListener('input', validatePasswordConfirmation);
    passwordConfirmInput?.addEventListener('input', validatePasswordConfirmation);
});
</script>
<?= $this->endSection() ?>
