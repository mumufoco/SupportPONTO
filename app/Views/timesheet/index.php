<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Espelho de ponto<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $activeMonth = (string) ($selectedMonth ?? date('Y-m'));
    $monthStart  = $activeMonth . '-01';
    $monthEnd    = date('Y-m-d', strtotime($monthStart . ' +1 month -1 day'));
    $exportQuery = http_build_query(array_filter([
        'start_date'  => $monthStart,
        'end_date'    => $monthEnd,
        'employee_id' => (int) ($targetEmployeeId ?? 0) > 0 ? (int) $targetEmployeeId : null,
    ]));

    $rawBalance     = (float) ($summary['balance'] ?? 0);
    $balanceDisplay = ($rawBalance > 0 ? '+' : '') . number_format($rawBalance, 2) . 'h';
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Espelho de ponto',
        'subtitle' => 'Consulte o período desejado, acompanhe indicadores mensais e exporte dados rapidamente.',
        'icon' => 'bi bi-calendar-range-fill',
        'actions' => [
            ['label' => 'Exportar Excel', 'icon' => 'bi bi-file-earmark-excel-fill', 'url' => base_url('timesheet/history/export/excel?' . $exportQuery), 'class' => 'sp-page-chip--primary'],
            ['label' => 'Exportar PDF',   'icon' => 'bi bi-file-earmark-pdf-fill',   'url' => base_url('timesheet/history/export/pdf?' . $exportQuery),   'class' => 'sp-page-chip--primary'],
            ['label' => 'Exportar CSV',   'icon' => 'bi bi-filetype-csv',            'url' => base_url('timesheet/history/export/csv?' . $exportQuery),   'class' => 'sp-page-chip--primary'],
            ['label' => 'Histórico', 'icon' => 'bi bi-clock-history', 'url' => sp_timesheet_history_url()],
            ['label' => 'Por Cliente', 'icon' => 'bi bi-building-fill-check', 'url' => site_url('timesheet/por-cliente')],
        ],
    ]) ?>

    <div class="sp-filter-shell">
        <form action="<?= sp_timesheet_index_url() ?>" method="GET" class="row g-3 align-items-end">
            <?php if (!empty($isManager) && !empty($employeesList)): ?>
                <div class="col-md-4">
                    <label for="employee_id" class="form-label mb-0 fw-semibold">Colaborador</label>
                    <select class="form-select" id="employee_id" name="employee_id">
                        <?php foreach ($employeesList as $emp): ?>
                            <option value="<?= (int) ($emp->id ?? $emp['id'] ?? 0) ?>"
                                <?= ((int) ($targetEmployeeId ?? 0) === (int) ($emp->id ?? $emp['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= esc($emp->name ?? $emp['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-4">
                <label for="month" class="form-label mb-0 fw-semibold">Selecionar período</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= esc($activeMonth) ?>" max="<?= date('Y-m') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Buscar
                </button>
            </div>
        </form>
    </div>

    <?php if (!empty($warning)): ?>
        <div class="sp-status-banner warning">
            <strong><i class="bi bi-info-circle-fill me-2"></i>Aviso:</strong>
            <div><?= esc($warning) ?></div>
        </div>
    <?php endif; ?>

    <div class="sp-kpi-grid">
        <div><?= view('components/kpi', ['icon' => 'fas fa-clock', 'iconColor' => 'primary', 'value' => ($summary['total_hours'] ?? '0.00') . 'h', 'label' => 'Horas trabalhadas', 'indicator' => 'Período atual', 'indicatorType' => 'neutral']) ?></div>
        <div><?= view('components/kpi', ['icon' => 'fas fa-calendar-check', 'iconColor' => 'success', 'value' => $summary['days_worked'] ?? 0, 'label' => 'Dias trabalhados', 'indicator' => 'Com registro', 'indicatorType' => 'success']) ?></div>
        <div><?= view('components/kpi', ['icon' => 'fas fa-balance-scale', 'iconColor' => 'warning', 'value' => $balanceDisplay, 'label' => 'Saldo do período', 'indicator' => 'Banco de horas', 'indicatorType' => 'neutral']) ?></div>
        <div><?= view('components/kpi', ['icon' => 'fas fa-exclamation-triangle', 'iconColor' => 'danger', 'value' => $summary['inconsistencies'] ?? 0, 'label' => 'Inconsistências', 'indicator' => 'Requer análise', 'indicatorType' => (($summary['inconsistencies'] ?? 0) > 0 ? 'warning' : 'success')]) ?></div>
    </div>

    <div class="sp-profile-card">
        <div class="sp-profile-card__header">
            <h2 class="sp-profile-card__title"><i class="bi bi-table"></i>Lançamentos do período</h2>
        </div>
        <div class="sp-profile-card__body">
            <?php if (empty($dailyRecords ?? [])): ?>
                <div class="sp-empty-state">Nenhum lançamento encontrado para o período informado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Horas</th>
                                <th>Situação</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($dailyRecords ?? []) as $day): ?>
                                <tr>
                                    <td><?= esc($day['date_formatted'] ?? $day['date'] ?? '-') ?></td>
                                    <td><?= esc($day['entrada'] ?? '-') ?></td>
                                    <td><?= esc($day['saida'] ?? '-') ?></td>
                                    <td><?= esc($day['total_hours'] ?? '-') ?>h</td>
                                    <td>
                                        <?php if (!empty($day['missing_punches'])): ?>
                                            <span class="sp-badge sp-badge-warning">Incompleto</span>
                                        <?php elseif (!empty($day['entrada_late'])): ?>
                                            <span class="sp-badge sp-badge-warning">Atraso</span>
                                        <?php else: ?>
                                            <span class="sp-badge sp-badge-success">OK</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php
                                        $dayUrl = sp_timesheet_day_url((string) ($day['date'] ?? ''))
                                            . (((int) ($targetEmployeeId ?? 0) > 0) ? '?employee_id=' . (int) $targetEmployeeId : '');
                                    ?>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <a href="<?= $dayUrl ?>" class="icon-action" title="Ver dia">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
