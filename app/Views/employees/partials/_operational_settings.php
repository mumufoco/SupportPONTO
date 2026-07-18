<?php
$employee = $employee ?? null;
$value = static fn(string $field, $default = '') => old($field, $employee->{$field} ?? $default);
$isEditing = isset($employee) && !empty($employee->id ?? null);
$states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
?>
<style>
#pwdStrengthBar { height:5px; border-radius:3px; transition:width .3s,background .3s; background:#dc3545 }
.sp-toggle-card { border:1.5px solid var(--sp-border); border-radius:.6rem; padding:1rem 1.1rem; display:flex; align-items:flex-start; gap:.85rem; cursor:pointer; transition:border-color .15s,background .15s; background:var(--sp-bg-surface); }
.sp-toggle-card:hover { border-color:var(--sp-primary); background:var(--sp-primary-soft) }
.sp-toggle-card input[type=checkbox] { display:none }
.sp-toggle-card.is-on { border-color:var(--sp-primary); background:var(--sp-primary-light) }
.sp-toggle-card__icon { font-size:1.6rem; flex-shrink:0; margin-top:.1rem }
.sp-toggle-card__body { flex:1 }
.sp-toggle-card__title { font-weight:600; font-size:.9rem; display:flex; align-items:center; gap:.5rem }
.sp-toggle-card__desc { font-size:.78rem; color:var(--sp-text-muted); margin-top:.2rem; line-height:1.45 }
.sp-toggle-pill { display:inline-flex; align-items:center; height:1.4rem; width:2.6rem; border-radius:1rem; padding:0 .2rem; background:var(--sp-gray-300); transition:background .2s; flex-shrink:0 }
.sp-toggle-pill::after { content:''; width:1rem; height:1rem; border-radius:50%; background:var(--sp-text-white); box-shadow:0 1px 3px rgba(0,0,0,.2); transition:transform .2s; transform:translateX(0) }
.is-on .sp-toggle-pill { background:var(--sp-primary) }
.is-on .sp-toggle-pill::after { transform:translateX(1.2rem) }
</style>

