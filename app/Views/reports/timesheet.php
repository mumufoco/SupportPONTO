<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Espelho consolidado<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Espelho consolidado de ponto',
        'subtitle' => 'Resumo mensal por colaborador com horas previstas, saldo, justificativas e validação dos registros.',
        'icon' => 'bi bi-calendar-range',
        'actions' => [
            ['label' => 'Voltar para relatórios', 'icon' => 'bi bi-arrow-left', 'url' => route_to('reports')],
        ],
    ]) ?>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" action="<?= route_to('reports.timesheet') ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="employee_id" class="form-label">Colaborador <span class="text-danger">*</span></label>
                    <select id="employee_id" name="employee_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach (($employees ?? []) as $emp): ?>
                            <option value="<?= (int) $emp->id ?>"
                                    data-department="<?= esc((string) ($emp->department ?? '')) ?>"
                                    <?= (string) ($selectedEmployee ?? '') === (string) $emp->id ? 'selected' : '' ?>>
                                <?= esc($emp->name) ?><?= !empty($emp->department) ? ' — ' . esc($emp->department) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" style="min-height:1.25rem"></div>
                </div>
                <div class="col-md-3">
                    <label for="month" class="form-label">Mês <span class="text-danger">*</span></label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= esc($month ?? date('Y-m')) ?>" required>
                    <div class="form-text" style="min-height:1.25rem"></div>
                </div>
                <div class="col-md-3">
                    <label for="department" class="form-label">Departamento</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($departments ?? []) as $departmentOption): ?>
                            <option value="<?= esc((string) $departmentOption) ?>" <?= ($selectedDepartment ?? '') === $departmentOption ? 'selected' : '' ?>><?= esc((string) $departmentOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" style="min-height:1.25rem">Filtra as opções do campo Colaborador</div>
                </div>
                <div class="col-md-2 d-flex flex-column">
                    <label class="form-label" style="visibility:hidden;">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Buscar</button>
                        <a href="<?= route_to('reports.timesheet') ?>" class="btn btn-outline-secondary">Limpar</a>
                    </div>
                    <div class="form-text" style="min-height:1.25rem"></div>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($selectedEmployee)): ?>
        <div class="sp-empty">
            <div class="sp-empty-icon"><i class="bi bi-person-lines-fill"></i></div>
            <p class="sp-empty-title">Selecione um colaborador para ver o espelho de ponto</p>
            <p class="text-muted small mb-0">Use os filtros acima para escolher o mês e o colaborador desejado.</p>
        </div>
    <?php elseif (empty($timesheets ?? [])): ?>
        <div class="alert alert-info">Nenhum espelho encontrado para o colaborador e mês informados.</div>
    <?php endif; ?>

    <?php foreach (($timesheets ?? []) as $sheet): ?>
        <?php $employeeData = $sheet['employee']; $summary = $sheet['summary']; ?>
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title">
                    <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-person-badge-fill"></i></span>
                    <?= esc((string) ($employeeData->name ?? 'Colaborador')) ?>
                </h2>
                <div class="text-muted small"><?= esc((string) ($employeeData->department ?? 'Sem departamento')) ?></div>
            </div>
            <div class="sp-data-card__body">
                <div class="sp-grid-4 mb-3">
                    <div class="stat-card">
                        <div class="stat-card-icon primary"><i class="bi bi-clock-fill"></i></div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">Horas trabalhadas</div>
                            <div class="stat-card-value"><?= esc(format_hours((float) ($summary['total_hours'] ?? 0))) ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon info"><i class="bi bi-calendar-check-fill"></i></div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">Horas previstas</div>
                            <div class="stat-card-value"><?= esc(format_hours((float) ($summary['expected_hours'] ?? 0))) ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon <?= (float) ($summary['balance'] ?? 0) < 0 ? 'warning' : 'success' ?>"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">Saldo</div>
                            <div class="stat-card-value"><?= esc(format_hours((float) ($summary['balance'] ?? 0), true)) ?></div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-icon primary"><i class="bi bi-calendar3"></i></div>
                        <div class="stat-card-content">
                            <div class="stat-card-label">Dias com jornada</div>
                            <div class="stat-card-value"><?= esc((string) ($summary['days_worked'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th rowspan="2">Data</th>
                                <th rowspan="2">Dia</th>
                                <th rowspan="2">Entrada</th>
                                <th colspan="2" class="text-center">Intervalo</th>
                                <th rowspan="2">Saída</th>
                                <th rowspan="2">Horas</th>
                                <th rowspan="2">Previsto</th>
                                <th rowspan="2">Saldo</th>
                                <th rowspan="2" class="text-center">Justificativas</th>
                                <th rowspan="2">Validação</th>
                            </tr>
                            <tr>
                                <th class="text-center small">Início</th>
                                <th class="text-center small">Fim</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($sheet['daily_records'] ?? []) as $record): ?>
                            <?php
                                $justificationCount = is_array($record['justifications'] ?? null) ? count($record['justifications']) : 0;
                                $validation = $record['validation'] ?? [];
                                $isValid = (bool) ($validation['valid'] ?? false);
                                $validationMessage = $validation['message'] ?? ($isValid ? 'OK' : 'Inconsistente');

                                $punchColumns = [
                                    'entrada' => [],
                                    'intervalo_inicio' => [],
                                    'intervalo_fim' => [],
                                    'saida' => [],
                                ];
                                foreach (($record['punches'] ?? []) as $p) {
                                    $ptype = (string) ($p['type'] ?? '');
                                    if (array_key_exists($ptype, $punchColumns)) {
                                        $punchColumns[$ptype][] = format_time((string) ($p['time'] ?? ''), false);
                                    }
                                }
                            ?>
                            <tr>
                                <td><?= esc(format_date_br((string) $record['date'])) ?></td>
                                <td><?= esc(get_day_of_week_br((string) $record['date'], true)) ?></td>
                                <?php foreach (['entrada', 'intervalo_inicio', 'intervalo_fim', 'saida'] as $col): ?>
                                    <td class="text-center">
                                        <?php if ($punchColumns[$col] === []): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <?= implode('<br>', array_map('esc', $punchColumns[$col])) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?= esc(format_hours((float) ($record['hours_worked'] ?? 0))) ?></td>
                                <td><?= esc(format_hours((float) ($record['expected_hours'] ?? 0))) ?></td>
                                <td><?= esc(format_hours((float) ($record['balance'] ?? 0), true)) ?></td>
                                <td class="text-center"><?= esc((string) $justificationCount) ?></td>
                                <td><span class="sp-badge <?= $isValid ? 'sp-badge-success' : 'sp-badge-warning' ?>"><?= esc((string) $validationMessage) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    var departmentSelect = document.getElementById('department');
    var employeeSelect = document.getElementById('employee_id');

    function filterEmployeesByDepartment() {
        var dept = departmentSelect.value;
        var currentValue = employeeSelect.value;
        var currentStillVisible = false;

        Array.prototype.forEach.call(employeeSelect.options, function (opt) {
            if (!opt.value) return;
            var show = !dept || opt.dataset.department === dept;
            opt.hidden = !show;
            opt.disabled = !show;
            if (show && opt.value === currentValue) currentStillVisible = true;
        });

        if (!currentStillVisible) {
            employeeSelect.value = '';
        }
    }

    // So filtra a partir de uma interacao do usuario - no carregamento inicial, o
    // colaborador ja selecionado (via GET) deve continuar visivel mesmo que o
    // departamento marcado no formulario nao bata (evita a tabela ja carregada
    // pelo servidor ficar dessincronizada do dropdown).
    departmentSelect.addEventListener('change', filterEmployeesByDepartment);
})();
</script>
<?= $this->endSection() ?>
