<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nova Escala<?= $this->endSection() ?>

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
.sp-shift-color-chip {
    width: 3.5rem; height: 3.5rem; border-radius: 50%;
    margin: 0 auto .75rem; border: 3px solid var(--sp-border);
    background: var(--sp-gray-300);
}
</style>

<div class="container-fluid sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => 'Nova Escala de Trabalho',
        'subtitle' => 'Atribua um turno a um colaborador em uma data específica.',
        'icon'     => 'bi bi-calendar-plus-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_schedules_index_url()],
        ],
    ]) ?>

    <div class="sp-grid-main">
        <!-- Coluna esquerda: formulário -->
        <div>
            <form method="POST" action="<?= sp_schedules_store_url() ?>" id="scheduleForm">
                <?= csrf_field() ?>

                <div class="sp-form-card">
                    <div class="sp-form-card__head">
                        <div class="sp-form-card__icon"><i class="bi bi-info-circle-fill"></i></div>
                        <div>
                            <p class="sp-form-card__title">Informações da escala</p>
                            <p class="sp-form-card__sub">Colaborador, turno e data.</p>
                        </div>
                    </div>
                    <div class="sp-form-card__body">

                        <div class="row">
                            <div class="col-12 mb-3">
                                <label for="employee_id" class="form-label">
                                    Colaborador <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Selecione um colaborador...</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?= (int) $employee->id ?>"
                                                <?= old('employee_id') == $employee->id ? 'selected' : '' ?>
                                                data-department="<?= esc($employee->department) ?>"
                                                data-position="<?= esc($employee->position) ?>">
                                            <?= esc($employee->name) ?> - <?= esc($employee->position) ?> (<?= esc($employee->department) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="shift_id" class="form-label">
                                    Turno <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="shift_id" name="shift_id" required>
                                    <option value="">Selecione um turno...</option>
                                    <?php foreach ($shifts as $shift): ?>
                                        <?php $shiftStart = substr((string) $shift->start_time, 0, 5); $shiftEnd = substr((string) $shift->end_time, 0, 5); ?>
                                        <option value="<?= (int) $shift->id ?>"
                                                <?= old('shift_id') == $shift->id ? 'selected' : '' ?>
                                                data-color="<?= esc($shift->color ?? '#6C757D') ?>"
                                                data-start="<?= esc($shiftStart) ?>"
                                                data-end="<?= esc($shiftEnd) ?>">
                                            <?= esc($shift->name) ?> (<?= esc($shiftStart) ?> - <?= esc($shiftEnd) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">
                                    Data <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="date" name="date"
                                       value="<?= esc(old('date', $dateDefault)) ?>"
                                       min="<?= date('Y-m-d') ?>"
                                       required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" value="1"
                                           <?= old('is_recurring') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_recurring">
                                        <strong>Escala Recorrente</strong>
                                        <br><small class="text-muted">Criar automaticamente esta escala toda semana no mesmo dia até a data final</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div id="recurring_options" style="display:none;">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                A escala será criada automaticamente toda semana no dia <strong id="weekday_display"></strong> até a data final especificada.
                            </div>

                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="recurrence_end_date" class="form-label">
                                        Data Final da Recorrência <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="recurrence_end_date" name="recurrence_end_date"
                                           value="<?= esc(old('recurrence_end_date')) ?>"
                                           min="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                                    <small class="text-muted">Até quando esta escala deve se repetir semanalmente</small>
                                </div>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="notes" class="form-label">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Observações sobre esta escala (opcional)..."><?= esc(old('notes')) ?></textarea>
                        </div>

                    </div>
                </div>

                <div class="sp-form-actions">
                    <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-2"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="bi bi-save-fill me-2"></i>Criar Escala
                    </button>
                </div>
            </form>
        </div>

        <!-- Coluna direita: pré-visualização e dicas -->
        <div>
            <div id="shift_preview" class="sp-card" style="display:none;">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-eye-fill"></i> Pré-visualização do Turno</span>
                </div>
                <div class="sp-card-body text-center">
                    <div id="shift_color" class="sp-shift-color-chip"></div>
                    <h5 id="shift_name">Selecione um turno</h5>
                    <p class="text-muted mb-0" id="shift_time">--:-- até --:--</p>
                </div>
            </div>

            <div class="sp-card">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-lightbulb-fill"></i> Dicas</span>
                </div>
                <div class="sp-card-body">
                    <ul class="list-unstyled mb-0" style="line-height:1.9;">
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Selecione o colaborador e o turno desejado</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Escolha a data da escala</li>
                        <li><i class="bi bi-check-circle-fill text-success me-2"></i>Use <strong>Escala Recorrente</strong> para criar automaticamente</li>
                        <li><i class="bi bi-info-circle-fill text-info me-2"></i>O sistema verifica conflitos automaticamente</li>
                        <li><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Não é possível criar escalas duplicadas</li>
                    </ul>
                </div>
            </div>

            <div class="sp-card">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-lightning-fill"></i> Ações Rápidas</span>
                </div>
                <div class="sp-card-body d-grid gap-2">
                    <a href="<?= sp_schedules_bulk_assign_url() ?>" class="btn btn-outline-primary w-100">
                        <i class="bi bi-people-fill me-1"></i>Atribuição em Massa
                    </a>
                    <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-calendar3 me-1"></i>Ver Calendário
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
const weekdays = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

document.getElementById('is_recurring').addEventListener('change', function() {
    const recurringOptions = document.getElementById('recurring_options');
    const endDateInput = document.getElementById('recurrence_end_date');

    if (this.checked) {
        recurringOptions.style.display = 'block';
        endDateInput.required = true;
        updateWeekdayDisplay();
    } else {
        recurringOptions.style.display = 'none';
        endDateInput.required = false;
    }
});

document.getElementById('date').addEventListener('change', function() {
    if (document.getElementById('is_recurring').checked) {
        updateWeekdayDisplay();
    }
});

function updateWeekdayDisplay() {
    const dateInput = document.getElementById('date');
    if (!dateInput.value) return;

    const date = new Date(dateInput.value + 'T00:00:00');
    const weekday = weekdays[date.getDay()];
    document.getElementById('weekday_display').textContent = weekday;

    const minEndDate = new Date(date);
    minEndDate.setDate(minEndDate.getDate() + 7);
    document.getElementById('recurrence_end_date').min = minEndDate.toISOString().split('T')[0];
}

if (document.getElementById('is_recurring').checked) {
    document.getElementById('recurring_options').style.display = 'block';
    document.getElementById('recurrence_end_date').required = true;
    updateWeekdayDisplay();
}

document.getElementById('shift_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.value) {
        const color = selectedOption.getAttribute('data-color');
        this.style.borderLeft = '4px solid ' + color;
    } else {
        this.style.borderLeft = '';
    }
});

document.getElementById('shift_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const preview = document.getElementById('shift_preview');

    if (selectedOption.value) {
        const color = selectedOption.getAttribute('data-color');
        const start = selectedOption.getAttribute('data-start');
        const end = selectedOption.getAttribute('data-end');
        const name = selectedOption.textContent.split('(')[0].trim();

        document.getElementById('shift_color').style.background = color;
        document.getElementById('shift_name').textContent = name;
        document.getElementById('shift_time').textContent = start + ' até ' + end;

        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

window.addEventListener('DOMContentLoaded', function() {
    const shiftSelect = document.getElementById('shift_id');
    if (shiftSelect.value) {
        shiftSelect.dispatchEvent(new Event('change'));
    }
});
</script>
<?= $this->endSection() ?>