<div class="row g-3">

    <!-- Código único + PIS -->
    <div class="col-6 col-md-3">
        <label for="employee_code" class="form-label">
            Código único
            <span class="badge text-bg-secondary ms-1" style="font-size:.65rem">Auto</span>
        </label>
        <div class="input-group">
            <input type="text" id="employee_code" name="employee_code" class="form-control font-monospace"
                   value="<?= esc(old('employee_code', old('unique_code', $employee->unique_code ?? ''))) ?>"
                   maxlength="12" readonly style="background:var(--sp-gray-50);cursor:default;letter-spacing:.05em"
                   title="Código gerado automaticamente">
            <button type="button" class="btn btn-outline-secondary" id="btnRegenCode" title="Gerar novo código">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
        <div class="form-text">Gerado automaticamente.</div>
    </div>

    <div class="col-6 col-md-3">
        <label for="pis_pasep" class="form-label">PIS/PASEP *</label>
        <input type="text" id="pis_pasep" name="pis_pasep" class="form-control"
               value="<?= esc(old('pis_pasep', old('pis', $employee->pis_pasep ?? $employee->pis ?? ''))) ?>"
               required inputmode="numeric">
        <input type="hidden" id="pis" name="pis"
               value="<?= esc(old('pis', old('pis_pasep', $employee->pis ?? $employee->pis_pasep ?? ''))) ?>">
    </div>

    <div class="col-6 col-md-2">
        <label for="ctps_numero" class="form-label">CTPS número *</label>
        <input type="text" id="ctps_numero" name="ctps_numero" class="form-control"
               value="<?= esc($value('ctps_numero')) ?>" required maxlength="15">
    </div>
    <div class="col-4 col-md-2">
        <label for="ctps_serie" class="form-label">Série *</label>
        <input type="text" id="ctps_serie" name="ctps_serie" class="form-control"
               value="<?= esc($value('ctps_serie')) ?>" required maxlength="10">
    </div>
    <div class="col-4 col-md-1">
        <label for="ctps_uf" class="form-label">UF *</label>
        <select id="ctps_uf" name="ctps_uf" class="form-select" required>
            <option value="">—</option>
            <?php foreach ($states as $st): ?>
                <option value="<?= esc($st) ?>" <?= (string)$value('ctps_uf')===$st?'selected':'' ?>><?= esc($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-4 col-md-1 d-flex align-items-end">
        <div class="form-check pb-2">
            <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                   <?= old('active', $employee->active ?? 1) ? 'checked' : '' ?>>
            <label class="form-check-label fw-semibold small" for="active">Ativo</label>
        </div>
    </div>

    <!-- Data emissão CTPS -->
    <div class="col-6 col-md-3">
        <label for="ctps_data_emissao" class="form-label">Emissão CTPS *</label>
        <input type="date" id="ctps_data_emissao" name="ctps_data_emissao" class="form-control"
               value="<?= esc($value('ctps_data_emissao')) ?>" required>
    </div>

    <!-- Banco -->
    <div class="col-6 col-md-3">
        <label for="banco" class="form-label">Banco *</label>
        <input type="text" id="banco" name="banco" class="form-control"
               value="<?= esc($value('banco')) ?>" required maxlength="50">
    </div>
    <div class="col-4 col-md-2">
        <label for="agencia" class="form-label">Agência *</label>
        <input type="text" id="agencia" name="agencia" class="form-control"
               value="<?= esc($value('agencia')) ?>" required maxlength="10">
    </div>
    <div class="col-4 col-md-2">
        <label for="conta" class="form-label">Conta *</label>
        <input type="text" id="conta" name="conta" class="form-control"
               value="<?= esc($value('conta')) ?>" required maxlength="20">
    </div>

    <!-- PIX -->
    <div class="col-6 col-md-2">
        <label for="pix_key_type" class="form-label">Tipo PIX</label>
        <select id="pix_key_type" name="pix_key_type" class="form-select">
            <option value="">— Sem PIX —</option>
            <option value="cpf"    <?= $value('pix_key_type')==='cpf'    ?'selected':'' ?>>CPF</option>
            <option value="cnpj"   <?= $value('pix_key_type')==='cnpj'   ?'selected':'' ?>>CNPJ</option>
            <option value="email"  <?= $value('pix_key_type')==='email'  ?'selected':'' ?>>E-mail</option>
            <option value="phone"  <?= $value('pix_key_type')==='phone'  ?'selected':'' ?>>Telefone</option>
            <option value="random" <?= $value('pix_key_type')==='random' ?'selected':'' ?>>Aleatória</option>
        </select>
        <div class="form-text" id="pixTypeHint" style="min-height:1rem"></div>
    </div>
    <div class="col-6 col-md-4" id="pixKeyWrap" style="display:none">
        <label for="pix_key" class="form-label" id="pixKeyLabel">Chave PIX</label>
        <div class="input-group">
            <input type="text" id="pix_key" name="pix_key" class="form-control"
                   value="<?= esc($value('pix_key')) ?>" maxlength="150" autocomplete="off" spellcheck="false">
            <button type="button" class="btn btn-outline-secondary d-none" id="btnPixRandom" title="Gerar chave aleatória">
                <i class="bi bi-shuffle"></i>
            </button>
        </div>
        <div class="sp-field-msg" id="msg-pix" style="font-size:.75rem;margin-top:.2rem"></div>
    </div>

    <!-- Senha -->
    <div class="col-12 col-md-6">
        <label for="password" class="form-label"><?= $isEditing ? 'Nova senha' : 'Senha inicial *' ?></label>
        <div class="d-flex align-items-center gap-2">
            <input type="password" id="password" name="password" class="form-control"
                   <?= !$isEditing ? 'required' : '' ?> minlength="8" autocomplete="new-password"
                   <?= $isEditing ? 'placeholder="Preencha apenas se desejar alterar"' : '' ?>>
            <div class="table-icon-actions">
                <button type="button" class="icon-action" id="btnTogglePwd" title="Mostrar/ocultar">
                    <i class="bi bi-eye" id="pwdEyeIcon"></i>
                </button>
                <button type="button" class="icon-action icon-action-edit" id="btnGenPwd" title="Gerar senha forte">
                    <i class="bi bi-magic"></i>
                </button>
                <button type="button" class="icon-action icon-action-success" id="btnCopyPwd" title="Copiar senha">
                    <i class="bi bi-clipboard" id="pwdClipIcon"></i>
                </button>
            </div>
        </div>
        <div class="mt-1" style="height:5px;background:var(--sp-gray-200);border-radius:3px;overflow:hidden">
            <div id="pwdStrengthBar" style="width:0%"></div>
        </div>
        <div id="pwdStrengthMsg" class="form-text mt-1" style="min-height:1rem"></div>
        <div class="form-text">Mínimo 8 caracteres. Use <i class="bi bi-magic"></i> para gerar uma senha forte.</div>
    </div>

    <div class="col-12 col-md-6 d-flex align-items-center">
        <div class="sp-callout-info py-2 px-3 w-100">
            <?php if ($isEditing): ?>
                <a href="<?= site_url('employees/dependents?employee_id=' . (int) $employee->id) ?>">
                    <i class="bi bi-person-hearts me-1"></i>Gerencie os dependentes deste colaborador
                </a>
            <?php else: ?>
                Dependentes podem ser cadastrados após salvar este colaborador.
            <?php endif; ?>
        </div>
    </div>

    <!-- Controles operacionais -->
    <div class="col-12">
        <div class="sp-form-divider"><i class="bi bi-shield-lock-fill me-1 text-primary"></i>Controles operacionais</div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="sp-toggle-card <?= old('allow_remote_punch', $employee->allow_remote_punch ?? 0) ? 'is-on' : '' ?>"
                       id="cardRemote" for="allow_remote_punch">
                    <div class="sp-toggle-card__icon text-primary"><i class="bi bi-wifi"></i></div>
                    <div class="sp-toggle-card__body">
                        <div class="sp-toggle-card__title">
                            Permitir ponto remoto
                            <span class="sp-toggle-pill" id="pillRemote"></span>
                        </div>
                        <div class="sp-toggle-card__desc">
                            Quando <strong>ativado</strong>, o colaborador pode registrar o ponto de qualquer localização via aplicativo ou navegador.<br>
                            <span class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Recomendado apenas para equipes externas ou trabalho remoto.</span>
                        </div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="allow_remote_punch" name="allow_remote_punch" value="1"
                           <?= old('allow_remote_punch', $employee->allow_remote_punch ?? 0) ? 'checked' : '' ?>>
                </label>
            </div>
            <div class="col-12 col-md-6">
                <label class="sp-toggle-card <?= old('require_geolocation', $employee->require_geolocation ?? 0) ? 'is-on' : '' ?>"
                       id="cardGeo" for="require_geolocation">
                    <div class="sp-toggle-card__icon text-success"><i class="bi bi-geo-alt-fill"></i></div>
                    <div class="sp-toggle-card__body">
                        <div class="sp-toggle-card__title">
                            Exigir geolocalização
                            <span class="sp-toggle-pill" id="pillGeo"></span>
                        </div>
                        <div class="sp-toggle-card__desc">
                            Quando <strong>ativado</strong>, o registro de ponto só é aceito se o colaborador estiver dentro de uma área geográfica válida (geofence).<br>
                            <span class="text-info"><i class="bi bi-info-circle me-1"></i>Requer que o dispositivo tenha GPS ou localização ativados.</span>
                        </div>
                    </div>
                    <input class="form-check-input" type="checkbox" id="require_geolocation" name="require_geolocation" value="1"
                           <?= old('require_geolocation', $employee->require_geolocation ?? 0) ? 'checked' : '' ?>>
                </label>
            </div>
        </div>
    </div>

</div><!-- /row -->

<script <?= csp_script_nonce_attr() ?>>
(function () {
    /* Código único */
    var codeInp=document.getElementById('employee_code'),codeBtn=document.getElementById('btnRegenCode');
    var CODE_CHARS='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    function genCode(){var arr=new Uint32Array(9);crypto.getRandomValues(arr);return Array.from(arr,function(n){return CODE_CHARS[n%CODE_CHARS.length];}).join('');}
    if(codeInp&&codeInp.value==='')codeInp.value=genCode();
    if(codeBtn)codeBtn.addEventListener('click',function(){if(codeInp)codeInp.value=genCode();});

    /* Senha */
    var pwdEl=document.getElementById('password'),eyeBtn=document.getElementById('btnTogglePwd'),eyeIcon=document.getElementById('pwdEyeIcon'),genBtn=document.getElementById('btnGenPwd'),copyBtn=document.getElementById('btnCopyPwd'),clipIcon=document.getElementById('pwdClipIcon'),bar=document.getElementById('pwdStrengthBar'),barMsg=document.getElementById('pwdStrengthMsg');
    var UPPERS='ABCDEFGHIJKLMNOPQRSTUVWXYZ',LOWERS='abcdefghijklmnopqrstuvwxyz',DIGITS='0123456789',SPECIALS='!@#$%^&*()-_=+[]{}|;:,.<>?';
    function genPassword(){var len=14,all=UPPERS+LOWERS+DIGITS+SPECIALS,arr=new Uint32Array(len+4);crypto.getRandomValues(arr);var pwd=[UPPERS[arr[0]%UPPERS.length],LOWERS[arr[1]%LOWERS.length],DIGITS[arr[2]%DIGITS.length],SPECIALS[arr[3]%SPECIALS.length]];for(var i=4;i<len+4;i++)pwd.push(all[arr[i]%all.length]);crypto.getRandomValues(arr);for(var i=pwd.length-1;i>0;i--){var j=arr[i]%(i+1),t=pwd[i];pwd[i]=pwd[j];pwd[j]=t;}return pwd.join('');}
    function checkStrength(pwd){if(!pwd)return{score:0,label:'',color:'#e9ecef'};var score=0;if(pwd.length>=8)score++;if(pwd.length>=12)score++;if(/[A-Z]/.test(pwd))score++;if(/[a-z]/.test(pwd))score++;if(/\d/.test(pwd))score++;if(/[^A-Za-z0-9]/.test(pwd))score++;return{score:score,label:['','Muito fraca','Fraca','Razoável','Boa','Forte','Muito forte'][score]||'',color:['#e9ecef','#dc3545','#fd7e14','#ffc107','#20c997','#198754','#0d6efd'][score]||'#dc3545'};}
    if(pwdEl){pwdEl.addEventListener('input',function(){var s=checkStrength(this.value);if(bar){bar.style.width=(s.score/6*100)+'%';bar.style.background=s.color;}if(barMsg){barMsg.textContent=s.label;barMsg.style.color=s.color;}});}
    if(eyeBtn&&pwdEl)eyeBtn.addEventListener('click',function(){var show=pwdEl.type==='password';pwdEl.type=show?'text':'password';if(eyeIcon)eyeIcon.className=show?'bi bi-eye-slash':'bi bi-eye';});
    if(genBtn&&pwdEl)genBtn.addEventListener('click',function(){var p=genPassword();pwdEl.value=p;pwdEl.type='text';if(eyeIcon)eyeIcon.className='bi bi-eye-slash';pwdEl.dispatchEvent(new Event('input'));});
    if(copyBtn&&pwdEl)copyBtn.addEventListener('click',function(){if(!pwdEl.value)return;navigator.clipboard.writeText(pwdEl.value).then(function(){if(clipIcon)clipIcon.className='bi bi-clipboard-check';setTimeout(function(){if(clipIcon)clipIcon.className='bi bi-clipboard';},2000);});});

    /* PIX */
    (function(){
        var typeEl=document.getElementById('pix_key_type'),keyWrap=document.getElementById('pixKeyWrap'),keyInp=document.getElementById('pix_key'),keyLabel=document.getElementById('pixKeyLabel'),hintEl=document.getElementById('pixTypeHint'),msgEl=document.getElementById('msg-pix'),randBtn=document.getElementById('btnPixRandom');
        var CFG={cpf:{label:'CPF',placeholder:'000.000.000-00',hint:'Mesmo CPF do titular.',maxlength:14},cnpj:{label:'CNPJ',placeholder:'00.000.000/0000-00',hint:'CNPJ da empresa.',maxlength:18},email:{label:'E-mail',placeholder:'colaborador@empresa.com.br',hint:'E-mail cadastrado no banco.',maxlength:77},phone:{label:'Telefone (+55)',placeholder:'+55 (DDD) 9 XXXX-XXXX',hint:'Celular com +55 e DDD.',maxlength:16},random:{label:'Chave aleatória',placeholder:'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx',hint:'UUID gerado pelo banco.',maxlength:36}};
        function setPixMsg(ok,text){if(!msgEl)return;msgEl.textContent=text||'';msgEl.style.color=ok===true?'#198754':ok===false?'#dc3545':'#6b7280';if(keyInp)keyInp.style.borderColor=ok===true?'#198754':ok===false?'#dc3545':'';}
        function validatePixKey(t,val){if(!val)return null;val=val.trim();if(t==='cpf'){var d=val.replace(/\D/g,'');if(d.length!==11)return false;var s=0,r;for(var i=0;i<9;i++)s+=+d[i]*(10-i);r=11-(s%11);if(r>=10)r=0;if(r!==+d[9])return false;s=0;for(var i=0;i<10;i++)s+=+d[i]*(11-i);r=11-(s%11);if(r>=10)r=0;return r===+d[10];}if(t==='email')return/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(val);if(t==='phone')return val.replace(/\D/g,'').length===13&&val.replace(/\D/g,'').startsWith('55');if(t==='random')return/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(val);return null;}
        function genUUID(){var a=new Uint8Array(16);crypto.getRandomValues(a);a[6]=(a[6]&0x0f)|0x40;a[8]=(a[8]&0x3f)|0x80;var h=Array.from(a,function(b){return b.toString(16).padStart(2,'0');}).join('');return h.slice(0,8)+'-'+h.slice(8,12)+'-'+h.slice(12,16)+'-'+h.slice(16,20)+'-'+h.slice(20);}
        function applyPixType(){var t=typeEl?typeEl.value:'';if(!t){if(keyWrap)keyWrap.style.display='none';if(hintEl)hintEl.textContent='';setPixMsg(null,'');return;}var cfg=CFG[t]||{};if(keyWrap)keyWrap.style.display='';if(keyLabel)keyLabel.textContent=cfg.label||'Chave PIX';if(keyInp){keyInp.placeholder=cfg.placeholder||'';keyInp.maxLength=cfg.maxlength||150;}if(hintEl)hintEl.textContent=cfg.hint||'';if(randBtn)randBtn.classList.toggle('d-none',t!=='random');}
        if(typeEl){typeEl.addEventListener('change',applyPixType);applyPixType();}
        if(keyInp)keyInp.addEventListener('input',function(){var t=typeEl?typeEl.value:'',ok=validatePixKey(t,this.value),cfg=CFG[t]||{};if(!this.value){setPixMsg(null,'');return;}setPixMsg(ok,ok===true?'✓ Chave válida':ok===false?'✗ Formato inválido para '+(cfg.label||'PIX'):'');});
        if(randBtn)randBtn.addEventListener('click',function(){if(keyInp){keyInp.value=genUUID();keyInp.dispatchEvent(new Event('input'));}});
    })();

    /* Toggle cards */
    ['allow_remote_punch','require_geolocation'].forEach(function(id){
        var cb=document.getElementById(id),card=cb?cb.closest('.sp-toggle-card'):null;
        if(!cb||!card)return;
        cb.addEventListener('change',function(){card.classList.toggle('is-on',this.checked);});
        card.addEventListener('click',function(e){if(e.target===cb)return;cb.checked=!cb.checked;cb.dispatchEvent(new Event('change'));});
    });
})();
</script>
