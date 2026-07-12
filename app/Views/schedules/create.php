<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nova Escala<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
use App\Libraries\UI\ComponentBuilder;
use App\Libraries\UI\UIHelper;
?>

<!-- Page Header -->
<div class="sp-page-section">
    <?= ComponentBuilder::card([
        'content' => UIHelper::flex([
            '<div>
                <h2 class="sp-page-title">
                    <i class="fas fa-calendar-plus me-2"></i>Nova Escala de Trabalho
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="' . sp_dashboard_url() . '">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="' . sp_schedules_index_url() . '">Escalas</a></li>
                        <li class="breadcrumb-item active">Nova</li>
                    </ol>
                </nav>
            </div>',
            ComponentBuilder::button([
                'text' => 'Voltar',
                'icon' => 'fa-arrow-left',
                'url' => sp_schedules_index_url(),
                'style' => 'outline-secondary',
            ])
        ], 'between', 'center')
    ]) ?>
</div>

<div class="sp-grid-main">

    <!-- Left Column: Form -->
    <div>
        <?= ComponentBuilder::card([
            'title' => 'Informações da Escala',
            'icon' => 'fa-info-circle',
            'content' => '
                <form method="POST" action="' . sp_schedules_store_url() . '" id="scheduleForm">
                    ' . csrf_field() . '
                    <div class="row">
                        <!-- Employee Selection -->
                        <div class="col-md-12 mb-3">
                            <label for="employee_id" class="form-label">
                                Funcionário <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Selecione um funcionário...</option>
                                ' . implode('', array_map(function($employee) {
                                    $selected = old('employee_id') == $employee->id ? 'selected' : '';
                                    return '<option value="' . (int) $employee->id . '" ' . $selected . '
                                        data-department="' . esc($employee->department) . '"
                                        data-position="' . esc($employee->position) . '">
                                        ' . esc($employee->name) . ' - ' . esc($employee->position) . ' (' . esc($employee->department) . ')
                                    </option>';
                                }, $employees)) . '
                            </select>
                            ' . (isset($errors['employee_id']) ? '<div class="invalid-feedback d-block">' . $errors['employee_id'] . '</div>' : '') . '
                        </div>
                    </div>

                    <div class="row">
                        <!-- Shift Selection -->
                        <div class="col-md-6 mb-3">
                            <label for="shift_id" class="form-label">
                                Turno <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="shift_id" name="shift_id" required>
                                <option value="">Selecione um turno...</option>
                                ' . implode('', array_map(function($shift) {
                                    $selected = old('shift_id') == $shift->id ? 'selected' : '';
                                    return '<option value="' . (int) $shift->id . '" ' . $selected . '
                                        data-color="' . esc($shift->color ?? '#6C757D') . '"
                                        data-start="' . esc(substr((string) $shift->start_time, 0, 5)) . '"
                                        data-end="' . esc(substr((string) $shift->end_time, 0, 5)) . '">
                                        ' . esc($shift->name) . ' (' . esc(substr((string) $shift->start_time, 0, 5)) . ' - ' . esc(substr((string) $shift->end_time, 0, 5)) . ')
                                    </option>';
                                }, $shifts)) . '
                            </select>
                            ' . (isset($errors['shift_id']) ? '<div class="invalid-feedback d-block">' . $errors['shift_id'] . '</div>' : '') . '
                        </div>

                        <!-- Date -->
                        <div class="col-md-6 mb-3">
                            <label for="date" class="form-label">
                                Data <span class="text-danger">*</span>
                            </label>
                            <input type="date" class="form-control" id="date" name="date"
                                value="' . esc($dateDefault) . '"
                                min="' . date('Y-m-d') . '"
                                required>
                            ' . (isset($errors['date']) ? '<div class="invalid-feedback d-block">' . $errors['date'] . '</div>' : '') . '
                        </div>
                    </div>

                    <!-- Recurring Schedule -->
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" value="1"
                                    ' . (old('is_recurring') ? 'checked' : '') . '>
                                <label class="form-check-label" for="is_recurring">
                                    <strong>Escala Recorrente</strong>
                                    <br><small class="text-muted">Criar automaticamente esta escala toda semana no mesmo dia até a data final</small>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Recurring Options (hidden by default) -->
                    <div id="recurring_options" class="sp-chat-hidden">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            A escala será criada automaticamente toda semana no dia <strong id="weekday_display"></strong> até a data final especificada.
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="recurrence_end_date" class="form-label">
                                    Data Final da Recorrência <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="recurrence_end_date" name="recurrence_end_date"
                                    value="' . old('recurrence_end_date') . '"
                                    min="' . date('Y-m-d', strtotime('+7 days')) . '">
                                ' . (isset($errors['recurrence_end_date']) ? '<div class="invalid-feedback d-block">' . $errors['recurrence_end_date'] . '</div>' : '') . '
                                <small class="text-muted">Até quando esta escala deve se repetir semanalmente</small>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"
                            placeholder="Observações sobre esta escala (opcional)...">' . old('notes') . '</textarea>
                        ' . (isset($errors['notes']) ? '<div class="invalid-feedback d-block">' . $errors['notes'] . '</div>' : '') . '
                    </div>

                    <!-- Actions -->
                    <div class="mt-4 pt-3 border-top sp-page-actions justify-content-end">
                        <a href="' . sp_schedules_index_url() . '" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </a>
                        ' . ComponentBuilder::button([
                            'text' => 'Criar Escala',
                            'icon' => 'fa-save',
                            'style' => 'primary',
                            'type' => 'submit'
                        ]) . '
                    </div>

                </form>

                <script <?= csp_script_nonce_attr() ?>>
                const weekdays = ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"];

                // Toggle recurring options
                document.getElementById("is_recurring").addEventListener("change", function() {
                    const recurringOptions = document.getElementById("recurring_options");
                    const endDateInput = document.getElementById("recurrence_end_date");

                    if (this.checked) {
                        recurringOptions.style.display = "block";
                        endDateInput.required = true;
                        updateWeekdayDisplay();
                    } else {
                        recurringOptions.style.display = "none";
                        endDateInput.required = false;
                    }
                });

                // Update weekday display when date changes
                document.getElementById("date").addEventListener("change", function() {
                    if (document.getElementById("is_recurring").checked) {
                        updateWeekdayDisplay();
                    }
                });

                function updateWeekdayDisplay() {
                    const dateInput = document.getElementById("date");
                    if (!dateInput.value) return;

                    const date = new Date(dateInput.value + "T00:00:00");
                    const weekday = weekdays[date.getDay()];
                    document.getElementById("weekday_display").textContent = weekday;

                    // Set minimum end date to next week
                    const minEndDate = new Date(date);
                    minEndDate.setDate(minEndDate.getDate() + 7);
                    document.getElementById("recurrence_end_date").min = minEndDate.toISOString().split("T")[0];
                }

                // Initialize if recurring is already checked
                if (document.getElementById("is_recurring").checked) {
                    document.getElementById("recurring_options").style.display = "block";
                    document.getElementById("recurrence_end_date").required = true;
                    updateWeekdayDisplay();
                }

                // Highlight selected shift color
                document.getElementById("shift_id").addEventListener("change", function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption.value) {
                        const color = selectedOption.getAttribute("data-color");
                        this.style.borderLeft = "4px solid " + color;
                    } else {
                        this.style.borderLeft = "";
                    }
                });
                </script>
            '
        ]) ?>
    </div>

    <!-- Right Column: Info & Tips -->
    <div>
        <!-- Selected Shift Preview -->
        <div id="shift_preview" class="sp-chat-hidden">
            <?= ComponentBuilder::card([
                'title' => 'Pré-visualização do Turno',
                'icon' => 'fa-eye',
                'class' => 'mb-3',
                'content' => '
                    <div class="text-center p-3">
                        <div id="shift_color" class="sp-color-chip-round sp-shift-preview-color-default"></div>
                        <h5 id="shift_name">Selecione um turno</h5>
                        <p class="text-muted mb-0" id="shift_time">--:-- até --:--</p>
                    </div>
                '
            ]) ?>
        </div>

        <!-- Help Card -->
        <?= ComponentBuilder::card([
            'title' => 'Dicas',
            'icon' => 'fa-lightbulb',
            'content' => '
                <ul class="list-unstyled mb-0 sp-line-height-relaxed">
                    <li><i class="fas fa-check text-success me-2"></i>Selecione o funcionário e o turno desejado</li>
                    <li><i class="fas fa-check text-success me-2"></i>Escolha a data da escala</li>
                    <li><i class="fas fa-check text-success me-2"></i>Use <strong>Escala Recorrente</strong> para criar automaticamente</li>
                    <li><i class="fas fa-info text-info me-2"></i>O sistema verifica conflitos automaticamente</li>
                    <li><i class="fas fa-warning text-warning me-2"></i>Não é possível criar escalas duplicadas</li>
                </ul>
            '
        ]) ?>

        <!-- Quick Link -->
        <?= ComponentBuilder::card([
            'title' => 'Ações Rápidas',
            'icon' => 'fa-bolt',
            'class' => 'mt-3',
            'content' => '
                <div class="sp-actions-stack">
                    ' . ComponentBuilder::button([
                        'text' => 'Atribuição em Massa',
                        'icon' => 'fa-users-cog',
                        'url' => sp_schedules_bulk_assign_url(),
                        'style' => 'outline-primary',
                        'class' => 'w-100'
                    ]) . '
                    ' . ComponentBuilder::button([
                        'text' => 'Ver Calendário',
                        'icon' => 'fa-calendar',
                        'url' => sp_schedules_index_url(),
                        'style' => 'outline-secondary',
                        'class' => 'w-100'
                    ]) . '
                </div>
            '
        ]) ?>
    </div>

</div>

<script <?= csp_script_nonce_attr() ?>>
// Update shift preview when shift is selected
document.getElementById("shift_id").addEventListener("change", function() {
    const selectedOption = this.options[this.selectedIndex];
    const preview = document.getElementById("shift_preview");

    if (selectedOption.value) {
        const color = selectedOption.getAttribute("data-color");
        const start = selectedOption.getAttribute("data-start");
        const end = selectedOption.getAttribute("data-end");
        const name = selectedOption.textContent.split("(")[0].trim();

        document.getElementById("shift_color").style.background = color;
        document.getElementById("shift_name").textContent = name;
        document.getElementById("shift_time").textContent = start + " até " + end;

        preview.style.display = "block";
    } else {
        preview.style.display = "none";
    }
});

// Trigger on page load if shift is pre-selected
window.addEventListener("DOMContentLoaded", function() {
    const shiftSelect = document.getElementById("shift_id");
    if (shiftSelect.value) {
        shiftSelect.dispatchEvent(new Event("change"));
    }
});
</script>

<?= $this->endSection() ?>
