<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Relatório por Cliente<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Relatório por Cliente',
        'subtitle' => 'Espelho consolidado de ponto agrupado por unidade de trabalho / cliente.',
        'icon'     => 'bi bi-building-fill-check',
        'actions'  => [
            ['label' => 'Espelho individual', 'icon' => 'bi bi-calendar-range-fill', 'url' => sp_timesheet_index_url()],
            ['label' => 'Histórico',          'icon' => 'bi bi-clock-history',       'url' => sp_timesheet_history_url()],
            ['label' => 'Colaboradores',      'icon' => 'bi bi-people-fill',         'url' => site_url('admin/employees/overview')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- Filtros -->
    <div class="sp-filter-shell mb-4">
        <form method="GET" action="<?= site_url('timesheet/por-cliente') ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold mb-1" for="work_unit_id">Cliente / Unidade</label>
                <select class="form-select" id="work_unit_id" name="work_unit_id">
                    <option value="">— Todos os clientes —</option>
                    <?php foreach ($workUnits as $wu): ?>
                    <option value="<?= (int)$wu->id ?>"
                            <?= ((int)($selectedWorkUnitId ?? 0) === (int)$wu->id) ? 'selected' : '' ?>>
                        <?= esc($wu->name) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold mb-1" for="month">Período</label>
                <input type="month" class="form-control" id="month" name="month"
                       value="<?= esc($selectedMonth ?? date('Y-m')) ?>"
                       max="<?= date('Y-m') ?>">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i>Buscar
                </button>
                <a href="<?= site_url('timesheet/por-cliente') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Limpar
                </a>
            </div>
            <?php if (!empty($selectedWorkUnitId) || empty($workUnits) === false): ?>
            <div class="col-md-auto d-flex gap-2 ms-auto">
                <a href="<?= site_url('timesheet/por-cliente/export?work_unit_id=' . ($selectedWorkUnitId ?? '') . '&month=' . ($selectedMonth ?? date('Y-m')) . '&format=excel') ?>"
                   class="btn btn-outline-success">
                    <i class="bi bi-file-earmark-excel-fill me-1"></i>Excel
                </a>
                <a href="<?= site_url('timesheet/por-cliente/export?work_unit_id=' . ($selectedWorkUnitId ?? '') . '&month=' . ($selectedMonth ?? date('Y-m')) . '&format=pdf') ?>"
                   class="btn btn-outline-danger">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i>PDF
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($selectedWorkUnit)): ?>
    <!-- KPIs resumo do cliente -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-primary"><?= count($clientData) ?></div>
                <div class="small text-muted">Colaboradores</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-success"><?= $totals['total_hours'] ?>h</div>
                <div class="small text-muted">Horas totais</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold text-info"><?= $totals['days_worked'] ?></div>
                <div class="small text-muted">Dias trabalhados</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="fs-2 fw-bold <?= $totals['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $totals['balance'] >= 0 ? '+' : '' ?><?= number_format($totals['balance'], 2) ?>h
                </div>
                <div class="small text-muted">Saldo total</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabela principal -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
            <span class="fw-semibold">
                <i class="bi bi-table me-2"></i>
                <?php if (!empty($selectedWorkUnit)): ?>
                    <?= esc($selectedWorkUnit->name) ?> — <?= esc($selectedMonth ?? date('Y-m')) ?>
                <?php else: ?>
                    Todos os clientes — <?= esc($selectedMonth ?? date('Y-m')) ?>
                <?php endif; ?>
            </span>
            <span class="badge text-bg-secondary"><?= count($clientData) ?> colaboradores</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($clientData)): ?>
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Nenhum colaborador encontrado para o filtro selecionado.
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Unidade / Cliente</th>
                            <th class="text-center">Dias trab.</th>
                            <th class="text-center">Horas trab.</th>
                            <th class="text-center">Horas prev.</th>
                            <th class="text-center">Saldo</th>
                            <th class="text-center">Atrasos</th>
                            <th class="text-end pe-3">Detalhe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientData as $row): ?>
                        <tr>
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($row['employee']->photo_path)): ?>
                                    <img src="<?= base_url($row['employee']->photo_path) ?>"
                                         alt="" class="rounded-circle"
                                         style="width:32px;height:32px;object-fit:cover;flex-shrink:0">
                                    <?php else: ?>
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                         style="width:32px;height:32px;font-size:.8rem">
                                        <?= mb_substr($row['employee']->name ?? 'C', 0, 1) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold small"><?= esc($row['employee']->name ?? '–') ?></div>
                                        <div class="text-muted" style="font-size:.72rem"><?= esc($row['employee']->position ?? '–') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge text-bg-light border text-dark small">
                                    <?= esc($row['employee']->work_unit ?? '–') ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold"><?= $row['summary']['days_worked'] ?? 0 ?></span>
                            </td>
                            <td class="text-center">
                                <span class="fw-semibold text-primary"><?= $row['summary']['total_hours'] ?? '0.00' ?>h</span>
                            </td>
                            <td class="text-center text-muted small">
                                <?= $row['summary']['expected_hours'] ?? '–' ?>h
                            </td>
                            <td class="text-center">
                                <?php $bal = (float)($row['summary']['balance'] ?? 0); ?>
                                <span class="fw-semibold <?= $bal >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= $bal >= 0 ? '+' : '' ?><?= number_format($bal, 2) ?>h
                                </span>
                            </td>
                            <td class="text-center">
                                <?php $late = (int)($row['summary']['late_arrivals'] ?? 0); ?>
                                <?php if ($late > 0): ?>
                                    <span class="badge text-bg-warning"><?= $late ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <a href="<?= site_url('timesheet/employee/' . ($row['employee']->id ?? '')) . '?month=' . ($selectedMonth ?? date('Y-m')) ?>"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($selectedWorkUnit)): ?>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td class="ps-3" colspan="2">TOTAL — <?= esc($selectedWorkUnit->name) ?></td>
                            <td class="text-center"><?= $totals['days_worked'] ?></td>
                            <td class="text-center text-primary"><?= $totals['total_hours'] ?>h</td>
                            <td class="text-center text-muted"><?= $totals['expected_hours'] ?>h</td>
                            <td class="text-center <?= $totals['balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= $totals['balance'] >= 0 ? '+' : '' ?><?= number_format($totals['balance'], 2) ?>h
                            </td>
                            <td class="text-center"><?= $totals['late_arrivals'] ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($workUnits)): ?>
    <div class="alert alert-info mt-3">
        <i class="bi bi-info-circle me-2"></i>
        Nenhuma unidade de trabalho cadastrada. Cadastre em
        <a href="<?= site_url('settings/work-units') ?>">Configurações &rsaquo; Unidades</a>.
    </div>
    <?php endif; ?>

</div>
<?= $this->endSection() ?>
