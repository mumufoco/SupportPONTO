<?php
$employee = $employee ?? null;
$value = static fn(string $field, $default = '') => old($field, $employee->{$field} ?? $default);
$catalogId = static fn(string $field) => old($field, $employee->{$field} ?? '');

$_shiftsJson = '{}';
if (!empty($formOptions['workShifts'])) {
    $_map = [];
    foreach ($formOptions['workShifts'] as $_s) {
        $_id    = (string)($_s['id'] ?? '');
        $_start = substr((string)($_s['start_time'] ?? '00:00'), 0, 5);
        $_end   = substr((string)($_s['end_time']   ?? '00:00'), 0, 5);
        $_break = (int)($_s['break_duration'] ?? 0);
        $_startMin = (int)explode(':', $_start)[0]*60 + (int)(explode(':', $_start)[1] ?? 0);
        $_endMin   = (int)explode(':', $_end)[0]*60   + (int)(explode(':', $_end)[1]   ?? 0);
        if ($_endMin <= $_startMin) $_endMin += 1440;
        $_dailyH = round(($_endMin - $_startMin - $_break) / 60, 2);
        $_map[$_id] = ['name' => (string)($_s['name'] ?? ''), 'start' => $_start, 'end' => $_end, 'break' => $_break, 'daily_h' => $_dailyH, 'weekly_h' => round($_dailyH*5, 2), 'label' => $_start.' às '.$_end];
    }
    $_shiftsJson = json_encode($_map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
}
?>
<div class="row g-3">

    <!-- Unidade, Departamento, Cargo -->
    <div class="col-12 col-md-4">
        <label for="work_unit_id" class="form-label">Unidade de trabalho *</label>
        <?php if (!empty($formOptions['workUnits'])): ?>
            <select id="work_unit_id" name="work_unit_id" class="form-select" required data-sync-text="work_unit">
                <option value="">Selecione a unidade...</option>
                <?php foreach ($formOptions['workUnits'] as $item): ?>
                    <option value="<?= esc($item['id'] ?? '') ?>" data-name="<?= esc($item['name'] ?? '') ?>"
                            <?= (string)$catalogId('work_unit_id') === (string)($item['id'] ?? '') ? 'selected' : '' ?>>
                        <?= esc($item['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="work_unit" name="work_unit" value="<?= esc($value('work_unit')) ?>">
        <?php else: ?>
            <input type="text" id="work_unit" name="work_unit" class="form-control" value="<?= esc($value('work_unit')) ?>" required maxlength="120">
            <small class="text-warning"><i class="bi bi-exclamation-triangle me-1"></i>Catálogo indisponível.</small>
        <?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
        <label for="department_id" class="form-label">Departamento *</label>
        <?php if (!empty($formOptions['departments'])): ?>
            <select id="department_id" name="department_id" class="form-select" required data-sync-text="department" data-sync-also="setor">
                <option value="">Selecione o departamento...</option>
                <?php foreach ($formOptions['departments'] as $item): ?>
                    <option value="<?= esc($item['id'] ?? '') ?>" data-name="<?= esc($item['name'] ?? '') ?>"
                            <?= (string)$catalogId('department_id') === (string)($item['id'] ?? '') ? 'selected' : '' ?>>
                        <?= esc($item['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="department" name="department" value="<?= esc($value('department')) ?>">
            <input type="hidden" id="setor"      name="setor"      value="<?= esc($value('setor', $value('department'))) ?>">
        <?php else: ?>
            <input type="text" id="department" name="department" class="form-control" value="<?= esc($value('department')) ?>" required maxlength="100">
            <input type="hidden" id="setor" name="setor" value="<?= esc($value('setor', $value('department'))) ?>">
        <?php endif; ?>
    </div>

    <div class="col-12 col-md-4">
        <label for="position_id" class="form-label">Cargo / Função *</label>
        <?php if (!empty($formOptions['positions'])): ?>
            <select id="position_id" name="position_id" class="form-select" required data-sync-text="position" data-sync-also="cargo">
                <option value="">Selecione o cargo...</option>
                <?php foreach ($formOptions['positions'] as $item): ?>
                    <option value="<?= esc($item['id'] ?? '') ?>" data-name="<?= esc($item['name'] ?? '') ?>"
                            <?= (string)$catalogId('position_id') === (string)($item['id'] ?? '') ? 'selected' : '' ?>>
                        <?= esc($item['name'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" id="position" name="position" value="<?= esc($value('position')) ?>">
            <input type="hidden" id="cargo"    name="cargo"    value="<?= esc($value('cargo', $value('position'))) ?>">
        <?php else: ?>
            <input type="text" id="position" name="position" class="form-control" value="<?= esc($value('position')) ?>" required maxlength="100">
            <input type="hidden" id="cargo" name="cargo" value="<?= esc($value('cargo', $value('position'))) ?>">
        <?php endif; ?>
    </div>

    <!-- Perfil, Contrato, Admissão, Desligamento -->
    <div class="col-6 col-md-3">
        <label for="role_id" class="form-label">Perfil de acesso *</label>
        <?php
            $_roleId = (int)old('role_id', $employee->role_id ?? 0);
            if ($_roleId === 0 && !empty($employee->role ?? null)) {
                foreach ($formOptions['roles'] ?? [] as $_r) {
                    if (strtolower($_r->name ?? $_r['name'] ?? '') === strtolower($employee->role)) {
                        $_roleId = (int)($_r->id ?? $_r['id'] ?? 0); break;
                    }
                }
            }
            if ($_roleId === 0) $_roleId = 1;
        ?>
        <?php if (!empty($formOptions['roles'])): ?>
            <select id="role_id" name="role_id" class="form-select" required>
                <?php foreach ($formOptions['roles'] as $_role): ?>
                    <?php $_rid=(int)($_role->id??$_role['id']??0); $_rname=$_role->name??$_role['name']??''; ?>
                    <option value="<?= esc($_rid) ?>" <?= $_roleId===$_rid?'selected':'' ?>><?= esc(ucfirst($_rname)) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <select id="role_id" name="role_id" class="form-select" required>
                <option value="1" <?= $_roleId===1?'selected':'' ?>>Colaborador</option>
                <option value="2" <?= $_roleId===2?'selected':'' ?>>Gestor</option>
                <option value="4" <?= $_roleId===4?'selected':'' ?>>RH</option>
                <option value="6" <?= $_roleId===6?'selected':'' ?>>DPO / LGPD</option>
                <option value="3" <?= $_roleId===3?'selected':'' ?>>Administrador</option>
            </select>
        <?php endif; ?>
    </div>

    <div class="col-6 col-md-3">
        <label for="tipo_contrato" class="form-label">Tipo de contrato *</label>
        <?php if (!empty($formOptions['contractTypes'])): ?>
            <select id="tipo_contrato" name="tipo_contrato" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($formOptions['contractTypes'] as $_ct): ?>
                    <?php $_ctName = $_ct['name'] ?? ''; ?>
                    <option value="<?= esc($_ctName) ?>" <?= (string)$value('tipo_contrato')===(string)$_ctName?'selected':'' ?>><?= esc($_ctName) ?></option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <select id="tipo_contrato" name="tipo_contrato" class="form-select" required>
                <?php foreach (['' => 'Selecione...', 'CLT' => 'CLT', 'temporario' => 'Temporário', 'estagio' => 'Estágio', 'terceirizado' => 'Terceirizado'] as $k => $l): ?>
                    <option value="<?= esc($k) ?>" <?= (string)$value('tipo_contrato')===(string)$k?'selected':'' ?>><?= esc($l) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>

    <div class="col-6 col-md-3">
        <label for="admission_date" class="form-label">Data de admissão *</label>
        <input type="date" id="admission_date" name="admission_date" class="form-control"
               value="<?= esc($value('admission_date')) ?>" required>
    </div>
    <div class="col-6 col-md-3">
        <label for="demission_date" class="form-label">Data de desligamento</label>
        <input type="date" id="demission_date" name="demission_date" class="form-control"
               value="<?= esc($value('demission_date')) ?>">
    </div>

    <!-- Jornada -->
    <div class="col-12 col-md-4">
        <label for="work_shift_id" class="form-label">Jornada de trabalho *</label>
        <?php if (!empty($formOptions['workShifts'])): ?>
            <select id="work_shift_id" name="work_shift_id" class="form-select" required>
                <option value="">Selecione a jornada...</option>
                <?php foreach ($formOptions['workShifts'] as $item): ?>
                    <option value="<?= esc($item['id'] ?? '') ?>"
                            <?= (string)old('work_shift_id', $employee->work_shift_id ?? '') === (string)($item['id'] ?? '') ? 'selected' : '' ?>>
                        <?= esc($item['name'] ?? '') ?> — <?= substr((string)($item['start_time']??''),0,5) ?> às <?= substr((string)($item['end_time']??''),0,5) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input type="text" name="work_shift_id" class="form-control" value="" placeholder="Jornada não disponível">
        <?php endif; ?>
    </div>
    <div class="col-12 col-md-8" id="shiftSummaryWrap" style="display:none">
        <label class="form-label">&nbsp;</label>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-0 py-2" style="font-size:.875rem">
            <i class="bi bi-clock-fill flex-shrink-0"></i>
            <div id="shiftSummaryText" class="flex-grow-1"></div>
        </div>
    </div>

    <!-- Campos manuais (quando sem jornada) -->
    <div class="col-6 col-md-2" id="manualEntradaWrap">
        <label for="horario_entrada" class="form-label">Entrada</label>
        <input type="time" id="horario_entrada" name="horario_entrada" class="form-control"
               value="<?= esc(old('horario_entrada', $employee->horario_entrada ?? $employee->work_schedule_start ?? $employee->work_start_time ?? '')) ?>">
    </div>
    <div class="col-6 col-md-2" id="manualSaidaWrap">
        <label for="horario_saida" class="form-label">Saída</label>
        <input type="time" id="horario_saida" name="horario_saida" class="form-control"
               value="<?= esc(old('horario_saida', $employee->horario_saida ?? $employee->work_schedule_end ?? $employee->work_end_time ?? '')) ?>">
    </div>
    <div class="col-12 col-md-4" id="manualJornadaWrap">
        <label for="jornada_trabalho" class="form-label">Descrição da jornada</label>
        <input type="text" id="jornada_trabalho" name="jornada_trabalho" class="form-control"
               value="<?= esc($value('jornada_trabalho')) ?>" maxlength="50">
    </div>

    <input type="hidden" id="work_schedule_start" name="work_schedule_start" value="<?= esc(old('work_schedule_start', $employee->work_schedule_start ?? '')) ?>">
    <input type="hidden" id="work_schedule_end"   name="work_schedule_end"   value="<?= esc(old('work_schedule_end',   $employee->work_schedule_end   ?? '')) ?>">

    <!-- Horas + Salário -->
    <div class="col-6 col-md-2">
        <label for="expected_hours_daily" class="form-label">Horas/dia</label>
        <input type="number" step="0.01" min="0" id="expected_hours_daily" name="expected_hours_daily" class="form-control"
               value="<?= esc($value('expected_hours_daily', $employee->daily_hours ?? '8.00')) ?>">
        <input type="hidden" name="daily_hours" value="<?= esc(old('daily_hours', $employee->daily_hours ?? $employee->expected_hours_daily ?? '8.00')) ?>">
    </div>
    <div class="col-6 col-md-2">
        <label for="weekly_hours" class="form-label">Horas/semana</label>
        <input type="number" step="0.01" min="0" id="weekly_hours" name="weekly_hours" class="form-control"
               value="<?= esc($value('weekly_hours', '44.00')) ?>">
    </div>
    <div class="col-6 col-md-3">
        <label for="salario_base" class="form-label">Salário base *</label>
        <div class="input-group">
            <span class="input-group-text">R$</span>
            <input type="number" step="0.01" min="0" id="salario_base" name="salario_base" class="form-control"
                   value="<?= esc($value('salario_base')) ?>" required>
        </div>
    </div>

</div><!-- /row -->

<script <?= csp_script_nonce_attr() ?>>
(function () {
    document.querySelectorAll('select[data-sync-text]').forEach(function (sel) {
        function sync() { var opt=sel.options[sel.selectedIndex]; var name=opt?(opt.dataset.name||opt.text||')':''; var el=document.getElementById(sel.dataset.syncText); if(el)el.value=name; var also=sel.dataset.syncAlso; if(also){var el2=document.getElementById(also);if(el2)el2.value=name;} }
        sel.addEventListener('change', sync); sync();
    });
    var shiftsData=<?= $_shiftsJson ?? '{}' ?>;
    var shiftSel=document.getElementById('work_shift_id');
    var summaryWrap=document.getElementById('shiftSummaryWrap');
    var summaryTxt=document.getElementById('shiftSummaryText');
    var manualWraps=['manualEntradaWrap','manualSaidaWrap','manualJornadaWrap'].map(function(id){return document.getElementById(id);});
    if(shiftSel){
        function applyShift(){
            var sid=shiftSel.value, s=shiftsData[sid];
            if(s){
                var set=function(id,v){var e=document.getElementById(id);if(e)e.value=v;};
                set('horario_entrada',s.start);set('horario_saida',s.end);set('jornada_trabalho',s.label);
                set('work_schedule_start',s.start);set('work_schedule_end',s.end);
                set('expected_hours_daily',s.daily_h);set('weekly_hours',s.weekly_h);
                if(summaryTxt)summaryTxt.innerHTML='<strong>'+s.name+'</strong> &nbsp;·&nbsp; '+s.start+' às '+s.end+(s.break>0?' · Intervalo: '+s.break+'min':'')+' &nbsp;·&nbsp; '+s.daily_h+'h/dia · '+s.weekly_h+'h/semana';
                if(summaryWrap)summaryWrap.style.display='';
                manualWraps.forEach(function(w){if(w)w.style.display='none';});
            }else{
                if(summaryWrap)summaryWrap.style.display='none';
                manualWraps.forEach(function(w){if(w)w.style.display='';});
            }
        }
        shiftSel.addEventListener('change',applyShift); applyShift();
    }
})();
</script>
