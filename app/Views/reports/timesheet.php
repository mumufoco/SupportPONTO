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
            <form method="get" action="<?= route_to('reports.timesheet') ?>" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label">Mês</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= esc($month ?? date('Y-m')) ?>">
                </div>
                <div class="col-md-3">
                    <label for="department" class="form-label">Departamento</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($departments ?? []) as $departmentOption): ?>
                            <option value="<?= esc((string) $departmentOption) ?>" <?= ($selectedDepartment ?? '') === $departmentOption ? 'selected' : '' ?>><?= esc((string) $departmentOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="employee_id" class="form-label">Colaborador</label>
                    <input type="number" id="employee_id" name="employee_id" class="form-control" value="<?= esc((string) ($selectedEmployee ?? '')) ?>" placeholder="ID opcional">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Atualizar</button>
                    <a href="<?= route_to('reports.timesheet') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($timesheets ?? [])): ?>
        <div class="alert alert-info">Nenhum espelho encontrado para os filtros informados.</div>
    <?php endif; ?>

    <?php foreach (($timesheets ?? []) as $sheet): ?>
        <?php $employeeData = $sheet['employee']; $summary = $sheet['summary']; ?>
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-person-badge"></i><?= esc((string) ($employeeData->name ?? 'Colaborador')) ?></h2>
                <div class="text-muted small"><?= esc((string) ($employeeData->department ?? 'Sem departamento')) ?></div>
            </div>
            <div class="sp-data-card__body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Horas trabalhadas</div><strong><?= esc(format_hours((float) ($summary['total_hours'] ?? 0))) ?></strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Horas previstas</div><strong><?= esc(format_hours((float) ($summary['expected_hours'] ?? 0))) ?></strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Saldo</div><strong><?= esc(format_hours((float) ($summary['balance'] ?? 0), true)) ?></strong></div></div>
                    <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Dias com jornada</div><strong><?= esc((string) ($summary['days_worked'] ?? 0)) ?></strong></div></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Dia</th>
                                <th>Marcações</th>
                                <th>Horas</th>
                                <th>Previsto</th>
                                <th>Saldo</th>
                                <th>Justificativas</th>
                                <th>Validação</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($sheet['daily_records'] ?? []) as $record): ?>
                            <?php
                                $punches = array_map(static fn(array $p): string => strtoupper((string) ($p['type'] ?? '')) . ' ' . format_time((string) ($p['time'] ?? ''), false), $record['punches'] ?? []);
                                $justificationCount = is_array($record['justifications'] ?? null) ? count($record['justifications']) : 0;
                                $validation = $record['validation'] ?? [];
                                $isValid = (bool) ($validation['valid'] ?? false);
                                $validationMessage = $validation['message'] ?? ($isValid ? 'OK' : 'Inconsistente');
                            ?>
                            <tr>
                                <td><?= esc(format_date_br((string) $record['date'])) ?></td>
                                <td><?= esc(get_day_of_week_br((string) $record['date'], true)) ?></td>
                                <td><?= esc($punches !== [] ? implode(' | ', $punches) : 'Sem registros') ?></td>
                                <td><?= esc(format_hours((float) ($record['hours_worked'] ?? 0))) ?></td>
                                <td><?= esc(format_hours((float) ($record['expected_hours'] ?? 0))) ?></td>
                                <td><?= esc(format_hours((float) ($record['balance'] ?? 0), true)) ?></td>
                                <td><?= esc((string) $justificationCount) ?></td>
                                <td><span class="badge <?= $isValid ? 'bg-success' : 'bg-warning text-dark' ?>"><?= esc((string) $validationMessage) ?></span></td>
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
