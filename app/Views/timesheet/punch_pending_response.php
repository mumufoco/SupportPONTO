<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Pendências de Ponto<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Pendências de Ponto',
        'subtitle' => 'Marcações incompletas detectadas automaticamente pelo sistema, aguardando sua justificativa.',
        'icon'     => 'bi bi-calendar-x',
    ]) ?>

    <?php if (!empty($awaiting)): ?>
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="stat-card">
                <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-card-content">
                    <div class="stat-card-label">Aguardando sua justificativa</div>
                    <div class="stat-card-value"><?= (int) count($awaiting) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($awaiting)): ?>
        <div class="sp-data-card">
            <div class="sp-empty">
                <div class="sp-empty-icon"><i class="bi bi-check-circle"></i></div>
                <p class="sp-empty-title">Nenhuma pendência no momento</p>
                <p class="text-muted small mb-0">Todas as suas marcações de ponto estão em dia.</p>
            </div>
        </div>
    <?php else: ?>

        <div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 mt-1"></i>
            <div>
                <strong>Marcação de ponto incompleta detectada</strong>
                <p class="mb-0 mt-1 small">
                    O sistema identificou que uma ou mais marcações de dias anteriores ficaram incompletas.
                    Explique o ocorrido em cada pendência abaixo para que seu gestor possa revisar.
                </p>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($awaiting as $item): ?>
            <div class="col-xl-4 col-lg-6">
                <div class="sp-data-card h-100">
                    <div class="sp-data-card__header">
                        <h2 class="sp-data-card__title">
                            <span class="stat-card-icon warning" style="width:2.1rem;height:2.1rem;font-size:1rem;"><i class="bi bi-clock-history"></i></span>
                            Marcação faltante
                        </h2>
                        <span class="sp-badge sp-badge-warning"><?= esc($punchTypes[$item->intended_punch_type] ?? $item->intended_punch_type) ?></span>
                    </div>
                    <div class="sp-data-card__body">
                        <p class="text-muted small mb-3">
                            <i class="bi bi-calendar3 me-1"></i>Referente ao dia <strong><?= esc(format_date_br((string) $item->intended_time)) ?></strong>
                        </p>

                        <div class="d-flex gap-2 align-items-start p-3 rounded-3 mb-3" style="background:var(--sp-bg-page);border:1px solid var(--sp-border);">
                            <i class="bi bi-robot text-muted mt-1"></i>
                            <div>
                                <div class="small fw-semibold text-muted">Detectado automaticamente pelo sistema</div>
                                <small class="text-muted"><?= esc($item->justification_text) ?></small>
                            </div>
                        </div>

                        <form method="post" action="<?= site_url('timesheet/punch/pendencias/' . (int) $item->id) ?>">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Tipo de situação <span class="text-danger">*</span></label>
                                <select name="situation_type" class="form-select" required>
                                    <option value="missing_checkout" selected>Esqueci de registrar / virada de dia</option>
                                    <option value="equipment_failure">Falha no equipamento / câmera</option>
                                    <option value="system_slow">Sistema lento ou indisponível</option>
                                    <option value="camera_inaccessible">Câmera inacessível</option>
                                    <option value="biometric_failed">Biometria não funcionou</option>
                                    <option value="other">Outro (descrever abaixo)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">
                                    Descreva o ocorrido <span class="text-danger">*</span>
                                    <small class="text-muted fw-normal">(mínimo 20 caracteres)</small>
                                </label>
                                <textarea name="justification" class="form-control" rows="4"
                                    placeholder="Explique por que essa marcação não foi concluída..."
                                    minlength="20" maxlength="1000" required></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i> Enviar justificativa
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

</div>

<?= $this->endSection() ?>
