<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Conformidade MTE 671/2021<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
    $nsrOk = (bool) $compliance['nsr_continuity'];
    $hashOk = (bool) $compliance['hash_integrity'];
    $tzOk = (bool) $compliance['timezone_valid'];
    $pisMissing = (int) $compliance['employees_without_pis'];
    $fallbackCount = (int) ($compliance['nsr_fallback_events_count'] ?? 0);
    $dupIssues = $compliance['nsr_duplicate_issues'] ?? [];
    $counterHealth = $compliance['nsr_counter_health'] ?? ['status' => 'error', 'message' => 'Indisponível'];
    $counterStatus = $counterHealth['status'] ?? 'error';

    $chainValid = $compliance['audit_chain_integrity_valid'] ?? null;
    $chainTampered = (int) ($compliance['audit_chain_tampered_count'] ?? 0);
    $chainForensic = (bool) ($compliance['audit_chain_forensic_required'] ?? false);
    $chainChecked = (int) ($compliance['audit_chain_checked'] ?? 0);
    $chainAnchorSupport = (bool) ($compliance['audit_chain_anchor_support'] ?? false);
    $chainAnchorCount = (int) ($compliance['audit_chain_anchor_count'] ?? 0);
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Conformidade MTE 671/2021',
        'subtitle' => 'Verificação de conformidade do REP-P (Registrador Eletrônico de Ponto por Programa) com a Portaria MTE 671/2021.',
        'icon'     => 'bi bi-patch-check-fill',
    ]) ?>

    <div class="sp-grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-card-icon <?= $nsrOk ? 'success' : 'danger' ?>"><i class="bi <?= $nsrOk ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?>"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">NSR Sequencial</div>
                <div class="stat-card-value" style="font-size:1.1rem;"><?= $nsrOk ? 'Conforme' : 'Não conforme' ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon <?= $hashOk ? 'success' : 'danger' ?>"><i class="bi bi-shield-lock-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Integridade Hash</div>
                <div class="stat-card-value" style="font-size:1.1rem;"><?= $hashOk ? 'Conforme' : $compliance['hash_issues_count'] . ' falhas' ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon <?= $tzOk ? 'success' : 'warning' ?>"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Fuso horário BR</div>
                <div class="stat-card-value" style="font-size:1rem;"><?= esc($compliance['timezone'] ?? '—') ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon <?= $pisMissing === 0 ? 'success' : 'warning' ?>"><i class="bi bi-person-vcard-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">PIS cadastrado</div>
                <div class="stat-card-value" style="font-size:1.1rem;"><?= $pisMissing === 0 ? 'Todos OK' : $pisMissing . ' pendentes' ?></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-card-icon <?= $fallbackCount === 0 ? 'success' : 'danger' ?>"><i class="bi bi-life-preserver"></i></div>
                <div class="stat-card-content">
                    <div class="stat-card-label">Contingência NSR (30 dias)</div>
                    <div class="stat-card-value" style="font-size:1.1rem;"><?= $fallbackCount === 0 ? 'Sem fallback' : $fallbackCount . ' eventos' ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-card-icon <?= $counterStatus === 'ok' ? 'success' : ($counterStatus === 'warning' ? 'warning' : 'danger') ?>"><i class="bi bi-cpu-fill"></i></div>
                <div class="stat-card-content">
                    <div class="stat-card-label">Saúde do contador NSR</div>
                    <div class="stat-card-value" style="font-size:.95rem;"><?= esc((string) ($counterHealth['message'] ?? 'Indisponível')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-card-icon <?= empty($dupIssues) ? 'success' : 'danger' ?>"><i class="bi bi-copy"></i></div>
                <div class="stat-card-content">
                    <div class="stat-card-label">Duplicidade de NSR</div>
                    <div class="stat-card-value" style="font-size:1.1rem;"><?= empty($dupIssues) ? 'Nenhuma' : count($dupIssues) . ' casos' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Integridade da cadeia de auditoria -->
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-link-45deg"></i></span>
                Integridade da cadeia de auditoria
            </h2>
        </div>
        <div class="sp-data-card__body">
            <?php if ($chainTampered > 0 || $chainForensic): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Atenção:</strong> <?= $chainTampered ?> registro(s) de auditoria com indício de adulteração.
                    <?php if ($chainForensic): ?> Revisão forense necessária.<?php endif; ?>
                </div>
            <?php elseif ($chainValid === null): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Não foi possível verificar a cadeia de integridade agora.
                    <?php if (!empty($compliance['audit_chain_verification_error'])): ?>
                        <span class="d-block small mt-1"><?= esc((string) $compliance['audit_chain_verification_error']) ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Status da cadeia</div>
                    <span class="sp-badge <?= $chainValid === true ? 'sp-badge-success' : ($chainValid === false ? 'sp-badge-danger' : 'sp-badge-neutral') ?>">
                        <?= $chainValid === true ? 'Válida' : ($chainValid === false ? 'Comprometida' : 'Não verificada') ?>
                    </span>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Registros verificados</div>
                    <div class="fw-semibold"><?= number_format($chainChecked) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Registros adulterados</div>
                    <div class="fw-semibold <?= $chainTampered > 0 ? 'text-danger' : '' ?>"><?= number_format($chainTampered) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Ancoragem de integridade</div>
                    <div class="fw-semibold"><?= $chainAnchorSupport ? number_format($chainAnchorCount) . ' âncora(s)' : 'Não configurada' ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(25,135,84,.12);color:#198754;"><i class="bi bi-file-earmark-arrow-down-fill"></i></span>
                        Exportar AFD
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <p class="text-muted small">O AFD (Arquivo Fonte de Dados) é exigido pela Portaria 671/2021 para fiscalização. Contém todas as marcações de ponto brutas com NSR, PIS do trabalhador, data/hora e tipo de marcação.</p>
                    <form action="<?= site_url('audit/afd') ?>" method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label for="date_from" class="form-label">Data início</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?= date('Y-m-01') ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label for="date_to" class="form-label">Data fim</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-download me-1"></i>Gerar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,202,240,.15);color:#0aa2c0;"><i class="bi bi-info-circle-fill"></i></span>
                        Requisitos Portaria 671/2021
                    </h2>
                </div>
                <div class="sp-data-card__body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-sort-numeric-up text-primary me-1"></i>NSR (Número Sequencial de Registro)</span>
                            <span class="sp-badge <?= $nsrOk ? 'sp-badge-success' : 'sp-badge-danger' ?>"><?= $nsrOk ? 'OK' : 'Atenção' ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-fingerprint text-primary me-1"></i>Hash de integridade</span>
                            <span class="sp-badge <?= $hashOk ? 'sp-badge-success' : 'sp-badge-danger' ?>"><?= $hashOk ? 'OK' : 'Atenção' ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-link-45deg text-primary me-1"></i>Cadeia de auditoria</span>
                            <span class="sp-badge <?= $chainValid === true ? 'sp-badge-success' : ($chainValid === false ? 'sp-badge-danger' : 'sp-badge-neutral') ?>"><?= $chainValid === true ? 'OK' : ($chainValid === false ? 'Atenção' : 'N/D') ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-clock-fill text-primary me-1"></i>Sincronismo hora legal BR</span>
                            <span class="sp-badge <?= $tzOk ? 'sp-badge-success' : 'sp-badge-warning' ?>"><?= $tzOk ? 'OK' : 'Verificar' ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-database-fill text-primary me-1"></i>Armazenamento 5 anos</span>
                            <span class="sp-badge sp-badge-success">Configurado</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-file-earmark-text-fill text-primary me-1"></i>Geração AFD</span>
                            <span class="sp-badge sp-badge-success">Disponível</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-life-preserver text-primary me-1"></i>Contingência formal rastreada</span>
                            <span class="sp-badge <?= $fallbackCount === 0 ? 'sp-badge-success' : 'sp-badge-warning' ?>"><?= $fallbackCount === 0 ? 'OK' : 'Revisar' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($compliance['hash_integrity_scope_note'])): ?>
        <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            <?= esc((string) $compliance['hash_integrity_scope_note']) ?>
            (<?= number_format((int) ($compliance['hash_integrity_sample_size'] ?? 0)) ?> de <?= number_format((int) ($compliance['hash_integrity_total_punches'] ?? 0)) ?> marcações verificadas)
        </div>
    <?php endif; ?>

    <?php if (!empty($compliance['nsr_issues'])): ?>
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(220,53,69,.12);color:#dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></span>
                Gaps no NSR detectados
            </h2>
        </div>
        <div class="sp-data-card__body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>NSR anterior</th>
                            <th>NSR próximo</th>
                            <th>Gap</th>
                            <th>Data aproximada</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compliance['nsr_issues'] as $issue): ?>
                        <tr>
                            <td><?= (int) $issue['gap_from'] ?></td>
                            <td><?= (int) $issue['gap_to'] ?></td>
                            <td class="text-danger fw-semibold"><?= (int) $issue['gap_to'] - (int) $issue['gap_from'] - 1 ?> registros</td>
                            <td><?= esc(format_datetime_br((string) $issue['date'], false)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="alert alert-warning small mt-3 mb-0">
                <i class="bi bi-info-circle me-1"></i><strong>Importante:</strong> gaps no NSR podem indicar registros excluídos ou problemas de sincronização, e podem ser questionados em fiscalização do MTE.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($fallbackCount > 0 || !empty($dupIssues)): ?>
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(255,193,7,.15);color:#997404;"><i class="bi bi-shield-fill-exclamation"></i></span>
                Contingência regulatória e reconciliação
            </h2>
        </div>
        <div class="sp-data-card__body">
            <?php if ($fallbackCount > 0): ?>
                <div class="alert alert-danger">
                    <strong>Fallback de NSR acionado:</strong> <?= $fallbackCount ?> evento(s) nos últimos 30 dias.
                    <?php if (!empty($compliance['nsr_latest_fallback_event']['recorded_at'])): ?>
                        Último em <?= esc(format_datetime_br((string) $compliance['nsr_latest_fallback_event']['recorded_at'], false)) ?>.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($dupIssues)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>NSR</th>
                                <th>Ocorrências</th>
                                <th>Tabelas envolvidas</th>
                                <th>Primeira ocorrência</th>
                                <th>Última ocorrência</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dupIssues as $issue): ?>
                            <tr>
                                <td><?= (int) ($issue['nsr'] ?? 0) ?></td>
                                <td class="text-danger fw-semibold"><?= (int) ($issue['count'] ?? 0) ?></td>
                                <td><?= esc((string) ($issue['source_tables'] ?? '')) ?></td>
                                <td><?= !empty($issue['first_occurrence']) ? esc(format_datetime_br((string) $issue['first_occurrence'], false)) : 'N/A' ?></td>
                                <td><?= !empty($issue['last_occurrence']) ? esc(format_datetime_br((string) $issue['last_occurrence'], false)) : 'N/A' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($compliance['nsr_contingency_alerts'] ?? [])): ?>
                <ul class="mb-0 mt-3">
                    <?php foreach ($compliance['nsr_contingency_alerts'] as $alert): ?>
                        <li><?= esc((string) ($alert['message'] ?? 'Alerta regulatório ativo.')) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(108,117,125,.12);color:#6c757d;"><i class="bi bi-pie-chart-fill"></i></span>
                        Estatísticas do sistema
                    </h2>
                </div>
                <div class="sp-data-card__body p-0">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between">
                            <span>Total de marcações</span>
                            <strong><?= number_format($compliance['total_punches']) ?></strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span>Colaboradores ativos</span>
                            <strong><?= number_format($compliance['total_employees']) ?></strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between">
                            <span>Registro mais antigo</span>
                            <strong><?= $compliance['storage_period'] ? esc(format_date_br((string) $compliance['storage_period'])) : 'N/A' ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(108,117,125,.12);color:#6c757d;"><i class="bi bi-bank2"></i></span>
                        Documentação para fiscalização
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <p class="text-muted small">Em caso de fiscalização do MTE, tenha disponível:</p>
                    <ul class="small mb-3">
                        <li>AFD do período solicitado (gere acima)</li>
                        <li>Atestado Técnico assinado digitalmente</li>
                        <li>Registro do software no INPI</li>
                        <li>Espelho de ponto dos colaboradores</li>
                    </ul>
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>O prazo para entrega do AFD e AEJ é de <strong>2 dias úteis</strong> após solicitação do Auditor-Fiscal.
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
