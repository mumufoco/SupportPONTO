<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nova advertência<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Nova advertência',
        'subtitle' => 'Registre medidas disciplinares com rastreabilidade, clareza e documentação adequada.',
        'icon'     => 'bi bi-exclamation-triangle-fill',
        'actions'  => [
            ['label' => 'Voltar para lista', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_warning_index_url()],
        ],
    ]) ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(220,53,69,.12);color:#dc3545;"><i class="bi bi-file-earmark-text-fill"></i></span>
                        Cadastro da advertência
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <p class="text-muted small">Preencha os dados essenciais da ocorrência e anexe evidências quando necessário.</p>

                    <form action="<?= sp_warning_index_url() ?>" method="post" enctype="multipart/form-data" id="warningForm" class="row g-3">
                        <?= csrf_field() ?>

                        <div class="col-12">
                            <label for="employee_id" class="form-label">Colaborador <span class="text-danger">*</span></label>
                            <select name="employee_id" id="employee_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= esc($emp->id) ?>" <?= (string) old('employee_id') === (string) $emp->id ? 'selected' : '' ?>>
                                        <?= esc($emp->name) ?> — <?= esc($emp->department) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="warning_type" class="form-label">Tipo de advertência <span class="text-danger">*</span></label>
                            <select name="warning_type" id="warning_type" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="verbal"    <?= old('warning_type') === 'verbal'    ? 'selected' : '' ?>>Verbal</option>
                                <option value="escrita"   <?= old('warning_type') === 'escrita'   ? 'selected' : '' ?>>Escrita</option>
                                <option value="suspensao" <?= old('warning_type') === 'suspensao' ? 'selected' : '' ?>>Suspensão</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="occurrence_date" class="form-label">Data da ocorrência <span class="text-danger">*</span></label>
                            <input type="date" name="occurrence_date" id="occurrence_date" class="form-control"
                                   value="<?= esc(old('occurrence_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="reason" class="form-label">Motivo detalhado <span class="text-danger">*</span></label>
                            <textarea name="reason" id="reason" class="form-control" rows="7" required minlength="50" maxlength="5000"><?= esc(old('reason', '')) ?></textarea>
                            <div class="form-text"><span id="charCount">0</span>/5000 caracteres (mínimo 50)</div>
                        </div>

                        <div class="col-12">
                            <label for="evidence_files" class="form-label">Evidências</label>
                            <input type="file" name="evidence_files[]" id="evidence_files" class="form-control" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx">
                            <div class="form-text"><i class="bi bi-info-circle"></i> Formatos aceitos: PDF, JPG, PNG, WEBP, DOC, DOCX. Máximo: 5 arquivos.</div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <a href="<?= sp_warning_index_url() ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i>Registrar advertência
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-info-circle-fill"></i></span>
                        Orientações
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0">
                            <i class="bi bi-shield-check text-primary me-2"></i>
                            <strong>Rastreabilidade</strong>
                            <small class="d-block text-muted">Descreva a ocorrência com fatos objetivos, datas e contexto verificável.</small>
                        </div>
                        <div class="list-group-item px-0">
                            <i class="bi bi-pen-fill text-primary me-2"></i>
                            <strong>Assinatura e ciência</strong>
                            <small class="d-block text-muted">O sistema seguirá o fluxo de notificação e assinatura conforme a política configurada.</small>
                        </div>
                        <div class="list-group-item px-0">
                            <i class="bi bi-paperclip text-primary me-2"></i>
                            <strong>Evidências</strong>
                            <small class="d-block text-muted">Anexe documentos ou imagens quando necessário para fortalecer o histórico da ocorrência.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    var reasonField = document.getElementById('reason');
    var charCountEl = document.getElementById('charCount');

    function updateCharCount() {
        var n = reasonField.value.length;
        charCountEl.textContent = n;
        charCountEl.style.color = n < 50 ? 'var(--sp-danger)' : (n > 4500 ? 'var(--sp-warning)' : 'var(--sp-success)');
    }

    reasonField.addEventListener('input', updateCharCount);
    updateCharCount();
})();
</script>
<?= $this->endSection() ?>
