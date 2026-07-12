<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Novo Turno<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
use App\Libraries\UI\ComponentBuilder;
use App\Libraries\UI\UIHelper;
?>

<!-- Page Header -->
<div class="sp-page-header-block">
    <?= ComponentBuilder::card([
        'content' => UIHelper::flex([
            '<div>
                <h2 class="sp-page-title-modern">
                    <i class="fas fa-clock me-2"></i>Novo Turno de Trabalho
                </h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="' . sp_dashboard_url() . '">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="' . sp_shifts_index_url() . '">Turnos</a></li>
                        <li class="breadcrumb-item active">Novo</li>
                    </ol>
                </nav>
            </div>',
            ComponentBuilder::button([
                'text' => 'Voltar',
                'icon' => 'fa-arrow-left',
                'url' => sp_shifts_index_url(),
                'style' => 'outline-secondary',
            ])
        ], 'between', 'center')
    ]) ?>
</div>

<!-- Form Card -->
<?= ComponentBuilder::card([
    'title' => 'Informações do Turno',
    'icon' => 'fa-info-circle',
    'content' => '
        <form method="POST" action="' . sp_shifts_store_url() . '" id="shiftForm">
            ' . csrf_field() . '
            <div class="row">
                <!-- Nome -->
                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">
                        Nome do Turno <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="name" name="name"
                        value="' . old('name') . '"
                        placeholder="Ex: Manhã Comercial, Plantão Noturno..."
                        required>
                    ' . (isset($errors['name']) ? '<div class="invalid-feedback d-block">' . $errors['name'] . '</div>' : '') . '
                </div>

                <!-- Tipo -->
                <div class="col-md-6 mb-3">
                    <label for="type" class="form-label">
                        Tipo de Turno <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">Selecione...</option>
                        ' . implode('', array_map(function($key, $label) {
                            $selected = old('type') === $key ? 'selected' : '';
                            return '<option value="' . $key . '" ' . $selected . '>' . $label . '</option>';
                        }, array_keys($shiftTypes), array_values($shiftTypes))) . '
                    </select>
                    ' . (isset($errors['type']) ? '<div class="invalid-feedback d-block">' . $errors['type'] . '</div>' : '') . '
                </div>
            </div>

            <div class="row">
                <!-- Horário Início -->
                <div class="col-md-4 mb-3">
                    <label for="start_time" class="form-label">
                        Horário de Início <span class="text-danger">*</span>
                    </label>
                    <input type="time" class="form-control" id="start_time" name="start_time"
                        value="' . old('start_time') . '"
                        required>
                    ' . (isset($errors['start_time']) ? '<div class="invalid-feedback d-block">' . $errors['start_time'] . '</div>' : '') . '
                </div>

                <!-- Horário Fim -->
                <div class="col-md-4 mb-3">
                    <label for="end_time" class="form-label">
                        Horário de Término <span class="text-danger">*</span>
                    </label>
                    <input type="time" class="form-control" id="end_time" name="end_time"
                        value="' . old('end_time') . '"
                        required>
                    ' . (isset($errors['end_time']) ? '<div class="invalid-feedback d-block">' . $errors['end_time'] . '</div>' : '') . '
                    <small class="text-muted">Se o turno termina no dia seguinte, será calculado automaticamente</small>
                </div>

                <!-- Intervalo -->
                <div class="col-md-4 mb-3">
                    <label for="break_duration" class="form-label">
                        Duração do Intervalo (minutos)
                    </label>
                    <input type="number" class="form-control" id="break_duration" name="break_duration"
                        value="' . old('break_duration', '0') . '"
                        min="0" max="480" step="15"
                        placeholder="0">
                    ' . (isset($errors['break_duration']) ? '<div class="invalid-feedback d-block">' . $errors['break_duration'] . '</div>' : '') . '
                    <small class="text-muted">0 = sem intervalo</small>
                </div>
            </div>

            <!-- Horários de Almoço/Intervalo -->
            <div class="row">
                <div class="col-12 mb-1">
                    <p class="mb-1 fw-semibold" style="font-size:.85rem;color:var(--sp-text-muted)">
                        <i class="fas fa-utensils me-1"></i>Horários do intervalo/almoço
                        <small class="fw-normal ms-1">(preencha para registro no ponto)</small>
                    </p>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lunch_start_time" class="form-label">Saída para almoço</label>
                    <input type="time" class="form-control" id="lunch_start_time" name="lunch_start_time"
                        value="' . old('lunch_start_time') . '">
                    <small class="text-muted">Início do intervalo</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="lunch_end_time" class="form-label">Retorno do almoço</label>
                    <input type="time" class="form-control" id="lunch_end_time" name="lunch_end_time"
                        value="' . old('lunch_end_time') . '">
                    <small class="text-muted">Fim do intervalo</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Duração calculada</label>
                    <div class="alert alert-light border mb-0 py-2" id="lunch_duration_display">
                        <small class="text-muted"><i class="fas fa-calculator me-1"></i>Auto</small>
                    </div>
            </div>

            <div class="row">
                <!-- Cor -->
                <div class="col-md-6 mb-3">
                    <label for="color" class="form-label">
                        Cor para Calendário
                    </label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color" id="color" name="color"
                            value="' . old('color', '#6C757D') . '"
                            title="Escolha uma cor">
                        <input type="text" class="form-control" id="color_hex" readonly
                            value="' . old('color', '#6C757D') . '">
                    </div>
                    ' . (isset($errors['color']) ? '<div class="invalid-feedback d-block">' . $errors['color'] . '</div>' : '') . '
                    <small class="text-muted">Cor usada para identificar este turno no calendário</small>
                </div>

                <!-- Duração Calculada -->
                <div class="col-md-6 mb-3">
                    <label class="form-label">Duração Total Estimada</label>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-clock me-2"></i>
                        <span id="duration_display">Preencha os horários</span>
                    </div>
                </div>
            </div>

            <!-- Descrição -->
            <div class="mb-3">
                <label for="description" class="form-label">Descrição</label>
                <textarea class="form-control" id="description" name="description" rows="3"
                    placeholder="Descrição opcional do turno...">' . old('description') . '</textarea>
                ' . (isset($errors['description']) ? '<div class="invalid-feedback d-block">' . $errors['description'] . '</div>' : '') . '
            </div>

            <!-- Actions -->
            <div class="mt-4 pt-3 border-top sp-form-actions-end">
                <a href="' . sp_shifts_index_url() . '" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-2"></i>Cancelar
                </a>
                ' . ComponentBuilder::button([
                    'text' => 'Criar Turno',
                    'icon' => 'fa-save',
                    'style' => 'primary',
                    'type' => 'submit'
                ]) . '
            </div>

        </form>

        <script <?= csp_script_nonce_attr() ?>>
        // Update hex display when color changes
        document.getElementById("color").addEventListener("input", function() {
            document.getElementById("color_hex").value = this.value.toUpperCase();
            calculateDuration();
        });

        // Calculate duration when times change
        document.getElementById("start_time").addEventListener("change", calculateDuration);
        document.getElementById("end_time").addEventListener("change", calculateDuration);
        document.getElementById("break_duration").addEventListener("input", calculateDuration);


        document.getElementById("lunch_start_time").addEventListener("change", calcLunchBreak);
        document.getElementById("lunch_end_time").addEventListener("change", calcLunchBreak);

        function calcLunchBreak() {
            const ls = document.getElementById("lunch_start_time").value;
            const le = document.getElementById("lunch_end_time").value;
            const disp = document.getElementById("lunch_duration_display");
            if (ls && le) {
                const [lsh, lsm] = ls.split(":").map(Number);
                const [leh, lem] = le.split(":").map(Number);
                let lunchMin = (leh * 60 + lem) - (lsh * 60 + lsm);
                if (lunchMin < 0) lunchMin += 1440;
                document.getElementById("break_duration").value = lunchMin;
                const h = Math.floor(lunchMin/60), m = lunchMin % 60;
                if (disp) disp.innerHTML = "<i class=\"fas fa-check-circle text-success me-1\"></i><strong>" + h + "h" + (m > 0 ? " " + m + "min" : "") + "</strong>";
                calculateDuration();
            } else {
                if (disp) disp.innerHTML = "<small class=\"text-muted\"><i class=\"fas fa-calculator me-1\"></i>Auto</small>";
            }
        }

                function calculateDuration() {
            const start = document.getElementById("start_time").value;
            const end = document.getElementById("end_time").value;
            const breakMinutes = parseInt(document.getElementById("break_duration").value) || 0;

            if (!start || !end) {
                document.getElementById("duration_display").textContent = "Preencha os horários";
                return;
            }

            // Convert to minutes
            const [startH, startM] = start.split(":").map(Number);
            const [endH, endM] = end.split(":").map(Number);

            let startMinutes = startH * 60 + startM;
            let endMinutes = endH * 60 + endM;

            // Handle overnight shifts
            if (endMinutes < startMinutes) {
                endMinutes += 24 * 60; // Add 24 hours
            }

            let totalMinutes = endMinutes - startMinutes - breakMinutes;
            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;

            const display = hours + "h" + (minutes > 0 ? " " + minutes + "min" : "");
            document.getElementById("duration_display").innerHTML =
                \'<strong>\' + display + \'</strong> \' +
                (endMinutes >= 24 * 60 ? \'<span class="badge bg-warning ms-2">Turno noturno</span>\' : \'\');
        }
        </script>
    '
]) ?>

<?= $this->endSection() ?>
