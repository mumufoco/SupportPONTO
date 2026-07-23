<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Justificativas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Relatório de justificativas',
        'subtitle' => 'Acompanhe solicitações do período com filtros por colaborador e departamento.',
        'icon' => 'bi bi-file-earmark-text',
        'actions' => [
            ['label' => 'Voltar para relatórios', 'icon' => 'bi bi-arrow-left', 'url' => route_to('reports')],
        ],
    ]) ?>

    <div class="sp-grid-4 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-file-earmark-text"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Total</div>
                <div class="stat-card-value"><?= esc((string) ($summary['total'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Pendentes</div>
                <div class="stat-card-value"><?= esc((string) ($summary['pending'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Aprovadas</div>
                <div class="stat-card-value"><?= esc((string) ($summary['approved'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Rejeitadas</div>
                <div class="stat-card-value"><?= esc((string) ($summary['rejected'] ?? 0)) ?></div>
            </div>
        </div>
    </div>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" action="<?= route_to('reports.justifications') ?>" class="row g-3">
                <div class="col-md-4">
                    <label for="employee_id" class="form-label">Colaborador</label>
                    <select id="employee_id" name="employee_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($employees ?? []) as $emp): ?>
                            <option value="<?= (int) $emp->id ?>"
                                    data-department-id="<?= (int) ($emp->department_id ?? 0) ?>"
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
                            <option value="<?= (int) $departmentOption->id ?>" <?= (string) ($selectedDepartment ?? '') === (string) $departmentOption->id ? 'selected' : '' ?>><?= esc($departmentOption->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" style="min-height:1.25rem">Filtra as opções do campo Colaborador</div>
                </div>
                <div class="col-md-2 d-flex flex-column">
                    <label class="form-label" style="visibility:hidden;">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Buscar</button>
                        <a href="<?= route_to('reports.justifications') ?>" class="btn btn-outline-secondary">Limpar</a>
                    </div>
                    <div class="form-text" style="min-height:1.25rem"></div>
                </div>
            </form>
        </div>
    </div>

    <?php
        $exportQuery = [
            'month' => $month,
            'department' => $selectedDepartment,
            'employee_id' => $selectedEmployee,
        ];
    ?>
    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-file-earmark-text"></i></span>
                Justificativas do período
            </h2>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= route_to('reports.justifications.export.pdf') . '?' . http_build_query($exportQuery) ?>"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
                    <li><a class="dropdown-item" href="<?= route_to('reports.justifications.export.excel') . '?' . http_build_query($exportQuery) ?>"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="<?= route_to('reports.justifications.export.csv') . '?' . http_build_query($exportQuery) ?>"><i class="bi bi-file-earmark-text me-2 text-secondary"></i>CSV</a></li>
                </ul>
            </div>
        </div>
        <div class="sp-data-card__body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Colaborador</th>
                            <th>Departamento</th>
                            <th>Motivo</th>
                            <th>Status</th>
                            <th>Solicitada em</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($justifications ?? [])): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma justificativa encontrada para os filtros informados.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($justifications ?? []) as $item): ?>
                        <?php
                            $status = (string) ($item->status ?? 'pendente');
                            $badgeClass = match ($status) {
                                'aprovado' => 'sp-badge-success',
                                'rejeitado' => 'sp-badge-danger',
                                default => 'sp-badge-warning',
                            };
                            $statusLabel = match ($status) {
                                'aprovado' => 'Aprovada',
                                'rejeitado' => 'Rejeitada',
                                default => 'Pendente',
                            };
                        ?>
                        <tr>
                            <td><?= esc(format_date_br((string) ($item->justification_date ?? $item->date ?? ''))) ?></td>
                            <td><?= esc((string) ($item->employee_name ?? '')) ?></td>
                            <td><?= esc((string) ($item->employee_department ?? '')) ?></td>
                            <td><?= esc((string) ($item->reason ?? $item->description ?? 'Sem descrição')) ?></td>
                            <td><span class="sp-badge <?= $badgeClass ?>"><?= esc($statusLabel) ?></span></td>
                            <td><?= esc(format_datetime_br((string) ($item->created_at ?? ''), false)) ?></td>
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
            var show = !dept || opt.dataset.departmentId === dept;
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
