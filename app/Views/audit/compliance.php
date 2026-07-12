<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1"><i class="fas fa-balance-scale text-primary"></i> Conformidade MTE 671/2021</h2>
                    <p class="text-muted">Verificação de conformidade com a Portaria MTE 671/2021 (REP-P)</p>
                </div>
                <div>
                    <a href="<?= site_url('audit') ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-left-<?= $compliance['nsr_continuity'] ? 'success' : 'danger' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">NSR Sequencial</div>
                            <div class="h5 mb-0 font-weight-bold <?= $compliance['nsr_continuity'] ? 'text-success' : 'text-danger' ?>">
                                <?= $compliance['nsr_continuity'] ? 'Conforme' : 'Gaps Detectados' ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-<?= $compliance['nsr_continuity'] ? 'check-circle text-success' : 'exclamation-triangle text-danger' ?> fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-left-<?= $compliance['hash_integrity'] ? 'success' : 'danger' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Integridade Hash</div>
                            <div class="h5 mb-0 font-weight-bold <?= $compliance['hash_integrity'] ? 'text-success' : 'text-danger' ?>">
                                <?= $compliance['hash_integrity'] ? 'Conforme' : $compliance['hash_issues_count'] . ' Falhas' ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-<?= $compliance['hash_integrity'] ? 'shield-alt text-success' : 'shield-alt text-danger' ?> fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-left-<?= $compliance['timezone_valid'] ? 'success' : 'warning' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Fuso Horário BR</div>
                            <div class="h6 mb-0 font-weight-bold <?= $compliance['timezone_valid'] ? 'text-success' : 'text-warning' ?>">
                                <?= esc($compliance['timezone'] ?? '') ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-clock fa-2x <?= $compliance['timezone_valid'] ? 'text-success' : 'text-warning' ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm border-left-<?= $compliance['employees_without_pis'] == 0 ? 'success' : 'warning' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">PIS Cadastrado</div>
                            <div class="h5 mb-0 font-weight-bold <?= $compliance['employees_without_pis'] == 0 ? 'text-success' : 'text-warning' ?>">
                                <?= $compliance['employees_without_pis'] == 0 ? 'Todos OK' : $compliance['employees_without_pis'] . ' Pendentes' ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-id-card fa-2x <?= $compliance['employees_without_pis'] == 0 ? 'text-success' : 'text-warning' ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-left-<?= ($compliance['nsr_fallback_events_count'] ?? 0) === 0 ? 'success' : 'danger' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Contingência NSR (30 dias)</div>
                            <div class="h5 mb-0 font-weight-bold <?= ($compliance['nsr_fallback_events_count'] ?? 0) === 0 ? 'text-success' : 'text-danger' ?>">
                                <?= ($compliance['nsr_fallback_events_count'] ?? 0) === 0 ? 'Sem fallback' : ($compliance['nsr_fallback_events_count'] . ' eventos') ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-life-ring fa-2x <?= ($compliance['nsr_fallback_events_count'] ?? 0) === 0 ? 'text-success' : 'text-danger' ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-left-<?= (($compliance['nsr_counter_health']['status'] ?? 'error') === 'ok') ? 'success' : ((($compliance['nsr_counter_health']['status'] ?? 'error') === 'warning') ? 'warning' : 'danger') ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Saúde do contador NSR</div>
                            <div class="h6 mb-0 font-weight-bold">
                                <?= esc((string) ($compliance['nsr_counter_health']['message'] ?? 'Indisponível')) ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-microchip fa-2x <?= (($compliance['nsr_counter_health']['status'] ?? 'error') === 'ok') ? 'text-success' : ((($compliance['nsr_counter_health']['status'] ?? 'error') === 'warning') ? 'text-warning' : 'text-danger') ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm border-left-<?= empty($compliance['nsr_duplicate_issues'] ?? []) ? 'success' : 'danger' ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-xs font-weight-bold text-uppercase mb-1">Duplicidade de NSR</div>
                            <div class="h5 mb-0 font-weight-bold <?= empty($compliance['nsr_duplicate_issues'] ?? []) ? 'text-success' : 'text-danger' ?>">
                                <?= empty($compliance['nsr_duplicate_issues'] ?? []) ? 'Nenhuma' : count($compliance['nsr_duplicate_issues']) . ' casos' ?>
                            </div>
                        </div>
                        <div>
                            <i class="fas fa-copy fa-2x <?= empty($compliance['nsr_duplicate_issues'] ?? []) ? 'text-success' : 'text-danger' ?>"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-download"></i> Exportar AFD (Arquivo Fonte de Dados)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">O AFD é o arquivo exigido pela Portaria 671/2021 para fiscalização. Contém todas as marcações de ponto brutas com NSR, PIS do trabalhador, data/hora e tipo de marcação.</p>
                    <form action="<?= site_url('audit/afd') ?>" method="GET" class="row">
                        <div class="col-md-5">
                            <label for="date_from">Data Início</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="date_to">Data Fim</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="fas fa-download"></i> Gerar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Requisitos Portaria 671/2021</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-sort-numeric-up text-primary"></i> NSR (Número Sequencial de Registro)</span>
                            <span class="badge badge-<?= $compliance['nsr_continuity'] ? 'success' : 'danger' ?>"><?= $compliance['nsr_continuity'] ? 'OK' : 'Atenção' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-fingerprint text-primary"></i> Hash de Integridade</span>
                            <span class="badge badge-<?= $compliance['hash_integrity'] ? 'success' : 'danger' ?>"><?= $compliance['hash_integrity'] ? 'OK' : 'Atenção' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clock text-primary"></i> Sincronismo Hora Legal BR</span>
                            <span class="badge badge-<?= $compliance['timezone_valid'] ? 'success' : 'warning' ?>"><?= $compliance['timezone_valid'] ? 'OK' : 'Verificar' ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-database text-primary"></i> Armazenamento 5 Anos</span>
                            <span class="badge badge-success">Configurado</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-file-alt text-primary"></i> Geração AFD</span>
                            <span class="badge badge-success">Disponível</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-life-ring text-primary"></i> Contingência formal rastreada</span>
                            <span class="badge badge-<?= (($compliance['nsr_fallback_events_count'] ?? 0) === 0) ? 'success' : 'warning' ?>"><?= (($compliance['nsr_fallback_events_count'] ?? 0) === 0) ? 'OK' : 'Revisar' ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($compliance['nsr_issues'])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-danger">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Gaps no NSR Detectados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>NSR Anterior</th>
                                    <th>NSR Próximo</th>
                                    <th>Gap</th>
                                    <th>Data Aproximada</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($compliance['nsr_issues'] as $issue): ?>
                                <tr>
                                    <td><?= (int) $issue['gap_from'] ?></td>
                                    <td><?= (int) $issue['gap_to'] ?></td>
                                    <td class="text-danger font-weight-bold"><?= (int) $issue['gap_to'] - (int) $issue['gap_from'] - 1 ?> registros</td>
                                    <td><?= date('d/m/Y H:i', strtotime($issue['date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-info-circle"></i> <strong>Importante:</strong> Gaps no NSR podem indicar registros excluídos ou problemas de sincronização. Isso pode ser questionado em fiscalização do MTE.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <?php if (($compliance['nsr_fallback_events_count'] ?? 0) > 0 || !empty($compliance['nsr_duplicate_issues'] ?? [])): ?>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0"><i class="fas fa-shield-alt"></i> Contingência regulatória e reconciliação</h5>
                </div>
                <div class="card-body">
                    <?php if (($compliance['nsr_fallback_events_count'] ?? 0) > 0): ?>
                        <div class="alert alert-danger">
                            <strong>Fallback de NSR acionado:</strong>
                            <?= (int) ($compliance['nsr_fallback_events_count'] ?? 0) ?> evento(s) nos últimos 30 dias.
                            <?php if (!empty($compliance['nsr_latest_fallback_event']['recorded_at'])): ?>
                                Último em <?= date('d/m/Y H:i', strtotime((string) $compliance['nsr_latest_fallback_event']['recorded_at'])) ?>.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($compliance['nsr_duplicate_issues'] ?? [])): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>NSR</th>
                                        <th>Ocorrências</th>
                                        <th>Primeira marcação</th>
                                        <th>Última marcação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($compliance['nsr_duplicate_issues'] ?? []) as $issue): ?>
                                    <tr>
                                        <td><?= (int) ($issue['nsr'] ?? 0) ?></td>
                                        <td class="text-danger font-weight-bold"><?= (int) ($issue['count'] ?? 0) ?></td>
                                        <td><?= !empty($issue['first_punch_time']) ? date('d/m/Y H:i', strtotime((string) $issue['first_punch_time'])) : 'N/A' ?></td>
                                        <td><?= !empty($issue['last_punch_time']) ? date('d/m/Y H:i', strtotime((string) $issue['last_punch_time'])) : 'N/A' ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($compliance['nsr_contingency_alerts'] ?? [])): ?>
                        <ul class="mb-0">
                            <?php foreach (($compliance['nsr_contingency_alerts'] ?? []) as $alert): ?>
                                <li><?= esc((string) ($alert['message'] ?? 'Alerta regulatório ativo.')) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-pie"></i> Estatísticas do Sistema</h6>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Total de Marcações</span>
                            <strong><?= number_format($compliance['total_punches']) ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Funcionários Ativos</span>
                            <strong><?= number_format($compliance['total_employees']) ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span>Registro Mais Antigo</span>
                            <strong><?= $compliance['storage_period'] ? date('d/m/Y', strtotime($compliance['storage_period'])) : 'N/A' ?></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-gavel"></i> Documentação para Fiscalização</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted">Em caso de fiscalização do MTE, tenha disponível:</p>
                    <ul>
                        <li>AFD do período solicitado (gere acima)</li>
                        <li>Atestado Técnico assinado digitalmente</li>
                        <li>Registro do software no INPI</li>
                        <li>Espelho de ponto dos funcionários</li>
                    </ul>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> O prazo para entrega do AFD e AEJ é de <strong>2 dias úteis</strong> após solicitação do Auditor-Fiscal.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.border-left-success { border-left: 4px solid #1cc88a !important; }
.border-left-danger { border-left: 4px solid #e74a3b !important; }
.border-left-warning { border-left: 4px solid #f6c23e !important; }
.text-xs { font-size: 0.7rem; }
</style>
<?= $this->endSection() ?>
