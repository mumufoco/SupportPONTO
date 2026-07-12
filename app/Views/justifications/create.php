<?php use App\Enums\Role; ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nova Justificativa<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_blue.css">

<style>
    .char-counter { font-size: 0.85rem; color: var(--sp-text-muted); }
    .char-counter.text-warning { color: var(--sp-warning) !important; }
    .char-counter.text-danger  { color: var(--sp-danger) !important; }
    .char-counter.text-success { color: var(--sp-success) !important; }

    .file-upload-area {
        border: 2px dashed var(--sp-border);
        border-radius: var(--sp-radius-md);
        padding: 2rem;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
    }
    .file-upload-area:hover,
    .file-upload-area.drag-over {
        border-color: var(--sp-primary);
        background-color: var(--sp-primary-light);
    }
    .file-preview {
        display: inline-flex;
        align-items: center;
        position: relative;
        margin: 0.5rem;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--sp-border);
        border-radius: var(--sp-radius-sm);
        background: var(--sp-gray-100);
        gap: .5rem;
    }
    .file-preview .remove-file {
        position: absolute;
        top: -8px;
        right: -8px;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: var(--sp-danger);
        color: #fff;
        border: 2px solid #fff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        line-height: 1;
    }
    .file-preview .remove-file:hover { background: #c0392b; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Nova Justificativa',
        'subtitle' => 'Preencha os dados para enviar sua justificativa ao gestor.',
        'icon'     => 'bi bi-file-earmark-plus-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left', 'url' => sp_safe_url(base_url('justifications'))],
        ],
    ]) ?>


    <div style="max-width:720px;margin:0 auto;">
        <div class="sp-card">
            <div class="sp-card-header">
                <h5 class="sp-card-title">
                    <i class="bi bi-pencil-fill"></i>Formulário de Justificativa
                </h5>
            </div>
            <div class="sp-card-body">

                <div class="sp-alert sp-alert-warning" style="margin-bottom:1.25rem;">
                    <i class="bi bi-info-circle-fill"></i>
                    <div>
                        <strong>Importante:</strong>
                        Preencha todos os campos obrigatórios. Justificativas são enviadas para aprovação do gestor.
                        <?php if (in_array(Role::normalize((string) ($employee['role'] ?? Role::Funcionario->value))->value, [Role::Admin->value, Role::Gestor->value, Role::RH->value], true)): ?>
                            <br><small>Como gestor/admin, suas justificativas serão aprovadas automaticamente.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="<?= sp_safe_url(base_url('justifications')) ?>" method="POST"
                      enctype="multipart/form-data" id="justificationForm">
                    <?= csrf_field() ?>

                    <!-- Data -->
                    <div class="sp-form-group">
                        <label for="justification_date" class="sp-label">
                            Data <span style="color:var(--sp-danger);">*</span>
                        </label>
                        <input type="text"
                               class="sp-input <?= session('errors.justification_date') ? 'is-invalid' : '' ?>"
                               id="justification_date"
                               name="justification_date"
                               placeholder="Selecione a data"
                               value="<?= sp_attr(old('justification_date', $date ?? '')) ?>"
                               required>
                        <?php if (session('errors.justification_date')): ?>
                            <span class="sp-field-error"><?= esc(session('errors.justification_date')) ?></span>
                        <?php endif; ?>
                        <span class="sp-field-hint">
                            <i class="bi bi-calendar-event"></i> Não é permitido justificar datas futuras
                        </span>
                    </div>

                    <!-- Tipo -->
                    <div class="sp-form-group">
                        <label for="justification_type" class="sp-label">
                            Tipo de Justificativa <span style="color:var(--sp-danger);">*</span>
                        </label>
                        <select class="sp-select <?= session('errors.justification_type') ? 'is-invalid' : '' ?>"
                                id="justification_type"
                                name="justification_type"
                                required>
                            <option value="">Selecione o tipo</option>
                            <option value="falta"            <?= old('justification_type') === 'falta'            ? 'selected' : '' ?>>Falta</option>
                            <option value="atraso"           <?= old('justification_type') === 'atraso'           ? 'selected' : '' ?>>Atraso</option>
                            <option value="saida-antecipada" <?= old('justification_type') === 'saida-antecipada' ? 'selected' : '' ?>>Saída Antecipada</option>
                        </select>
                        <?php if (session('errors.justification_type')): ?>
                            <span class="sp-field-error"><?= esc(session('errors.justification_type')) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Categoria -->
                    <div class="sp-form-group">
                        <label for="category" class="sp-label">
                            Categoria <span style="color:var(--sp-danger);">*</span>
                        </label>
                        <select class="sp-select <?= session('errors.category') ? 'is-invalid' : '' ?>"
                                id="category"
                                name="category"
                                required>
                            <option value="">Selecione a categoria</option>
                            <option value="doenca"              <?= old('category') === 'doenca'              ? 'selected' : '' ?>>Doença</option>
                            <option value="compromisso-pessoal" <?= old('category') === 'compromisso-pessoal' ? 'selected' : '' ?>>Compromisso Pessoal</option>
                            <option value="emergencia-familiar" <?= old('category') === 'emergencia-familiar' ? 'selected' : '' ?>>Emergência Familiar</option>
                            <option value="outro"               <?= old('category') === 'outro'               ? 'selected' : '' ?>>Outro</option>
                        </select>
                        <?php if (session('errors.category')): ?>
                            <span class="sp-field-error"><?= esc(session('errors.category')) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Motivo -->
                    <div class="sp-form-group">
                        <label for="reason" class="sp-label">
                            Motivo Detalhado <span style="color:var(--sp-danger);">*</span>
                        </label>
                        <textarea class="sp-textarea <?= session('errors.reason') ? 'is-invalid' : '' ?>"
                                  id="reason"
                                  name="reason"
                                  rows="5"
                                  placeholder="Descreva o motivo da justificativa com detalhes (mínimo 50 caracteres)"
                                  minlength="50"
                                  maxlength="500"
                                  required><?= sp_text(old('reason')) ?></textarea>
                        <div style="display:flex;justify-content:space-between;align-items:center;">
                            <span class="sp-field-hint">
                                <i class="bi bi-pencil"></i> Mínimo 50 caracteres, máximo 500
                            </span>
                            <span class="char-counter" id="charCounter">
                                <span id="charCount">0</span> / 500
                            </span>
                        </div>
                        <div id="reasonError" style="color:var(--sp-danger);font-size:.825rem;margin-top:.2rem;min-height:1.1em;"></div>
                        <?php if (session('errors.reason')): ?>
                            <span class="sp-field-error"><?= esc(session('errors.reason')) ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Anexos -->
                    <div class="sp-form-group">
                        <label class="sp-label">
                            Anexos <span class="sp-field-hint" style="display:inline;">(Opcional)</span>
                        </label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <i class="bi bi-cloud-upload"
                               style="font-size:2.5rem;display:block;margin-bottom:.5rem;color:var(--sp-text-muted);"></i>
                            <p style="margin-bottom:.5rem;font-weight:500;">Clique ou arraste arquivos aqui</p>
                            <p class="sp-field-hint" style="margin:0;">
                                Máximo 3 arquivos &bull; PDF, JPG ou PNG &bull; 5 MB cada
                            </p>
                            <input type="file"
                                   id="attachments"
                                   name="attachments[]"
                                   multiple
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   style="display:none;">
                        </div>
                        <div id="filePreviewContainer" style="margin-top:.75rem;display:flex;flex-wrap:wrap;"></div>
                        <span class="sp-field-hint" style="margin-top:.5rem;">
                            <i class="bi bi-info-circle"></i>
                            Anexe documentos comprobatórios (ex: atestado médico, comprovante)
                        </span>
                    </div>

                    <!-- Botões -->
                    <div style="display:flex;justify-content:space-between;gap:.75rem;margin-top:1.5rem;flex-wrap:wrap;">
                        <a href="<?= sp_safe_url(base_url('justifications')) ?>" class="sp-btn sp-btn-outline">
                            <i class="bi bi-x-lg"></i> Cancelar
                        </a>
                        <button type="submit" class="sp-btn sp-btn-primary" id="submitBtn">
                            <i class="bi bi-send-fill"></i> Enviar Justificativa
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>

