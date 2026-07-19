<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-7">

      <div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
        <i class="bi bi-calendar-x fs-4 mt-1"></i>
        <div>
          <strong>Marcação de ponto incompleta detectada</strong>
          <p class="mb-0 mt-1 small">
            O sistema identificou que uma ou mais marcações de dias anteriores ficaram incompletas.
            Explique o ocorrido em cada pendência abaixo para que seu gestor possa revisar.
          </p>
        </div>
      </div>

      <?php if (empty($awaiting)): ?>
        <div class="card shadow-sm">
          <div class="card-body text-center text-muted py-5">
            <i class="bi bi-check-circle fs-1 d-block mb-2 text-success"></i>
            Nenhuma pendência aguardando sua justificativa no momento.
            <div class="mt-3">
              <a href="<?= sp_timesheet_punch_url() ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar para Bater ponto
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>

        <?php foreach ($awaiting as $item): ?>
        <div class="card shadow-sm mb-4">
          <div class="card-body">
            <h5 class="card-title mb-1">
              Marcação faltante: <?= esc($punchTypes[$item->intended_punch_type] ?? $item->intended_punch_type) ?>
            </h5>
            <p class="text-muted small mb-3">
              Referente ao dia <?= esc(date('d/m/Y', strtotime((string) $item->intended_time))) ?>
            </p>

            <div class="card border-0 bg-light mb-3">
              <div class="card-body py-2">
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
        <?php endforeach; ?>

      <?php endif; ?>

    </div>
  </div>
</div>

<?= $this->endSection() ?>
