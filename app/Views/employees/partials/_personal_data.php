<?php
$employee = $employee ?? null;
$value = static fn(string $field, $default = '') => old($field, $employee->{$field} ?? $default);
$states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>
<style>
.sp-field-ok  .form-control, .sp-field-ok  .form-select { border-color:#198754!important }
.sp-field-ok  .sp-field-msg { color:#198754 }
.sp-field-err .form-control, .sp-field-err .form-select { border-color:#dc3545!important }
.sp-field-err .sp-field-msg { color:#dc3545 }
.sp-field-msg { font-size:.75rem; margin-top:.2rem; min-height:1rem }
#cepSpinner   { display:none }
</style>

<!-- Identificação -->
<div class="row g-3">

    <div class="col-12 col-md-8">
        <label for="name" class="form-label">Nome completo *</label>
        <input type="text" id="name" name="name" class="form-control"
               value="<?= esc($value('name')) ?>" required maxlength="255" autocomplete="name">
    </div>
    <div class="col-12 col-md-4">
        <label for="birth_date" class="form-label">Data de nascimento *</label>
        <input type="date" id="birth_date" name="birth_date" class="form-control"
               value="<?= esc($value('birth_date')) ?>" required>
    </div>

    <div class="col-12 col-md-5" id="wrap-email">
        <label for="email" class="form-label">E-mail *</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= esc($value('email')) ?>" required maxlength="255" autocomplete="email">
        <div class="sp-field-msg" id="msg-email"></div>
    </div>
    <div class="col-6 col-md-3" id="wrap-telefone">
        <label for="telefone" class="form-label">Telefone *</label>
        <input type="text" id="telefone" name="telefone" class="form-control"
               value="<?= esc(old('telefone', old('phone', $employee->telefone ?? $employee->phone ?? ''))) ?>"
               required placeholder="(00) 9 0000-0000" maxlength="16" autocomplete="tel">
        <div class="sp-field-msg" id="msg-telefone"></div>
        <input type="hidden" name="phone" id="phone">
    </div>
    <div class="col-6 col-md-4">
        <label for="estado_civil" class="form-label">Estado civil *</label>
        <select id="estado_civil" name="estado_civil" class="form-select" required>
            <?php foreach (['' => 'Selecione...', 'solteiro' => 'Solteiro(a)', 'casado' => 'Casado(a)', 'divorciado' => 'Divorciado(a)', 'viuvo' => 'Viúvo(a)', 'uniao_estavel' => 'União estável'] as $k => $l): ?>
                <option value="<?= esc($k) ?>" <?= (string)$value('estado_civil') === (string)$k ? 'selected' : '' ?>><?= esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- RG -->
    <div class="col-6 col-md-3" id="wrap-cpf">
        <label for="cpf" class="form-label">CPF *</label>
        <input type="text" id="cpf" name="cpf" class="form-control"
               value="<?= esc($value('cpf')) ?>" required inputmode="numeric"
               placeholder="000.000.000-00" maxlength="14" autocomplete="off">
        <div class="sp-field-msg" id="msg-cpf"></div>
    </div>
    <div class="col-6 col-md-3">
        <label for="rg" class="form-label">RG *</label>
        <input type="text" id="rg" name="rg" class="form-control"
               value="<?= esc($value('rg')) ?>" required maxlength="20">
    </div>
    <div class="col-5 col-md-2">
        <label for="rg_orgao_emissor" class="form-label">Órgão emissor *</label>
        <input type="text" id="rg_orgao_emissor" name="rg_orgao_emissor" class="form-control"
               value="<?= esc($value('rg_orgao_emissor')) ?>" required maxlength="10">
    </div>
    <div class="col-7 col-md-4">
        <label for="rg_data_expedicao" class="form-label">Data de expedição *</label>
        <input type="date" id="rg_data_expedicao" name="rg_data_expedicao" class="form-control"
               value="<?= esc($value('rg_data_expedicao')) ?>" required>
    </div>

    <!-- Complementares -->
    <div class="col-6 col-md-3">
        <label for="nacionalidade" class="form-label">Nacionalidade *</label>
        <input type="text" id="nacionalidade" name="nacionalidade" class="form-control"
               value="<?= esc($value('nacionalidade', 'Brasileira')) ?>" required maxlength="50">
    </div>
    <div class="col-6 col-md-2">
        <label for="sexo" class="form-label">Sexo *</label>
        <select id="sexo" name="sexo" class="form-select" required>
            <?php foreach (['' => 'Selecione...', 'masculino' => 'Masculino', 'feminino' => 'Feminino'] as $k => $l): ?>
                <option value="<?= esc($k) ?>" <?= (string)$value('sexo') === (string)$k ? 'selected' : '' ?>><?= esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label for="cor_raca" class="form-label">Cor/Raça *</label>
        <select id="cor_raca" name="cor_raca" class="form-select" required>
            <?php foreach (['' => 'Selecione...', 'branca' => 'Branca', 'preta' => 'Preta', 'parda' => 'Parda', 'amarela' => 'Amarela', 'indigena' => 'Indígena'] as $k => $l): ?>
                <option value="<?= esc($k) ?>" <?= (string)$value('cor_raca') === (string)$k ? 'selected' : '' ?>><?= esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label for="grau_instrucao" class="form-label">Grau de instrução *</label>
        <select id="grau_instrucao" name="grau_instrucao" class="form-select" required>
            <?php foreach (['' => 'Selecione...', 'analfabeto' => 'Analfabeto', 'fundamental_incompleto' => 'Fund. incompleto', 'fundamental_completo' => 'Fund. completo', 'medio_incompleto' => 'Médio incompleto', 'medio_completo' => 'Médio completo', 'superior_incompleto' => 'Superior incompleto', 'superior_completo' => 'Superior completo', 'pos_graduacao' => 'Pós-graduação'] as $k => $l): ?>
                <option value="<?= esc($k) ?>" <?= (string)$value('grau_instrucao') === (string)$k ? 'selected' : '' ?>><?= esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-1">
        <label for="deficiencia" class="form-label">PCD?</label>
        <select id="deficiencia" name="deficiencia" class="form-select">
            <?php foreach (['' => '—', 'nao' => 'Não', 'sim' => 'Sim'] as $k => $l): ?>
                <option value="<?= esc($k) ?>" <?= (string)$value('deficiencia') === (string)$k ? 'selected' : '' ?>><?= esc($l) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

</div><!-- /row identificação -->

<!-- Endereço -->
<div class="sp-form-divider"><i class="bi bi-geo-alt-fill me-1"></i>Endereço</div>

<div class="row g-3">

    <div class="col-6 col-md-2" id="wrap-cep">
        <label for="cep" class="form-label">
            CEP *
            <span id="cepSpinner" class="spinner-border spinner-border-sm text-primary ms-1" role="status"></span>
        </label>
        <input type="text" id="cep" name="cep" class="form-control"
               value="<?= esc($value('cep')) ?>" required inputmode="numeric"
               placeholder="00000-000" maxlength="9">
        <div class="sp-field-msg" id="msg-cep"></div>
    </div>
    <div class="col-12 col-md-7">
        <label for="logradouro" class="form-label">Logradouro *</label>
        <input type="text" id="logradouro" name="logradouro" class="form-control"
               value="<?= esc($value('logradouro')) ?>" required maxlength="255">
    </div>
    <div class="col-4 col-md-1">
        <label for="numero" class="form-label">Nº *</label>
        <input type="text" id="numero" name="numero" class="form-control"
               value="<?= esc($value('numero')) ?>" required maxlength="10">
    </div>
    <div class="col-8 col-md-2">
        <label for="complemento" class="form-label">Complemento</label>
        <input type="text" id="complemento" name="complemento" class="form-control"
               value="<?= esc($value('complemento')) ?>" maxlength="100">
    </div>

    <div class="col-6 col-md-3">
        <label for="bairro" class="form-label">Bairro *</label>
        <input type="text" id="bairro" name="bairro" class="form-control"
               value="<?= esc($value('bairro')) ?>" required maxlength="100">
    </div>
    <div class="col-6 col-md-5">
        <label for="municipio" class="form-label">Município *</label>
        <input type="text" id="municipio" name="municipio" class="form-control"
               value="<?= esc($value('municipio')) ?>" required maxlength="100">
    </div>
    <div class="col-4 col-md-2">
        <label for="uf" class="form-label">UF *</label>
        <select id="uf" name="uf" class="form-select" required>
            <option value="">—</option>
            <?php foreach ($states as $st): ?>
                <option value="<?= esc($st) ?>" <?= (string)$value('uf') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

</div><!-- /row endereço -->

<script <?= csp_script_nonce_attr() ?>>
(function () {
    function setStatus(wrapId, msgId, ok, text) {
        var w = document.getElementById(wrapId), m = document.getElementById(msgId);
        if (w) { w.classList.toggle('sp-field-ok', ok === true); w.classList.toggle('sp-field-err', ok === false); }
        if (m) m.textContent = text || '';
    }
    /* CPF */
    SupportPontoValidation.bindCpfField(document.getElementById('cpf'), { wrapId: 'wrap-cpf', msgId: 'msg-cpf' });
    /* Telefone */
    function phoneMask(v){v=v.replace(/\D/g,'').slice(0,11);if(!v.length)return'';if(v.length<=2)return'('+v;if(v.length<=3)return'('+v.slice(0,2)+') '+v.slice(2);if(v.length<=7)return'('+v.slice(0,2)+') '+v.slice(2,3)+' '+v.slice(3);return'('+v.slice(0,2)+') '+v.slice(2,3)+' '+v.slice(3,7)+'-'+v.slice(7);}
    var phoneEl=document.getElementById('telefone'),phoneHid=document.getElementById('phone');
    if(phoneEl){phoneEl.addEventListener('input',function(){this.value=phoneMask(this.value);var raw=this.value.replace(/\D/g,'');if(phoneHid)phoneHid.value=raw;if(raw.length===11)setStatus('wrap-telefone','msg-telefone',true,'✓ Formato válido');else if(raw.length>0)setStatus('wrap-telefone','msg-telefone',false,'✗ Use (DDD) 9 XXXX-XXXX');else setStatus('wrap-telefone','msg-telefone',null,'');});phoneEl.dispatchEvent(new Event('input'));}
    /* E-mail */
    SupportPontoValidation.bindEmailFormatField(document.getElementById('email'), { wrapId: 'wrap-email', msgId: 'msg-email' });
    /* CEP */
    SupportPontoValidation.bindCepField(document.getElementById('cep'), {
        wrapId: 'wrap-cep',
        msgId: 'msg-cep',
        spinnerId: 'cepSpinner',
        fields: { logradouro: 'logradouro', bairro: 'bairro', municipio: 'municipio', uf: 'uf', numero: 'numero' }
    });
})();
</script>
