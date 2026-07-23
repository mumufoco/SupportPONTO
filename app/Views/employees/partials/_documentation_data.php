<?php
$employee = $employee ?? null;
$value = static fn(string $field, $default = '') => old($field, $employee->{$field} ?? $default);
$states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
$possuiCnh = (bool) old('possui_cnh', $employee->possui_cnh ?? false);
$possuiCtpsFisica = (bool) old('possui_ctps_fisica', $employee->possui_ctps_fisica ?? false);
?>
<div class="row g-3">

    <!-- Título de Eleitor -->
    <div class="col-12">
        <div class="sp-form-divider"><i class="bi bi-card-checklist me-1 text-primary"></i>Título de Eleitor</div>
    </div>
    <div class="col-6 col-md-3">
        <label for="titulo_eleitor_numero" class="form-label">Número</label>
        <input type="text" id="titulo_eleitor_numero" name="titulo_eleitor_numero" class="form-control"
               value="<?= esc($value('titulo_eleitor_numero')) ?>" maxlength="12">
    </div>
    <div class="col-6 col-md-2">
        <label for="titulo_eleitor_zona" class="form-label">Zona</label>
        <input type="text" id="titulo_eleitor_zona" name="titulo_eleitor_zona" class="form-control"
               value="<?= esc($value('titulo_eleitor_zona')) ?>" maxlength="5">
    </div>
    <div class="col-6 col-md-2">
        <label for="titulo_eleitor_secao" class="form-label">Seção</label>
        <input type="text" id="titulo_eleitor_secao" name="titulo_eleitor_secao" class="form-control"
               value="<?= esc($value('titulo_eleitor_secao')) ?>" maxlength="5">
    </div>
    <div class="col-6 col-md-2">
        <label for="titulo_eleitor_uf" class="form-label">UF</label>
        <select id="titulo_eleitor_uf" name="titulo_eleitor_uf" class="form-select">
            <option value="">—</option>
            <?php foreach ($states as $st): ?>
                <option value="<?= esc($st) ?>" <?= (string) $value('titulo_eleitor_uf') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-md-3">
        <label for="titulo_eleitor_municipio" class="form-label">Município</label>
        <input type="text" id="titulo_eleitor_municipio" name="titulo_eleitor_municipio" class="form-control"
               value="<?= esc($value('titulo_eleitor_municipio')) ?>" maxlength="100">
    </div>

    <!-- CNH -->
    <div class="col-12">
        <div class="sp-form-divider"><i class="bi bi-car-front-fill me-1 text-primary"></i>CNH</div>
    </div>
    <div class="col-12">
        <label class="sp-toggle-card <?= $possuiCnh ? 'is-on' : '' ?>" id="cardPossuiCnh" for="possui_cnh">
            <div class="sp-toggle-card__icon text-primary"><i class="bi bi-car-front-fill"></i></div>
            <div class="sp-toggle-card__body">
                <div class="sp-toggle-card__title">
                    Possui CNH?
                    <span class="sp-toggle-pill" id="pillPossuiCnh"></span>
                </div>
                <div class="sp-toggle-card__desc">Marque para informar os dados da Carteira Nacional de Habilitação.</div>
            </div>
            <input class="form-check-input" type="checkbox" id="possui_cnh" name="possui_cnh" value="1" <?= $possuiCnh ? 'checked' : '' ?>>
        </label>
    </div>
    <div class="col-12" id="cnhFieldsWrap" style="<?= $possuiCnh ? '' : 'display:none' ?>">
        <div class="row g-3 mt-1">
            <div class="col-6 col-md-3">
                <label for="cnh_numero" class="form-label">Número da CNH</label>
                <input type="text" id="cnh_numero" name="cnh_numero" class="form-control cnh-field"
                       value="<?= esc($value('cnh_numero')) ?>" maxlength="20" <?= $possuiCnh ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 col-md-2">
                <label for="cnh_categoria" class="form-label">Categoria</label>
                <select id="cnh_categoria" name="cnh_categoria" class="form-select cnh-field" <?= $possuiCnh ? '' : 'disabled' ?>>
                    <option value="">—</option>
                    <?php foreach (['A','B','C','D','E','AB','AC','AD','AE'] as $cat): ?>
                        <option value="<?= esc($cat) ?>" <?= (string) $value('cnh_categoria') === $cat ? 'selected' : '' ?>><?= esc($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="cnh_data_emissao" class="form-label">Emissão</label>
                <input type="date" id="cnh_data_emissao" name="cnh_data_emissao" class="form-control cnh-field"
                       value="<?= esc($value('cnh_data_emissao')) ?>" <?= $possuiCnh ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 col-md-2">
                <label for="cnh_validade" class="form-label">Validade</label>
                <input type="date" id="cnh_validade" name="cnh_validade" class="form-control cnh-field"
                       value="<?= esc($value('cnh_validade')) ?>" <?= $possuiCnh ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 col-md-2">
                <label for="cnh_orgao_emissor" class="form-label">Órgão emissor</label>
                <input type="text" id="cnh_orgao_emissor" name="cnh_orgao_emissor" class="form-control cnh-field"
                       value="<?= esc($value('cnh_orgao_emissor')) ?>" maxlength="20" <?= $possuiCnh ? '' : 'disabled' ?>>
            </div>
            <div class="col-6 col-md-1">
                <label for="cnh_uf" class="form-label">UF</label>
                <select id="cnh_uf" name="cnh_uf" class="form-select cnh-field" <?= $possuiCnh ? '' : 'disabled' ?>>
                    <option value="">—</option>
                    <?php foreach ($states as $st): ?>
                        <option value="<?= esc($st) ?>" <?= (string) $value('cnh_uf') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- CTPS Digital / CTPS física -->
    <div class="col-12">
        <div class="sp-form-divider"><i class="bi bi-journal-bookmark-fill me-1 text-primary"></i>CTPS Digital / CTPS física</div>
        <p class="text-muted small mb-0">
            Desde 2019 a CTPS Digital dispensa carteira física para a maioria dos admitidos —
            o vínculo é feito só por CPF. Só peça número/série de quem realmente tem a
            carteira de trabalho física (o livrinho de papel).
        </p>
    </div>
    <div class="col-12">
        <label class="sp-toggle-card <?= $possuiCtpsFisica ? 'is-on' : '' ?>" id="cardPossuiCtpsFisica" for="possui_ctps_fisica">
            <div class="sp-toggle-card__icon text-primary"><i class="bi bi-journal-bookmark-fill"></i></div>
            <div class="sp-toggle-card__body">
                <div class="sp-toggle-card__title">
                    Possui CTPS física (carteira impressa)?
                    <span class="sp-toggle-pill" id="pillPossuiCtpsFisica"></span>
                </div>
                <div class="sp-toggle-card__desc">Marque apenas se o colaborador tem carteira de trabalho física com número e série.</div>
            </div>
            <input class="form-check-input" type="checkbox" id="possui_ctps_fisica" name="possui_ctps_fisica" value="1" <?= $possuiCtpsFisica ? 'checked' : '' ?>>
        </label>
    </div>
    <div class="col-12" id="ctpsFieldsWrap" style="<?= $possuiCtpsFisica ? '' : 'display:none' ?>">
        <div class="row g-3 mt-1">
            <div class="col-6 col-md-2">
                <label for="ctps_numero" class="form-label">CTPS número</label>
                <input type="text" id="ctps_numero" name="ctps_numero" class="form-control ctps-field"
                       value="<?= esc($value('ctps_numero')) ?>" maxlength="15" <?= $possuiCtpsFisica ? '' : 'disabled' ?>>
            </div>
            <div class="col-4 col-md-2">
                <label for="ctps_serie" class="form-label">Série</label>
                <input type="text" id="ctps_serie" name="ctps_serie" class="form-control ctps-field"
                       value="<?= esc($value('ctps_serie')) ?>" maxlength="10" <?= $possuiCtpsFisica ? '' : 'disabled' ?>>
            </div>
            <div class="col-4 col-md-1">
                <label for="ctps_uf" class="form-label">UF</label>
                <select id="ctps_uf" name="ctps_uf" class="form-select ctps-field" <?= $possuiCtpsFisica ? '' : 'disabled' ?>>
                    <option value="">—</option>
                    <?php foreach ($states as $st): ?>
                        <option value="<?= esc($st) ?>" <?= (string) $value('ctps_uf') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="ctps_data_emissao" class="form-label">Emissão CTPS</label>
                <input type="date" id="ctps_data_emissao" name="ctps_data_emissao" class="form-control ctps-field"
                       value="<?= esc($value('ctps_data_emissao')) ?>" <?= $possuiCtpsFisica ? '' : 'disabled' ?>>
            </div>
        </div>
    </div>

    <!-- Dados Gerais -->
    <div class="col-12">
        <div class="sp-form-divider"><i class="bi bi-folder2-open me-1 text-primary"></i>Dados Gerais</div>
        <p class="text-muted small mb-0">CPF, RG e Órgão expedidor já foram informados na Aba 1 — Dados Pessoais.</p>
    </div>
    <div class="col-6 col-md-3">
        <label for="pis_pasep" class="form-label">PIS/PASEP *</label>
        <input type="text" id="pis_pasep" name="pis_pasep" class="form-control"
               value="<?= esc(old('pis_pasep', old('pis', $employee->pis_pasep ?? $employee->pis ?? ''))) ?>"
               required inputmode="numeric">
        <input type="hidden" id="pis" name="pis"
               value="<?= esc(old('pis', old('pis_pasep', $employee->pis ?? $employee->pis_pasep ?? ''))) ?>">
    </div>
    <div class="col-6 col-md-3">
        <label for="certificado_militar" class="form-label">Certificado Militar (RA)</label>
        <input type="text" id="certificado_militar" name="certificado_militar" class="form-control"
               value="<?= esc($value('certificado_militar')) ?>" maxlength="30">
    </div>
    <div class="col-6 col-md-2">
        <label for="rg_uf" class="form-label">UF emissora do RG</label>
        <select id="rg_uf" name="rg_uf" class="form-select">
            <option value="">—</option>
            <?php foreach ($states as $st): ?>
                <option value="<?= esc($st) ?>" <?= (string) $value('rg_uf') === $st ? 'selected' : '' ?>><?= esc($st) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

</div><!-- /row -->

<script <?= csp_script_nonce_attr() ?>>
(function () {
    function bindToggle(checkboxId, wrapId, fieldClass) {
        var cb = document.getElementById(checkboxId);
        var card = cb ? cb.closest('.sp-toggle-card') : null;
        var wrap = document.getElementById(wrapId);
        var fields = document.querySelectorAll('.' + fieldClass);

        function apply(checked) {
            if (wrap) wrap.style.display = checked ? '' : 'none';
            fields.forEach(function (el) {
                el.disabled = !checked;
                el.required = checked;
            });
            if (card) card.classList.toggle('is-on', checked);
        }

        if (cb) {
            apply(cb.checked);
            cb.addEventListener('change', function () { apply(this.checked); });
            if (card) {
                card.addEventListener('click', function (e) {
                    if (e.target === cb) return;
                    cb.checked = !cb.checked;
                    cb.dispatchEvent(new Event('change'));
                });
            }
        }
    }

    bindToggle('possui_cnh', 'cnhFieldsWrap', 'cnh-field');
    bindToggle('possui_ctps_fisica', 'ctpsFieldsWrap', 'ctps-field');
})();
</script>
