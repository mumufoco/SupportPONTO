<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Turno<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.sp-form-card {
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.sp-form-card__head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--sp-border);
    background: var(--sp-gray-50, rgba(0,0,0,.02));
}
.sp-form-card__icon {
    width: 2.1rem; height: 2.1rem;
    border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    background: rgba(13,110,253,.12); color: #0d6efd;
}
.sp-form-card__title { font-weight: 700; font-size: 1rem; margin: 0 }
.sp-form-card__sub   { font-size: .78rem; color: var(--sp-text-muted); margin: 0 }
.sp-form-card__body  { padding: 1.5rem }
.sp-form-actions {
    display: flex; justify-content: flex-end; align-items: center; gap: .75rem;
    padding: 1.25rem 1.5rem;
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
</style>

<div class="container-fluid sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => 'Editar Turno: ' . esc($shift->name),
        'subtitle' => 'Atualize os dados deste turno.',
        'icon'     => 'bi bi-pencil-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_shifts_index_url()],
        ],
    ]) ?>

    <form method="POST" action="<?= sp_shifts_update_url($shift->id) ?>" id="shiftForm">
        <?= csrf_field() ?>

        <div class="sp-form-card">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon"><i class="bi bi-info-circle-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Informações do turno</p>
                    <p class="sp-form-card__sub">Nome, tipo, horários e intervalo.</p>
                </div>
            </div>
            <div class="sp-form-card__body">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">
                            Nome do Turno <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="name" name="name"
                               value="<?= esc(old('name', $shift->name)) ?>"
                               placeholder="Ex: Manhã Comercial, Plantão Noturno..."
                               required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label for="type" class="form-label">
                            Tipo de Turno <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($shiftTypes as $key => $label): ?>
                                <option value="<?= esc($key) ?>" <?= old('type', $shift->type) === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="start_time" class="form-label">
                            Horário de Início <span class="text-danger">*</span>
                        </label>
                        <input type="time" class="form-control" id="start_time" name="start_time"
                               value="<?= esc(old('start_time', substr((string) $shift->start_time, 0, 5))) ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="end_time" class="form-label">
                            Horário de Término <span class="text-danger">*</span>
                        </label>
                        <input type="time" class="form-control" id="end_time" name="end_time"
                               value="<?= esc(old('end_time', substr((string) $shift->end_time, 0, 5))) ?>" required>
                        <small class="text-muted">Se o turno termina no dia seguinte, será calculado automaticamente</small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="break_duration" class="form-label">
                            Duração do Intervalo (minutos)
                        </label>
                        <input type="number" class="form-control" id="break_duration" name="break_duration"
                               value="<?= (int) old('break_duration', (int) ($shift->break_duration ?? 0)) ?>"
                               min="0" max="480" step="15"
                               placeholder="0">
                        <small class="text-muted">0 = sem intervalo</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12 mb-1">
                        <p class="mb-1 fw-semibold" style="font-size:.85rem;color:var(--sp-text-muted)">
                            <i class="bi bi-cup-hot-fill me-1"></i>Horários do intervalo/almoço
                            <small class="fw-normal ms-1">(preencha para registro no ponto)</small>
                        </p>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="lunch_start_time" class="form-label">Saída para almoço</label>
                        <input type="time" class="form-control" id="lunch_start_time" name="lunch_start_time"
                               value="<?= esc(old('lunch_start_time', !empty($shift->lunch_start_time) ? substr($shift->lunch_start_time, 0, 5) : '')) ?>">
                        <small class="text-muted">Início do intervalo</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="lunch_end_time" class="form-label">Retorno do almoço</label>
                        <input type="time" class="form-control" id="lunch_end_time" name="lunch_end_time"
                               value="<?= esc(old('lunch_end_time', !empty($shift->lunch_end_time) ? substr($shift->lunch_end_time, 0, 5) : '')) ?>">
                        <small class="text-muted">Fim do intervalo</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Duração calculada</label>
                        <div class="alert alert-light border mb-0 py-2" id="lunch_duration_display">
                            <small class="text-muted"><i class="bi bi-calculator me-1"></i>Auto</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="color" class="form-label">
                            Cor para Calendário
                        </label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="color" name="color"
                                   value="<?= esc(old('color', $shift->color ?? '#6C757D')) ?>"
                                   title="Escolha uma cor">
                            <input type="text" class="form-control" id="color_hex" readonly
                                   value="<?= esc(old('color', $shift->color ?? '#6C757D')) ?>">
                        </div>
                        <small class="text-muted">Cor usada para identificar este turno no calendário</small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="active" class="form-label">Status</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                                   <?= old('active', $shift->active) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="active">
                                <strong>Turno Ativo</strong>
                            </label>
                        </div>
                        <small class="text-muted">Apenas turnos ativos podem ser atribuídos a funcionários</small>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Duração Total Estimada</label>
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-clock-fill me-2"></i>
                            <span id="duration_display">Calculando...</span>
                        </div>
                    </div>
                </div>

                <div class="mb-0">
                    <label for="description" class="form-label">Descrição</label>
                    <textarea class="form-control" id="description" name="description" rows="3"
                              placeholder="Descrição opcional do turno..."><?= esc(old('description', (string) ($shift->description ?? ''))) ?></textarea>
                </div>

            </div>
        </div>

        <div class="sp-form-actions">
            <a href="<?= sp_shifts_show_url($shift->id) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-2"></i>Cancelar
            </a>
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-save-fill me-2"></i>Salvar Alterações
            </button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
