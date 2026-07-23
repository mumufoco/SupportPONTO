<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Cadastro — SupportPONTO<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$invite = $invite ?? null;
$formOptions = $formOptions ?? [];
$token  = $token ?? '';
$errors = $errors ?? [];
$states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>
<style>
.inv-header{background:var(--sp-primary-dark);color:#fff;padding:1.25rem 2rem;border-radius:8px 8px 0 0;margin-bottom:0}
.inv-body{background:var(--sp-bg-surface);border:1px solid var(--sp-border);border-top:none;border-radius:0 0 8px 8px;padding:2rem}
.inv-section{border-top:1px solid var(--sp-border);margin-top:1.5rem;padding-top:1.5rem}
.inv-section h3{font-size:.85rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--sp-text-muted);margin-bottom:1rem}
.sp-field-msg{font-size:.75rem;margin-top:.2rem;min-height:1rem}
.sp-field-ok .form-control{border-color:#198754!important}
.sp-field-ok .sp-field-msg{color:#198754}
.sp-field-err .form-control{border-color:#dc3545!important}
.sp-field-err .sp-field-msg{color:#dc3545}
</style>

<div style="max-width:680px;margin:2rem auto;padding:0 1rem">
    <div class="inv-header">
        <div class="d-flex align-items-center gap-3">
            <i class="bi bi-person-plus-fill" style="font-size:1.8rem"></i>
            <div>
                <h1 style="font-size:1.2rem;font-weight:700;margin:0">Formulário de Auto-Cadastro</h1>
                <p style="margin:0;font-size:.83rem;opacity:.8">SupportPONTO — Controle de Ponto Eletrônico</p>
            </div>
        </div>
    </div>
    <div class="inv-body">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($error) ?></div>
        <?php endif; ?>

        <p class="text-muted mb-0" style="font-size:.88rem">
            <?php if (!empty($invite->message)): ?>
                <em><?= esc($invite->message) ?></em><br><br>
            <?php endif; ?>
            Preencha todos os campos obrigatórios (*). Após o envio, seu cadastro será analisado pelo RH ou gestor.
        </p>

        <form action="<?= site_url('convite/' . $token) ?>" method="post" novalidate>
            <?= csrf_field() ?>

            <!-- Acesso -->
            <div class="inv-section">
                <h3><i class="bi bi-person-lock me-1"></i>Dados de acesso</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">E-mail (não editável)</label>
                        <input type="email" class="form-control bg-light" value="<?= esc($invite->email ?? '') ?>" readonly>
                        <input type="hidden" name="email" value="<?= esc($invite->email ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="ir_pwd" class="form-label">Senha *</label>
                        <div class="input-group">
                            <input type="password" id="ir_pwd" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary" id="ir_togglePwd"><i class="bi bi-eye"></i></button>
                        </div>
                        <div id="ir_pwdBar" style="height:4px;border-radius:2px;background:var(--sp-gray-200);margin-top:4px;overflow:hidden"><div id="ir_pwdFill" style="height:100%;width:0;transition:width .3s,background .3s"></div></div>
                        <div id="ir_pwdMsg" class="form-text" style="min-height:1rem"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="ir_pwd2" class="form-label">Confirmar senha *</label>
                        <input type="password" id="ir_pwd2" class="form-control" required minlength="8" autocomplete="new-password">
                        <div id="ir_pwd2Msg" class="sp-field-msg"></div>
                    </div>
                </div>
            </div>

            <!-- Dados pessoais -->
            <div class="inv-section">
                <h3><i class="bi bi-person-vcard me-1"></i>Dados pessoais</h3>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="ir_name">Nome completo *</label>
                        <input type="text" id="ir_name" name="name" class="form-control" value="<?= esc(old('name', $invite->name ?? '')) ?>" required maxlength="255">
                    </div>
                    <div class="col-md-6" id="ir_cpfWrap">
                        <label class="form-label" for="ir_cpf">CPF *</label>
                        <input type="text" id="ir_cpf" name="cpf" class="form-control" value="<?= esc(old('cpf')) ?>" required placeholder="000.000.000-00" maxlength="14" inputmode="numeric">
                        <div class="sp-field-msg" id="ir_cpfMsg"></div>
                    </div>
                    <div class="col-md-6" id="ir_phoneWrap">
                        <label class="form-label" for="ir_phone">Telefone celular *</label>
                        <input type="text" id="ir_phone" name="telefone" class="form-control" value="<?= esc(old('telefone')) ?>" required placeholder="(00) 9 0000-0000" maxlength="16">
                        <div class="sp-field-msg" id="ir_phoneMsg"></div>
                        <input type="hidden" name="phone" id="ir_phoneHid">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_birth">Data de nascimento *</label>
                        <input type="date" id="ir_birth" name="birth_date" class="form-control" value="<?= esc(old('birth_date')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_sexo">Sexo *</label>
                        <select id="ir_sexo" name="sexo" class="form-select" required>
                            <option value="">Selecione...</option>
                            <option value="masculino" <?= old('sexo') === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="feminino"  <?= old('sexo') === 'feminino'  ? 'selected' : '' ?>>Feminino</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ir_rg">RG *</label>
                        <input type="text" id="ir_rg" name="rg" class="form-control" value="<?= esc(old('rg')) ?>" required maxlength="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ir_rgorg">Órgão emissor *</label>
                        <input type="text" id="ir_rgorg" name="rg_orgao_emissor" class="form-control" value="<?= esc(old('rg_orgao_emissor')) ?>" required maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ir_rgdata">Exp. RG *</label>
                        <input type="date" id="ir_rgdata" name="rg_data_expedicao" class="form-control" value="<?= esc(old('rg_data_expedicao')) ?>" required>
                    </div>
                </div>
            </div>

            <!-- Endereço -->
            <div class="inv-section">
                <h3><i class="bi bi-geo-alt me-1"></i>Endereço</h3>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="ir_cep">CEP *<span id="ir_cepSpin" class="spinner-border spinner-border-sm text-primary ms-1 d-none"></span></label>
                        <input type="text" id="ir_cep" name="cep" class="form-control" value="<?= esc(old('cep')) ?>" required placeholder="00000-000" maxlength="9" inputmode="numeric">
                        <div class="sp-field-msg" id="ir_cepMsg"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_logr">Logradouro *</label>
                        <input type="text" id="ir_logr" name="logradouro" class="form-control" value="<?= esc(old('logradouro')) ?>" required maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="ir_num">Nº *</label>
                        <input type="text" id="ir_num" name="numero" class="form-control" value="<?= esc(old('numero')) ?>" required maxlength="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="ir_bairro">Bairro *</label>
                        <input type="text" id="ir_bairro" name="bairro" class="form-control" value="<?= esc(old('bairro')) ?>" required maxlength="100">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="ir_mun">Município *</label>
                        <input type="text" id="ir_mun" name="municipio" class="form-control" value="<?= esc(old('municipio')) ?>" required maxlength="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ir_uf">UF *</label>
                        <select id="ir_uf" name="uf" class="form-select" required>
                            <option value="">--</option>
                            <?php foreach ($states as $s): ?>
                                <option value="<?= $s ?>" <?= old('uf') === $s ? 'selected' : '' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Vínculo -->
            <div class="inv-section">
                <h3><i class="bi bi-briefcase me-1"></i>Vínculo profissional</h3>
                <div class="row g-3">
                    <?php if (!empty($formOptions['workUnits'])): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_unit">Unidade *</label>
                        <select id="ir_unit" name="work_unit_id" class="form-select" required data-sync-text="work_unit">
                            <option value="">Selecione...</option>
                            <?php foreach ($formOptions['workUnits'] as $u): ?>
                                <option value="<?= esc($u['id']) ?>" data-name="<?= esc($u['name']) ?>" <?= old('work_unit_id') == $u['id'] ? 'selected' : ($invite->work_unit === $u['name'] ? 'selected' : '') ?>><?= esc($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="work_unit" id="work_unit">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($formOptions['departments'])): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_dept">Departamento *</label>
                        <select id="ir_dept" name="department_id" class="form-select" required data-sync-text="department">
                            <option value="">Selecione...</option>
                            <?php foreach ($formOptions['departments'] as $d): ?>
                                <option value="<?= esc($d['id']) ?>" data-name="<?= esc($d['name']) ?>" <?= old('department_id') == $d['id'] ? 'selected' : ($invite->department === $d['name'] ? 'selected' : '') ?>><?= esc($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="department" id="department">
                        <input type="hidden" name="setor"      id="setor">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($formOptions['positions'])): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_pos">Cargo *</label>
                        <select id="ir_pos" name="position_id" class="form-select" required data-sync-text="position">
                            <option value="">Selecione...</option>
                            <?php foreach ($formOptions['positions'] as $p): ?>
                                <option value="<?= esc($p['id']) ?>" data-name="<?= esc($p['name']) ?>" <?= old('position_id') == $p['id'] ? 'selected' : ($invite->position === $p['name'] ? 'selected' : '') ?>><?= esc($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="position" id="position">
                        <input type="hidden" name="cargo"    id="cargo">
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($formOptions['workShifts'])): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_shift">Jornada *</label>
                        <select id="ir_shift" name="work_shift_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($formOptions['workShifts'] as $s): ?>
                                <option value="<?= esc($s['id']) ?>" <?= old('work_shift_id') == $s['id'] ? 'selected' : '' ?>>
                                    <?= esc($s['name']) ?> — <?= substr($s['start_time']??'',0,5) ?> às <?= substr($s['end_time']??'',0,5) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label" for="ir_adm">Data de admissão *</label>
                        <input type="date" id="ir_adm" name="admission_date" class="form-control" value="<?= esc(old('admission_date')) ?>" required>
                    </div>
                </div>
            </div>

            <?= view('components/turnstile_widget') ?>

            <div class="d-grid mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-send-check me-2"></i>Enviar cadastro para aprovação
                </button>
            </div>
            <p class="text-muted text-center mt-2" style="font-size:.78rem">
                Ao enviar, seus dados serão encaminhados ao RH para análise e aprovação.
            </p>
        </form>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(function(){
    // Password strength
    var pwdEl=document.getElementById('ir_pwd'),pwd2El=document.getElementById('ir_pwd2');
    var fill=document.getElementById('ir_pwdFill'),msg=document.getElementById('ir_pwdMsg');
    var msg2=document.getElementById('ir_pwd2Msg');
    document.getElementById('ir_togglePwd')?.addEventListener('click',function(){
        pwdEl.type=pwdEl.type==='password'?'text':'password';
        this.querySelector('i').className=pwdEl.type==='text'?'bi bi-eye-slash':'bi bi-eye';
    });
    function pwdStrength(p){var s=0;if(p.length>=8)s++;if(p.length>=12)s++;if(/[A-Z]/.test(p))s++;if(/[a-z]/.test(p))s++;if(/\d/.test(p))s++;if(/[^A-Za-z0-9]/.test(p))s++;return s;}
    var clrs=['#e9ecef','#dc3545','#fd7e14','#ffc107','#20c997','#198754','#0d6efd'];
    var lbls=['','Muito fraca','Fraca','Razoável','Boa','Forte','Muito forte'];
    pwdEl?.addEventListener('input',function(){
        var s=pwdStrength(this.value);
        if(fill){fill.style.width=(s/6*100)+'%';fill.style.background=clrs[s];}
        if(msg){msg.textContent=lbls[s]||'';msg.style.color=clrs[s];}
        if(pwd2El?.value)pwd2El.dispatchEvent(new Event('input'));
    });
    pwd2El?.addEventListener('input',function(){
        var ok=this.value===pwdEl?.value;
        if(msg2){msg2.textContent=this.value?(ok?'✓ Senhas coincidem':'✗ Senhas não coincidem'):'';msg2.style.color=ok?'#198754':'#dc3545';}
        this.closest('[id$=Wrap]')?.classList?.toggle('sp-field-err',!ok&&!!this.value);
    });

    // CPF mask + validação real (compartilhado)
    SupportPontoValidation.bindCpfField(document.getElementById('ir_cpf'), { wrapId: 'ir_cpfWrap', msgId: 'ir_cpfMsg' });

    // Phone mask
    function phoneMask(v){v=v.replace(/\D/g,'').slice(0,11);if(!v.length)return '';if(v.length<=2)return '('+v;if(v.length<=3)return '('+v.slice(0,2)+') '+v.slice(2);if(v.length<=7)return '('+v.slice(0,2)+') '+v.slice(2,3)+' '+v.slice(3);return '('+v.slice(0,2)+') '+v.slice(2,3)+' '+v.slice(3,7)+'-'+v.slice(7);}
    var phoneEl=document.getElementById('ir_phone'),phoneHid=document.getElementById('ir_phoneHid');
    phoneEl?.addEventListener('input',function(){this.value=phoneMask(this.value);var r=this.value.replace(/\D/g,'');if(phoneHid)phoneHid.value=r;var m=document.getElementById('ir_phoneMsg');if(m)m.textContent=r.length===11?'✓ Formato válido':r.length>0?'✗ Use (DDD) 9 XXXX-XXXX':''});

    // CEP auto-fill (compartilhado)
    SupportPontoValidation.bindCepField(document.getElementById('ir_cep'), {
        msgId: 'ir_cepMsg',
        spinnerId: 'ir_cepSpin',
        fields: { logradouro: 'ir_logr', bairro: 'ir_bairro', municipio: 'ir_mun', uf: 'ir_uf', numero: 'ir_num' }
    });

    // Catalog sync
    document.querySelectorAll('select[data-sync-text]').forEach(function(sel){
        function sync(){var opt=sel.options[sel.selectedIndex];var name=opt?(opt.dataset.name||opt.text||')':'';
        var el=document.getElementById(sel.dataset.syncText);if(el)el.value=name;
        var also=sel.dataset.syncAlso;if(also){var el2=document.getElementById(also);if(el2)el2.value=name;}}
        sel.addEventListener('change',sync);sync();
    });
})();
</script>
<?= $this->endSection() ?>