<script <?= csp_script_nonce_attr() ?>>
    // Date picker
    flatpickr('#justification_date', {
        locale: 'pt',
        dateFormat: 'Y-m-d',
        maxDate: 'today',
        defaultDate: <?= json_encode(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($date ?? '')) ? $date : '') ?>,
        allowInput: true,
    });

    // Character counter
    const reasonTextarea = document.getElementById('reason');
    const charCountSpan  = document.getElementById('charCount');
    const charCounter    = document.getElementById('charCounter');
    const reasonError    = document.getElementById('reasonError');

    reasonTextarea.addEventListener('input', function () {
        const count = this.value.trim().length;
        charCountSpan.textContent = count;
        reasonError.textContent = '';
        charCounter.classList.remove('text-warning', 'text-danger', 'text-success');
        if (count < 50) {
            charCounter.classList.add('text-danger');
            reasonError.textContent = 'Mínimo 50 caracteres.';
        } else if (count > 450) {
            charCounter.classList.add('text-warning');
        } else {
            charCounter.classList.add('text-success');
        }
        if (this.value.length > 500) this.value = this.value.substring(0, 500);
    });
    reasonTextarea.dispatchEvent(new Event('input'));

    // File upload
    const fileUploadArea       = document.getElementById('fileUploadArea');
    const fileInput            = document.getElementById('attachments');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    let selectedFiles = [];

    fileUploadArea.addEventListener('click',     () => fileInput.click());
    fileUploadArea.addEventListener('dragover',  (e) => { e.preventDefault(); fileUploadArea.classList.add('drag-over'); });
    fileUploadArea.addEventListener('dragleave', () => fileUploadArea.classList.remove('drag-over'));
    fileUploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadArea.classList.remove('drag-over');
        handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', (e) => handleFiles(e.target.files));

    function handleFiles(files) {
        if (selectedFiles.length + files.length > 3) {
            fileInput.setCustomValidity('Máximo de 3 arquivos permitidos.');
            fileInput.reportValidity();
            return;
        }
        let hasError = false;
        Array.from(files).forEach(file => {
            if (!['application/pdf', 'image/jpeg', 'image/png'].includes(file.type)) {
                fileInput.setCustomValidity('Tipo de arquivo não permitido: ' + file.name);
                fileInput.reportValidity();
                hasError = true;
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                fileInput.setCustomValidity('Arquivo muito grande (máx 5 MB): ' + file.name);
                fileInput.reportValidity();
                hasError = true;
                return;
            }
            selectedFiles.push(file);
            renderFilePreview(file);
        });
        if (!hasError) fileInput.setCustomValidity('');
        updateFileInput();
    }

    function renderFilePreview(file) {
        const preview = document.createElement('div');
        preview.className = 'file-preview';
        const icon = file.type === 'application/pdf'
            ? '<i class="bi bi-file-pdf-fill" style="font-size:1.75rem;color:var(--sp-danger);"></i>'
            : '<i class="bi bi-file-image-fill" style="font-size:1.75rem;color:var(--sp-primary);"></i>';
        preview.innerHTML = icon
            + '<div><div style="font-size:.8rem;font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + file.name + '</div>'
            + '<div style="font-size:.75rem;color:var(--sp-text-muted);">' + (file.size / 1024).toFixed(1) + ' KB</div></div>'
            + '<button type="button" class="remove-file" onclick="removeFile(\'' + file.name.replace(/'/g, "\\'") + '\')">'
            + '<i class="bi bi-x"></i></button>';
        filePreviewContainer.appendChild(preview);
    }

    function removeFile(fileName) {
        selectedFiles = selectedFiles.filter(f => f.name !== fileName);
        renderAllPreviews();
        updateFileInput();
    }
    function renderAllPreviews() {
        filePreviewContainer.innerHTML = '';
        selectedFiles.forEach(file => renderFilePreview(file));
    }
    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }

    // Form validation
    document.getElementById('justificationForm').addEventListener('submit', function (e) {
        const val = reasonTextarea.value.trim();
        if (val.length < 50) {
            e.preventDefault();
            reasonError.textContent = 'O motivo deve ter pelo menos 50 caracteres.';
            reasonTextarea.focus();
            return false;
        }
        if (val.length > 500) {
            e.preventDefault();
            reasonError.textContent = 'O motivo deve ter no máximo 500 caracteres.';
            reasonTextarea.focus();
            return false;
        }
        reasonTextarea.setCustomValidity('');
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
    });
</script>
<?= $this->endSection() ?>