document.getElementById('color').addEventListener('input', function() {
    document.getElementById('color_hex').value = this.value.toUpperCase();
});

document.getElementById('start_time').addEventListener('change', calculateDuration);
document.getElementById('end_time').addEventListener('change', calculateDuration);
document.getElementById('break_duration').addEventListener('input', calculateDuration);

document.getElementById('lunch_start_time').addEventListener('change', calcLunchBreak);
document.getElementById('lunch_end_time').addEventListener('change', calcLunchBreak);

function calcLunchBreak() {
    const ls = document.getElementById('lunch_start_time').value;
    const le = document.getElementById('lunch_end_time').value;
    const disp = document.getElementById('lunch_duration_display');
    if (ls && le) {
        const [lsh, lsm] = ls.split(':').map(Number);
        const [leh, lem] = le.split(':').map(Number);
        let lunchMin = (leh * 60 + lem) - (lsh * 60 + lsm);
        if (lunchMin < 0) lunchMin += 1440;
        document.getElementById('break_duration').value = lunchMin;
        const h = Math.floor(lunchMin / 60), m = lunchMin % 60;
        if (disp) disp.innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i><strong>' + h + 'h' + (m > 0 ? ' ' + m + 'min' : '') + '</strong>';
        calculateDuration();
    } else {
        if (disp) disp.innerHTML = '<small class="text-muted"><i class="bi bi-calculator me-1"></i>Auto</small>';
    }
}

function calculateDuration() {
    const start = document.getElementById('start_time').value;
    const end = document.getElementById('end_time').value;
    const breakMinutes = parseInt(document.getElementById('break_duration').value) || 0;

    if (!start || !end) {
        document.getElementById('duration_display').textContent = 'Preencha os horários';
        return;
    }

    const [startH, startM] = start.split(':').map(Number);
    const [endH, endM] = end.split(':').map(Number);

    let startMinutes = startH * 60 + startM;
    let endMinutes = endH * 60 + endM;

    if (endMinutes < startMinutes) {
        endMinutes += 24 * 60;
    }

    let totalMinutes = endMinutes - startMinutes - breakMinutes;
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;

    const display = hours + 'h' + (minutes > 0 ? ' ' + minutes + 'min' : '');
    document.getElementById('duration_display').innerHTML =
        '<strong>' + display + '</strong> ' +
        (endMinutes >= 24 * 60 ? '<span class="badge bg-warning ms-2">Turno noturno</span>' : '');
}

// Calcular duração (turno e intervalo de almoço) com os valores já preenchidos ao carregar a página
window.addEventListener('DOMContentLoaded', function () {
    calcLunchBreak();
    calculateDuration();
});
</script>
<?= $this->endSection() ?>
