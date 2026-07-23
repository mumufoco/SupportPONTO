<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Assiduidade<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Assiduidade e frequência',
        'subtitle' => 'Consolidação mensal de horas trabalhadas, saldo, atrasos e marcações faltantes por colaborador.',
        'icon' => 'bi bi-graph-up-arrow',
        'actions' => [
            ['label' => 'Voltar para relatórios', 'icon' => 'bi bi-arrow-left', 'url' => route_to('reports')],
        ],
    ]) ?>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" action="<?= route_to('reports.attendance') ?>" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="month" class="form-label">Mês</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= esc($month ?? date('Y-m')) ?>">
                </div>
                <div class="col-md-4">
                    <label for="department" class="form-label">Departamento</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($departments ?? []) as $departmentOption): ?>
                            <option value="<?= (int) $departmentOption->id ?>" <?= (string) ($selectedDepartment ?? '') === (string) $departmentOption->id ? 'selected' : '' ?>><?= esc($departmentOption->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Buscar</button>
                    <a href="<?= route_to('reports.attendance') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <?php
        $exportQuery = [
            'month' => $month,
            'department' => $selectedDepartment,
        ];
    ?>
    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-graph-up-arrow"></i></span>
                Assiduidade por colaborador
            </h2>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= route_to('reports.attendance.export.pdf') . '?' . http_build_query($exportQuery) ?>"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
                    <li><a class="dropdown-item" href="<?= route_to('reports.attendance.export.excel') . '?' . http_build_query($exportQuery) ?>"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="<?= route_to('reports.attendance.export.csv') . '?' . http_build_query($exportQuery) ?>"><i class="bi bi-file-earmark-text me-2 text-secondary"></i>CSV</a></li>
                </ul>
            </div>
        </div>
        <div class="sp-data-card__body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Departamento</th>
                            <th>Dias trabalhados</th>
                            <th>Horas</th>
                            <th>Previsto</th>
                            <th>Saldo</th>
                            <th>Atrasos</th>
                            <th>Marcações faltantes</th>
                            <th>Assiduidade</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($attendanceData ?? [])): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">Nenhum dado encontrado para o período informado.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($attendanceData ?? []) as $item): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?= view('components/employee_avatar', ['employeeId' => (int) ($item['employee']->id ?? 0), 'initials' => $item['employee']->name ?? '?', 'size' => 28]) ?>
                                    <?= esc((string) ($item['employee']->name ?? '')) ?>
                                </div>
                            </td>
                            <td><?= esc((string) ($item['employee']->department ?? '')) ?></td>
                            <td><?= esc((string) ($item['days_worked'] ?? 0)) ?></td>
                            <td><?= esc(format_hours((float) ($item['hours_worked'] ?? 0))) ?></td>
                            <td><?= esc(format_hours((float) ($item['expected_hours'] ?? 0))) ?></td>
                            <td><?= esc(format_hours((float) ($item['balance'] ?? 0), true)) ?></td>
                            <td><?= esc((string) ($item['late_arrivals'] ?? 0)) ?></td>
                            <td><?= esc((string) ($item['missing_punches'] ?? 0)) ?></td>
                            <td><span class="badge bg-primary"><?= esc(format_percentage((float) ($item['attendance_rate'] ?? 0), 1)) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
