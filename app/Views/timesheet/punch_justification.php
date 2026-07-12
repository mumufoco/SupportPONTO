<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container py-4">
  <div class="row justify-content-center">
    <div class="col-lg-7">

      <!-- Cabeçalho de alerta -->
      <div class="alert alert-warning d-flex align-items-start gap-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4 mt-1"></i>
        <div>
          <strong>Registro automático não pôde ser concluído</strong>
          <p class="mb-0 mt-1 small">
            O sistema tentou registrar seu ponto pelos métodos disponíveis e encontrou falhas técnicas.
            Preencha a justificativa abaixo para que seu gestor possa aprovar o registro.
          </p>
        </div>
      </div>

      <!-- Falhas detectadas -->
      <?php if (!empty($failureSummary)): ?>
      <div class="card border-0 bg-light mb-4">
        <div class="card-body">
          <h6 class="card-title mb-2"><i class="bi bi-tools me-1"></i> Falhas técnicas identificadas:</h6>
          <ul class="mb-0 small">
            <?php foreach ($failureSummary as $failure): ?>
              <li><?= esc($failure) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>

      <!-- Bloqueio por limite -->
      <?php if (!empty($eligibility['blocked'])): ?>
      <div class="alert alert-danger">
        <i class="bi bi-ban me-1"></i> <?= esc($eligibility['block_reason']) ?>
      </div>
      <?php else: ?>

      <!-- Limite mensal -->
      <?php if (isset($eligibility['monthly_used'])): ?>
      <div class="mb-3 small text-muted">
        Justificativas neste mês: <strong><?= (int)$eligibility['monthly_used'] ?></strong>
        de <?= (int)$eligibility['monthly_limit'] ?> permitidas.
      </div>
      <?php endif; ?>

      <!-- Formulário -->
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-4">Justificativa de Presença</h5>

          <?= view('partials/flash_messages') ?>

          <form method="post" action="<?= sp_timesheet_justify_submit_url() ?>">
            <?php if (!empty($attemptLog)): ?>
              <input type="hidden" name="attempt_context_present" value="1">
            <?php endif; ?>
            <?= csrf_field() ?>

            <div class="mb-3">
              <label class="form-label fw-semibold">Tipo de registro <span class="text-danger">*</span></label>
              <select name="punch_type" class="form-select" required>
                <?php foreach ($punchTypes as $val => $label): ?>
                  <option value="<?= esc($val) ?>"><?= esc($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Tipo de situação <span class="text-danger">*</span></label>
              <select name="situation_type" class="form-select" required>
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
                placeholder="Descreva o que aconteceu ao tentar registrar o ponto..."
                minlength="20" maxlength="1000" required></textarea>
              <div class="form-text text-end" id="charCount">0/1000 caracteres</div>
            </div>

            <div class="mb-4">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="confirm_presence" value="1" id="confirmPresence" required>
                <label class="form-check-label" for="confirmPresence">
                  Confirmo que estou presente fisicamente no local de trabalho neste momento.
                </label>
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="confirmApproval" required>
                <label class="form-check-label" for="confirmApproval">
                  Estou ciente de que este registro passará por aprovação do meu gestor (prazo: 24 horas).
                </label>
              </div>
            </div>

            <div class="d-flex gap-2">
              <a href="<?= sp_timesheet_punch_url() ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancelar
              </a>
              <button type="submit" class="btn btn-primary flex-grow-1">
                <i class="bi bi-send me-1"></i> Enviar justificativa
              </button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
document.querySelector('textarea[name="justification"]')?.addEventListener('input', function() {
  document.getElementById('charCount').textContent = this.value.length + '/1000 caracteres';
});
</script>

<?= $this->endSection() ?>
