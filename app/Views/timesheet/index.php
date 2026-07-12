<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Espelho de ponto<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Espelho de ponto',
        'subtitle' => 'Consulte o período desejado, acompanhe indicadores mensais e exporte dados rapidamente.',
        'icon' => 'bi bi-calendar-range-fill',
        'actions' => [
            ['label' => 'Exportar Excel', 'icon' => 'bi bi-file-earmark-excel-fill', 'url' => sp_timesheet_export_excel_url((string) ($selectedMonth ?? date('Y-m')))],
            ['label' => 'Exportar PDF', 'icon' => 'bi bi-file-earmark-pdf-fill', 'url' => sp_timesheet_export_pdf_url((string) ($selectedMonth ?? date('Y-m')))],
            ['label' => 'Histórico', 'icon' => 'bi bi-clock-history', 'url' => sp_timesheet_history_url()],
            ['label' => 'Por Cliente', 'icon' => 'bi bi-building-fill-check', 'url' => site_url('timesheet/por-cliente')],
        ],
    ]) ?>

    <div class="sp-filter-shell">
        <form action="<?= sp_timesheet_index_url() ?>" method="GET" class="row g-3 align-items-center">
            <div class="col-md-4">
                <label for="month" class="form-label mb-0 fw-semibold">Selecionar período</label>
                <input type="month" class="form-control" id="month" name="month" value="<?= $selectedMonth ?? date('Y-m') ?>" max="<?= date('Y-m') ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Buscar
                </button>
            </div>
        </form>
    </div>

    <div class="sp-kpi-grid">
        <div><?= view('components/kpi', ['icon' => 'fas fa-clock', 'iconColor' => 'primary', 'value' => ($summary['total_hours'] ?? '0.00') . 'h', 'label' => 'Horas trabalhadas', 'indicator' => 'Período atual', 'indicatorType' => 'neutral']) ?></div>
        <div><?= view('components/kpi', ['icon' => 'fas fa-calendar-check', 'iconColor' => 'success', 'value' => $summary['days_worked'] ?? 0, 'label' => 'Dias trabalhados', 'indicator' => 'Com registro', 'indicatorType' => 'success']) ?></div>
        <div><?= view('components/kpi', ['icon' => 'fas fa-balance-scale', 'iconColor' => 'warning', 'value' => ($summary['balance'] ?? '0.00') . 'h', 'label' => 'Saldo do período', 'indicator' => 'Banco de horas', 'indicatorType' => 'neutral']) ?></div>
        <div><?= view('components/kpi', ['icon' => 'fas fa-exclamation-triangle', 'iconColor' => 'danger', 'value' => $summary['inconsistencies'] ?? 0, 'label' => 'Inconsistências', 'indicator' => 'Requer análise', 'indicatorType' => (($summary['inconsistencies'] ?? 0) > 0 ? 'warning' : 'success')]) ?></div>
    </div>

    <div class="sp-profile-card">
        <div class="sp-profile-card__header">
            <h2 class="sp-profile-card__title"><i class="bi bi-table"></i>Lançamentos do período</h2>
        </div>
        <div class="sp-profile-card__body">
            <?php if (empty($days ?? [])): ?>
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
                                <th>Saldo</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($days ?? []) as $day): ?>
                                <tr>
                                    <td><?= esc($day['date_formatted'] ?? $day['date'] ?? '-') ?></td>
                                    <td><?= esc($day['first_punch'] ?? '-') ?></td>
                                    <td><?= esc($day['last_punch'] ?? '-') ?></td>
                                    <td><?= esc($day['hours_worked'] ?? '-') ?></td>
                                    <td><?= esc($day['balance'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <a href="<?= sp_timesheet_day_url((string) ($day['date'] ?? '')) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
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
