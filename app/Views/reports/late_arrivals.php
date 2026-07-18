<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Atrasos<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Relatório de atrasos',
        'subtitle' => 'Identifique colaboradores com atrasos no período e as datas impactadas.',
        'icon' => 'bi bi-alarm',
        'actions' => [
            ['label' => 'Voltar para relatórios', 'icon' => 'bi bi-arrow-left', 'url' => route_to('reports')],
        ],
    ]) ?>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" action="<?= route_to('reports.late_arrivals') ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="employee_id" class="form-label">Colaborador</label>
                    <select id="employee_id" name="employee_id" class="form-select">
                        <option value="">Todos</option>
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
                    <label for="month" class="form-label">Mês</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= esc($month ?? date('Y-m')) ?>">
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
                        <a href="<?= route_to('reports.late_arrivals') ?>" class="btn btn-outline-secondary">Limpar</a>
                    </div>
                    <div class="form-text" style="min-height:1.25rem"></div>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Departamento</th>
                            <th>Total de atrasos</th>
                            <th>Datas registradas</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($lateArrivalsData ?? [])): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Nenhum atraso encontrado para os filtros informados.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lateArrivalsData ?? []) as $item): ?>
                        <tr>
                            <td><?= esc((string) ($item['employee']->name ?? '')) ?></td>
                            <td><?= esc((string) ($item['employee']->department ?? '')) ?></td>
                            <td><span class="sp-badge sp-badge-warning"><?= esc((string) ($item['total_count'] ?? 0)) ?></span></td>
                            <td>
                                <?php
                                    $dates = [];
                                    foreach (($item['late_arrivals'] ?? []) as $late) {
                                        $dateValue = is_object($late) ? ($late->date ?? $late->punch_date ?? null) : ($late['date'] ?? $late['punch_date'] ?? null);
                                        if ($dateValue) {
                                            $dates[] = format_date_br((string) $dateValue);
                                        }
                                    }
                                ?>
                                <?= esc($dates !== [] ? implode(', ', $dates) : 'Sem detalhes') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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

    departmentSelect.addEventListener('change', filterEmployeesByDepartment);
})();
</script>
<?= $this->endSection() ?>
