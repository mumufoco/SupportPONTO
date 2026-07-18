<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Saldo de Horas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Saldo de Horas',
        'subtitle' => 'Acompanhe seu banco de horas e desempenho por período.',
        'icon'     => 'bi bi-bar-chart-line-fill',
        'actions'  => [
            ['label' => 'Exportar PDF',   'icon' => 'bi bi-file-earmark-pdf-fill',   'url' => base_url('timesheet/export/pdf?employee_id='   . ($viewingEmployee->id ?? '') . '&period=' . ($period ?? 30)), 'class' => 'sp-page-chip--primary'],
            ['label' => 'Exportar Excel', 'icon' => 'bi bi-file-earmark-excel-fill', 'url' => base_url('timesheet/export/excel?employee_id=' . ($viewingEmployee->id ?? '') . '&period=' . ($period ?? 30)), 'class' => 'sp-page-chip--primary'],
            ['label' => 'Exportar CSV',   'icon' => 'bi bi-filetype-csv',            'url' => base_url('timesheet/export/csv?employee_id='   . ($viewingEmployee->id ?? '') . '&period=' . ($period ?? 30)), 'class' => 'sp-page-chip--primary'],
        ],
    ]) ?>

    <?php if ($balance['balance'] < -10): ?>
        <div class="sp-status-banner error">
            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Atenção!</strong>
            <div>Você possui saldo negativo superior a 10 horas. Entre em contato com o RH para regularizar.</div>
        </div>
    <?php elseif ($balance['balance'] > 40): ?>
        <div class="sp-status-banner warning">
            <strong><i class="bi bi-info-circle-fill me-2"></i>Aviso:</strong>
            <div>Você possui mais de 40 horas extras acumuladas. Considere solicitar compensação.</div>
        </div>
    <?php elseif (!empty($incompleteDays)): ?>
        <div class="sp-status-banner warning">
            <strong><i class="bi bi-exclamation-circle-fill me-2"></i>Marcações incompletas:</strong>
            <div>Você possui <?= count($incompleteDays) ?> dia(s) com marcações incompletas. <a href="<?= base_url('timesheet') ?>">Verificar</a></div>
        </div>
    <?php endif; ?>

    <!-- Employee Selector (managers/admin) -->
    <?php if (in_array(\App\Enums\Role::normalize((string) ($employee['role'] ?? \App\Enums\Role::Funcionario->value))->value, [\App\Enums\Role::Admin->value, \App\Enums\Role::Gestor->value, \App\Enums\Role::RH->value], true) && !empty($employees)): ?>
        <div class="sp-filter-shell">
            <form method="GET" action="<?= base_url('timesheet/balance') ?>" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 small">Visualizar funcionário:</label>
                </div>
                <div class="col-md-4">
                    <select name="employee_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Meu saldo</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= (int) $emp->id ?>" <?= ($viewingEmployee->id == $emp->id) ? 'selected' : '' ?>>
                                <?= esc($emp->name) ?> – <?= esc($emp->position ?? 'N/A') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="period" value="<?= esc($period) ?>">
            </form>
        </div>
    <?php endif; ?>

    <!-- Balance Hero Card -->
    <?php
        $balanceClass  = 'neutral';
        $balanceIcon   = 'bi-hourglass-split';
        $balanceLabel  = 'Saldo de Horas';
        $balanceColor  = 'var(--sp-text-secondary)';
        if ($balance['balance'] > 0) {
            $balanceClass = 'positive'; $balanceIcon = 'bi-arrow-up-circle-fill'; $balanceLabel = 'Horas Extras'; $balanceColor = 'var(--sp-success)';
        } elseif ($balance['balance'] < 0) {
            $balanceClass = 'negative'; $balanceIcon = 'bi-arrow-down-circle-fill'; $balanceLabel = 'Horas Devidas'; $balanceColor = 'var(--sp-danger)';
        }
        $balanceText = ($balance['balance'] > 0 ? '+' : '') . number_format($balance['balance'], 2) . 'h';
    ?>
    <div class="sp-card mb-3">
        <div class="sp-card-body">
            <div class="row align-items-center gy-3">
                <div class="col-md-7 d-flex align-items-center gap-3">
                    <div class="stat-card-icon <?= $balanceClass === 'positive' ? 'success' : ($balanceClass === 'negative' ? 'danger' : 'info') ?>" style="width:64px;height:64px;font-size:2rem;flex-shrink:0;">
                        <i class="bi <?= $balanceIcon ?>"></i>
                    </div>
                    <div>
                        <div class="text-muted small mb-1"><?= esc($viewingEmployee->name ?? '') ?> · <?= esc($viewingEmployee->position ?? 'N/A') ?></div>
                        <div style="font-size:2.5rem;font-weight:700;color:<?= $balanceColor ?>;line-height:1"><?= $balanceText ?></div>
                        <div class="text-muted small mt-1"><?= $balanceLabel ?> · Última atualização: <?= date('d/m/Y H:i') ?></div>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="sp-card p-3 text-center mb-0" style="border-color:var(--sp-success)">
                                <div style="font-size:1.4rem;font-weight:700;color:var(--sp-success)">+<?= number_format($balance['extra'], 2) ?>h</div>
                                <div class="text-muted small">Horas Extras</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="sp-card p-3 text-center mb-0" style="border-color:var(--sp-danger)">
                                <div style="font-size:1.4rem;font-weight:700;color:var(--sp-danger)">-<?= number_format($balance['owed'], 2) ?>h</div>
                                <div class="text-muted small">Horas Devidas</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="sp-grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-calendar-check"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Dias Trabalhados</div>
                <div class="stat-card-value"><?= (int) $statistics['total_days'] ?></div>
                <div class="text-muted small">Últimos <?= $period ?> dias</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-exclamation-circle"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Dias Incompletos</div>
                <div class="stat-card-value"><?= (int) $statistics['incomplete_days'] ?></div>
                <?php if ($statistics['incomplete_days'] > 0): ?>
                    <a href="?irregularities=1&period=<?= $period ?>" class="small">Ver detalhes</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-clock-history"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Média Diária</div>
                <div class="stat-card-value"><?= number_format($statistics['avg_worked'], 2) ?>h</div>
                <div class="text-muted small">Esperado: <?= number_format($statistics['total_expected'] / max($statistics['total_days'], 1), 2) ?>h</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Dias Justificados</div>
                <div class="stat-card-value"><?= (int) $statistics['justified_days'] ?></div>
                <?php if ($statistics['justified_days'] > 0): ?>
                    <div class="text-muted small">Com justificativa aprovada</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-graph-up"></i> Evolução do Saldo</span>
            <div class="d-flex gap-1">
                <?php foreach ([30 => '30 dias', 60 => '60 dias', 90 => '90 dias'] as $days => $label): ?>
                    <a href="?period=<?= $days ?>&employee_id=<?= urlencode((string) ($employee_id ?? '')) ?>"
                       class="btn btn-sm <?= (int) $period === $days ? 'btn-primary' : 'btn-outline-secondary' ?>">
                        <?= $label ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="sp-card-body">
            <div style="position:relative;height:320px">
                <canvas id="balanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-table"></i> Registros Detalhados</span>
            <div class="d-flex gap-1">
                <a href="?period=<?= urlencode((string) $period) ?>&employee_id=<?= urlencode((string) ($employee_id ?? '')) ?>"
                   class="btn btn-sm <?= !$irregularitiesOnly ? 'btn-primary' : 'btn-outline-secondary' ?>">Todos</a>
                <a href="?period=<?= urlencode((string) $period) ?>&irregularities=1&employee_id=<?= urlencode((string) ($employee_id ?? '')) ?>"
                   class="btn btn-sm <?= $irregularitiesOnly ? 'btn-warning' : 'btn-outline-warning' ?>">Irregularidades</a>
            </div>
        </div>
        <div class="sp-card-body p-0">
            <?php if (empty($records)): ?>
                <div class="sp-empty-state m-3">
                    <i class="bi bi-inbox" style="font-size:2.5rem;display:block;margin-bottom:.75rem"></i>
                    Nenhum registro encontrado para o período selecionado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Intervalo</th>
                                <th>Trabalhado</th>
                                <th>Esperado</th>
                                <th>Extra</th>
                                <th>Devidas</th>
                                <th>Status</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($record->date)) ?></strong>
                                        <div class="text-muted small"><?= date('l', strtotime($record->date)) ?></div>
                                    </td>
                                    <td><?= esc($record->first_punch ?? '–') ?></td>
                                    <td><?= esc($record->last_punch  ?? '–') ?></td>
                                    <td><?= number_format($record->total_interval, 2) ?>h</td>
                                    <td><strong><?= number_format($record->total_worked, 2) ?>h</strong></td>
                                    <td><?= number_format($record->expected, 2) ?>h</td>
                                    <td>
                                        <?php if ($record->extra > 0): ?>
                                            <span class="sp-badge sp-badge-success">+<?= number_format($record->extra, 2) ?>h</span>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record->owed > 0): ?>
                                            <span class="sp-badge sp-badge-danger">-<?= number_format($record->owed, 2) ?>h</span>
                                        <?php else: ?>
                                            <span class="text-muted">–</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record->incomplete): ?>
                                            <span class="sp-status-pill is-warning"><i class="bi bi-exclamation-triangle"></i> Incompleto</span>
                                        <?php elseif ($record->justified): ?>
                                            <span class="sp-status-pill is-info"><i class="bi bi-patch-check"></i> Justificado</span>
                                        <?php else: ?>
                                            <span class="sp-status-pill is-success"><i class="bi bi-check-circle"></i> OK</span>
                                        <?php endif; ?>
                                        <?php if ($record->interval_violation > 0): ?>
                                            <span class="sp-status-pill is-danger ms-1"><i class="bi bi-exclamation-circle"></i> Intervalo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?= $record->notes ? esc(mb_substr($record->notes, 0, 50)) . (mb_strlen($record->notes) > 50 ? '…' : '') : '–' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4" class="text-end text-muted small">Totais:</td>
                                <td><?= number_format($statistics['total_worked'],   2) ?>h</td>
                                <td><?= number_format($statistics['total_expected'], 2) ?>h</td>
                                <td class="text-success">+<?= number_format($statistics['total_extra'], 2) ?>h</td>
                                <td class="text-danger">-<?= number_format($statistics['total_owed'],  2) ?>h</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    const evolutionData = <?= json_encode($evolution ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const labels      = evolutionData.map(d => { const dt = new Date(d.date); return dt.toLocaleDateString('pt-BR', {day:'2-digit',month:'2-digit'}); });
    const balanceData = evolutionData.map(d => d.balance);
    const extraData   = evolutionData.map(d => d.extra);
    const owedData    = evolutionData.map(d => d.owed);

    // Dark theme colors matching sp-* variables
    const gridColor = (getComputedStyle(document.documentElement).getPropertyValue('--sp-border').trim() || 'rgba(125,125,125,.15)');
    const textColor = (getComputedStyle(document.documentElement).getPropertyValue('--sp-text-muted').trim() || '#898fa9');

    new Chart(document.getElementById('balanceChart').getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                { label: 'Saldo Total',              data: balanceData, borderColor: '#5b73e8', backgroundColor: 'rgba(91,115,232,.12)', borderWidth: 2, fill: true,  tension: 0.4 },
                { label: 'Horas Extras (acum.)',     data: extraData,   borderColor: '#1abc9c', backgroundColor: 'transparent',           borderWidth: 2, fill: false, tension: 0.4, borderDash: [5,4] },
                { label: 'Horas Devidas (acum.)',    data: owedData,    borderColor: '#e74c3c', backgroundColor: 'transparent',           borderWidth: 2, fill: false, tension: 0.4, borderDash: [5,4] },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { labels: { color: textColor } },
                tooltip: {
                    mode: 'index', intersect: false,
                    callbacks: { label: ctx => { const v = ctx.parsed.y; return ctx.dataset.label + ': ' + (v >= 0 ? '+' : '') + v.toFixed(2) + 'h'; } }
                }
            },
            scales: {
                y: {
                    ticks: { color: textColor, callback: v => v.toFixed(1) + 'h' },
                    grid: { color: ctx => ctx.tick.value === 0 ? 'rgba(125,125,125,.45)' : gridColor }
                },
                x: { ticks: { color: textColor }, grid: { display: false } }
            }
        }
    });
})();
</script>
<?= $this->endSection() ?>
