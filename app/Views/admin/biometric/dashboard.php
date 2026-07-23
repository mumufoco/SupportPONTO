<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Dashboard Biométrico') ?> - SupportPONTO</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">
                    <i class="bi bi-fingerprint"></i>
                    Dashboard - Biometria Digital
                </h1>
                <p class="text-muted">Sistema de gerenciamento de impressões digitais</p>
            </div>
            <div class="col-auto">
                <button onclick="window.location.href='<?= base_url('admin/biometric/export-stats') ?>'" class="btn btn-outline-primary">
                    <i class="bi bi-download"></i> Exportar Estatísticas
                </button>
                <a href="<?= base_url('admin') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <!-- Total Templates -->
            <div class="col-md-3">
                <div class="card sp-stat-accent sp-stat-accent-primary h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Templates Ativos</h6>
                                <h2 class="card-title mb-0"><?= number_format($statistics['total_templates']) ?></h2>
                            </div>
                            <i class="bi bi-fingerprint display-6 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Users -->
            <div class="col-md-3">
                <div class="card sp-stat-accent sp-stat-accent-success h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Usuários Cadastrados</h6>
                                <h2 class="card-title mb-0"><?= number_format($statistics['total_users']) ?></h2>
                            </div>
                            <i class="bi bi-people display-6 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Average Quality -->
            <div class="col-md-3">
                <div class="card sp-stat-accent sp-stat-accent-warning h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Qualidade Média</h6>
                                <h2 class="card-title mb-0"><?= number_format($statistics['avg_quality'], 1) ?></h2>
                            </div>
                            <i class="bi bi-star display-6 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollments Last 30 Days -->
            <div class="col-md-3">
                <div class="card sp-stat-accent sp-stat-accent-purple h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Cadastros (30 dias)</h6>
                                <h2 class="card-title mb-0"><?= number_format($statistics['enrollments_30_days']) ?></h2>
                            </div>
                            <i class="bi bi-calendar-plus display-6 text-primary opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Authentication Statistics -->
        <div class="row g-3 mb-4">
            <!-- Success Today -->
            <div class="col-md-4">
                <div class="card sp-stat-accent sp-stat-accent-teal h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Sucessos Hoje</h6>
                                <h2 class="card-title mb-0 text-success"><?= number_format($statistics['success_today']) ?></h2>
                            </div>
                            <i class="bi bi-check-circle display-6 text-success opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Failed Today -->
            <div class="col-md-4">
                <div class="card sp-stat-accent sp-stat-accent-danger h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Falhas Hoje</h6>
                                <h2 class="card-title mb-0 text-danger"><?= number_format($statistics['failed_today']) ?></h2>
                            </div>
                            <i class="bi bi-x-circle display-6 text-danger opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Duplicate Attempts -->
            <div class="col-md-4">
                <div class="card sp-stat-accent sp-stat-accent-orange h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle mb-2 text-muted">Tentativas Duplicadas</h6>
                                <h2 class="card-title mb-0 text-warning"><?= number_format($statistics['duplicate_attempts']) ?></h2>
                            </div>
                            <i class="bi bi-exclamation-triangle display-6 text-warning opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-3 mb-4">
            <!-- Quality Distribution -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-bar-chart"></i>
                            Distribuição de Qualidade
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="qualityChart" height="200"></canvas>
                        
                        <div class="mt-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span><span class="badge bg-success">Excelente (80-100)</span></span>
                                <strong><?= (int) $qualityDistribution['excellent'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><span class="badge bg-info">Bom (60-79)</span></span>
                                <strong><?= (int) $qualityDistribution['good'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span><span class="badge bg-warning text-dark">Regular (40-59)</span></span>
                                <strong><?= (int) $qualityDistribution['fair'] ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><span class="badge bg-danger">Baixo (30-39)</span></span>
                                <strong><?= (int) $qualityDistribution['poor'] ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Department Distribution -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-pie-chart"></i>
                            Templates por Departamento
                        </h6>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Enrollments -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history"></i>
                            Cadastros Recentes
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Colaborador</th>
                                        <th>Dedo</th>
                                        <th>Qualidade</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentEnrollments)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Nenhum cadastro recente</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentEnrollments as $enrollment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= esc($enrollment->name) ?></strong><br>
                                                    <small class="text-muted"><?= esc($enrollment->cpf) ?></small>
                                                </td>
                                                <td><?= esc($enrollment->finger_position ?? 'N/A') ?></td>
                                                <td>
                                                    <?php
                                                    $quality = $enrollment->quality_score ?? 0;
                                                    $badge = 'secondary';
                                                    if ($quality >= 80) $badge = 'success';
                                                    elseif ($quality >= 60) $badge = 'info';
                                                    elseif ($quality >= 40) $badge = 'warning';
                                                    elseif ($quality >= 30) $badge = 'danger';
                                                    ?>
                                                    <span class="badge bg-<?= $badge ?>"><?= number_format($quality, 1) ?></span>
                                                </td>
                                                <td>
                                                    <small><?= date('d/m/Y H:i', strtotime($enrollment->enrolled_at)) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Authentications -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-shield-check"></i>
                            Autenticações Recentes
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Colaborador</th>
                                        <th>Ação</th>
                                        <th>Status</th>
                                        <th>Data</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentAuthentications)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">Nenhuma autenticação recente</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentAuthentications as $auth): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= esc($auth->name ?? 'N/A') ?></strong>
                                                </td>
                                                <td>
                                                    <?php
                                                    $actionLabel = [
                                                        'BIOMETRIC_AUTH_SUCCESS' => 'Verificação 1:1',
                                                        'BIOMETRIC_AUTH_FAILED' => 'Verificação 1:1',
                                                        'BIOMETRIC_IDENTIFY_SUCCESS' => 'Identificação 1:N',
                                                        'BIOMETRIC_IDENTIFY_FAILED' => 'Identificação 1:N',
                                                    ];
                                                    echo $actionLabel[$auth->action] ?? esc($auth->action);
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if (strpos($auth->action, 'SUCCESS') !== false): ?>
                                                        <span class="badge bg-success">Sucesso</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Falhou</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= date('d/m/Y H:i', strtotime($auth->created_at)) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script <?= csp_script_nonce_attr() ?>>
        // Quality Distribution Chart
        const qualityCtx = document.getElementById('qualityChart').getContext('2d');
        new Chart(qualityCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excelente', 'Bom', 'Regular', 'Baixo'],
                datasets: [{
                    data: [
                        <?= (int) ($qualityDistribution['excellent'] ?? 0) ?>,
                        <?= (int) ($qualityDistribution['good'] ?? 0) ?>,
                        <?= (int) ($qualityDistribution['fair'] ?? 0) ?>,
                        <?= (int) ($qualityDistribution['poor'] ?? 0) ?>
                    ],
                    backgroundColor: [
                        '#198754',
                        '#0dcaf0',
                        '#ffc107',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Department Distribution Chart
        const deptCtx = document.getElementById('departmentChart').getContext('2d');
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($departmentDistribution), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                datasets: [{
                    label: 'Colaboradores com Biometria',
                    data: <?= json_encode(array_values($departmentDistribution), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
                    backgroundColor: '#0d6efd'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
