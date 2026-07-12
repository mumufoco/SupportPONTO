<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Relatório ANPD<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= site_url('dashboard') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= site_url('lgpd/consents') ?>">LGPD</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Relatório ANPD</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>
                            Relatório para ANPD
                        </h5>
                        <button class="btn btn-light btn-sm" onclick="window.print()">
                            <i class="fas fa-print me-1"></i> Imprimir
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Este relatório contém informações sobre o tratamento de dados pessoais em conformidade com a Lei Geral de Proteção de Dados (LGPD).
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Informações do Controlador</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted">Nome:</td>
                                    <td><strong><?= esc($report['controller_name'] ?? 'N/A') ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">CNPJ:</td>
                                    <td><?= esc($report['controller_cnpj'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Encarregado (DPO):</td>
                                    <td><?= esc($report['dpo_name'] ?? 'N/A') ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Email do DPO:</td>
                                    <td><?= esc($report['dpo_email'] ?? 'N/A') ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Período do Relatório</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <td class="text-muted">Data de Geração:</td>
                                    <td><strong><?= date('d/m/Y H:i') ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Período:</td>
                                    <td><?= esc($report['period'] ?? 'Últimos 12 meses') ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <hr>

                    <h6 class="text-muted mb-3">
                        <i class="fas fa-database me-2"></i>
                        Resumo do Tratamento de Dados
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-primary mb-0"><?= number_format($report['total_subjects'] ?? 0) ?></h3>
                                    <small class="text-muted">Titulares de Dados</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-success mb-0"><?= number_format($report['consents_granted'] ?? 0) ?></h3>
                                    <small class="text-muted">Consentimentos Ativos</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-warning mb-0"><?= number_format($report['consents_revoked'] ?? 0) ?></h3>
                                    <small class="text-muted">Consentimentos Revogados</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-info mb-0"><?= number_format($report['data_exports'] ?? 0) ?></h3>
                                    <small class="text-muted">Exportações de Dados</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h6 class="text-muted mb-3">
                        <i class="fas fa-list me-2"></i>
                        Tipos de Dados Coletados
                    </h6>
                    <ul class="list-group mb-4">
                        <?php $dataTypes = $report['data_types'] ?? ['Dados de identificação', 'Dados de contato', 'Dados de localização', 'Dados biométricos']; ?>
                        <?php foreach ($dataTypes as $type): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?= esc($type) ?>
                            <span class="badge bg-primary rounded-pill">
                                <i class="fas fa-check"></i>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <h6 class="text-muted mb-3">
                        <i class="fas fa-shield-alt me-2"></i>
                        Medidas de Segurança
                    </h6>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Criptografia de dados em trânsito (TLS)</li>
                                <li><i class="fas fa-check text-success me-2"></i> Criptografia de dados em repouso</li>
                                <li><i class="fas fa-check text-success me-2"></i> Controle de acesso baseado em funções</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i> Registro de auditoria (logs)</li>
                                <li><i class="fas fa-check text-success me-2"></i> Backups regulares</li>
                                <li><i class="fas fa-check text-success me-2"></i> Monitoramento de segurança</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Documento gerado automaticamente pelo sistema
                        </small>
                        <a href="<?= site_url('lgpd/consents') ?>" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
