<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Analytics<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    .chart-container { position: relative; height: 300px; }
    .filter-card { 
        background: linear-gradient(135deg, #9DB89D 0%, #2563EB 100%); 
        color: white; 
        border-radius: 0.75rem;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="mb-4">
        <h2 class="mb-1">
            <i class="fas fa-chart-line me-2"></i>Analytics Dashboard
        </h2>
        <p class="text-muted mb-0">Análise detalhada de métricas e estatísticas</p>
    </div>
    <!-- Filters -->
    <div class="card filter-card mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-filter me-2"></i>Filtros</h5>
            <form method="GET" action="/dashboard/analytics" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="start_date" class="form-control" value="<?= esc($filters['startDate']) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="end_date" class="form-control" value="<?= esc($filters['endDate']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Departamento</label>
                    <select name="department_id" class="form-select">
                        <option value="">Todos os Departamentos</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $filters['departmentId'] == $dept['id'] ? 'selected' : '' ?>><?= esc($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-light w-100"><i class="fas fa-search me-2"></i>Filtrar</button>
                </div>
            </form>
        </div>
    </div>
    <!-- KPIs Row 1 -->
    <div class="dashboard-grid mb-4">
        <?= view('components/kpi', [
            'icon' => 'fas fa-users',
            'iconColor' => 'primary',
            'value' => number_format($kpis['total_employees']),
            'label' => 'Total Funcionários',
            'classes' => 'grid-col-3'
        ]) ?>
        
        <?= view('components/kpi', [
            'icon' => 'fas fa-user-check',
            'iconColor' => 'accent',
            'value' => number_format($kpis['active_employees']),
            'label' => 'Funcionários Ativos',
            'classes' => 'grid-col-3'
        ]) ?>
        
        <?= view('components/kpi', [
            'icon' => 'fas fa-fingerprint',
            'iconColor' => 'warning',
            'value' => number_format($kpis['punches_today']),
            'label' => 'Batidas no Período',
            'classes' => 'grid-col-3'
        ]) ?>
        
        <?= view('components/kpi', [
            'icon' => 'fas fa-clock',
            'iconColor' => 'primary',
            'value' => number_format($kpis['total_hours'], 1) . 'h',
            'label' => 'Total de Horas',
            'classes' => 'grid-col-3'
        ]) ?>
    </div>
    <!-- KPIs Row 2 -->
    <div class="dashboard-grid mb-4">
        <?= view('components/kpi', [
            'icon' => 'fas fa-hourglass-half',
            'iconColor' => 'danger',
            'value' => number_format($kpis['pending_approvals']),
            'label' => 'Aprovações Pendentes',
            'classes' => 'grid-col-4'
        ]) ?>
        
        <?= view('components/kpi', [
            'icon' => 'fas fa-chart-bar',
            'iconColor' => 'primary',
            'value' => number_format($kpis['avg_hours_per_employee'], 1) . 'h',
            'label' => 'Média Horas/Funcionário',
            'classes' => 'grid-col-4'
        ]) ?>
        
        <?= view('components/kpi', [
            'icon' => 'fas fa-percentage',
            'iconColor' => 'accent',
            'value' => number_format($attendance_rate, 1) . '%',
            'label' => 'Taxa de Presença',
            'classes' => 'grid-col-4'
        ]) ?>
    </div>
    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Batidas por Hora
                </div>
                <div class="card-body"><div class="chart-container"><canvas id="punchesByHourChart"></canvas></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>Status dos Funcionários
                </div>
                <div class="card-body"><div class="chart-container"><canvas id="employeeStatusChart"></canvas></div></div>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-bar me-2"></i>Horas por Departamento
                </div>
                <div class="card-body"><div class="chart-container"><canvas id="hoursByDepartmentChart"></canvas></div></div>
            </div>
        </div>
    </div>
    <!-- Tables -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-trophy me-2"></i>Top 10 Funcionários
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-sm mb-0">
                            <thead><tr><th>#</th><th>Nome</th><th>Departamento</th><th class="text-end">Horas</th></tr></thead>
                            <tbody>
                                <?php foreach ($top_employees as $index => $emp): ?>
                                    <tr><td><?= $index + 1 ?></td><td><?= esc($emp['name']) ?></td><td><?= esc($emp['department']) ?></td><td class="text-end"><strong><?= number_format($emp['total_hours'], 1) ?>h</strong></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i>Atividade Recente
                </div>
                <div class="card-body">
                    <?php foreach ($recent_activity as $activity): ?>
                        <div>
                            <strong><?= esc($activity['employee_name']) ?></strong> <small class="text-muted">(<?= esc($activity['department']) ?>)</small><br>
                            <small><i class="fas fa-clock me-1"></i><?= esc($activity['formatted_time']) ?>
                            <?php if ($activity['punch_out_time']): ?>
                                <span class="badge">Completo</span>
                            <?php else: ?>
                                <span class="badge">Em andamento</span>
                            <?php endif; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Export Button -->
    <div class="text-center mb-4">
        <a href="/dashboard/export?<?= http_build_query($filters) ?>" class="btn btn-lg">
            <i class="fas fa-file-csv me-2"></i>Exportar Relatório (CSV)
        </a>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
// Batidas por Hora (Line Chart)
new Chart(document.getElementById('punchesByHourChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?= json_encode($charts['punches_by_hour']['labels']) ?>,
        datasets: [{ 
            label: 'Batidas', 
            data: <?= json_encode($charts['punches_by_hour']['data']) ?>, 
            borderColor: '#9DB89D', 
            backgroundColor: 'rgba(157, 184, 157, 0.1)', 
            tension: 0.4, 
            fill: true 
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: true, position: 'top' }}, 
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }}}
    }
});

// Status dos Funcionários (Pie Chart)
new Chart(document.getElementById('employeeStatusChart').getContext('2d'), {
    type: 'pie',
    data: { 
        labels: <?= json_encode($charts['employee_status']['labels']) ?>, 
        datasets: [{ 
            data: <?= json_encode($charts['employee_status']['data']) ?>, 
            backgroundColor: ['#5A6B5A', '#F4C542', '#E74C3C', '#9DB89D'] 
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { position: 'bottom' }}
    }
});

// Horas por Departamento (Bar Chart)
new Chart(document.getElementById('hoursByDepartmentChart').getContext('2d'), {
    type: 'bar',
    data: { 
        labels: <?= json_encode($charts['hours_by_department']['labels']) ?>, 
        datasets: [{ 
            label: 'Horas Trabalhadas', 
            data: <?= json_encode($charts['hours_by_department']['data']) ?>, 
            backgroundColor: 'rgba(157, 184, 157, 0.5)', 
            borderColor: '#9DB89D', 
            borderWidth: 1 
        }]
    },
    options: { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { display: true, position: 'top' }}, 
        scales: { y: { beginAtZero: true, title: { display: true, text: 'Horas' }}}
    }
});
</script>
<?= $this->endSection() ?>
