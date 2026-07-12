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
            ['label' => 'Relatórios',        'icon' => 'bi bi-bar-chart-fill',    'url' => sp_reports_index_url()],
        ],
    ]) ?>


    <!-- Grid 2 colunas -->
    <div class="sp-warning-create-grid"
         style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;">

        <!-- Formulário principal -->
        <div>
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5 class="sp-card-title">
                        <i class="bi bi-file-earmark-text-fill"></i>Cadastro da advertência
                    </h5>
                    <p style="font-size:.8125rem;color:var(--sp-text-secondary);margin:.25rem 0 0;">
                        Preencha os dados essenciais da ocorrência e anexe evidências quando necessário.
                    </p>
                </div>
                <div class="sp-card-body">
                    <form action="<?= sp_warning_index_url() ?>" method="post"
                          enctype="multipart/form-data" id="warningForm">
                        <?= csrf_field() ?>

                        <!-- Funcionário -->
                        <div class="sp-form-group">
                            <label for="employee_id" class="sp-label">
                                Funcionário <span style="color:var(--sp-danger);">*</span>
                            </label>
                            <select name="employee_id" id="employee_id" class="sp-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= esc($emp->id) ?>">
                                        <?= esc($emp->name) ?> — <?= esc($emp->department) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Tipo + Data em linha -->
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div class="sp-form-group">
                                <label for="warning_type" class="sp-label">
                                    Tipo de advertência <span style="color:var(--sp-danger);">*</span>
                                </label>
                                <select name="warning_type" id="warning_type" class="sp-select" required>
                                    <option value="">Selecione...</option>
                                    <option value="verbal">Verbal</option>
                                    <option value="escrita">Escrita</option>
                                    <option value="suspensao">Suspensão</option>
                                </select>
                            </div>
                            <div class="sp-form-group">
                                <label for="occurrence_date" class="sp-label">
                                    Data da ocorrência <span style="color:var(--sp-danger);">*</span>
                                </label>
                                <input type="date" name="occurrence_date" id="occurrence_date"
                                       class="sp-input" value="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>

                        <!-- Motivo -->
                        <div class="sp-form-group">
                            <label for="reason" class="sp-label">
                                Motivo detalhado <span style="color:var(--sp-danger);">*</span>
                            </label>
                            <textarea name="reason" id="reason" class="sp-textarea"
                                      rows="7" required minlength="50" maxlength="5000"></textarea>
                            <span class="sp-field-hint">
                                <span id="charCount">0</span>/5000 caracteres (mínimo 50)
                            </span>
                        </div>

                        <!-- Evidências -->
                        <div class="sp-form-group">
                            <label for="evidence_files" class="sp-label">Evidências</label>
                            <input type="file" name="evidence_files[]" id="evidence_files"
                                   class="sp-input" multiple
                                   accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <span class="sp-field-hint">
                                <i class="bi bi-info-circle"></i>
                                Formatos aceitos: PDF, JPG, PNG, DOC, DOCX. Máximo: 5 arquivos.
                            </span>
                        </div>

                        <!-- Botões -->
                        <div style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1rem;">
                            <a href="<?= sp_warning_index_url() ?>" class="sp-btn sp-btn-outline">
                                Cancelar
                            </a>
                            <button type="submit" class="sp-btn sp-btn-danger">
                                <i class="bi bi-exclamation-octagon-fill"></i>
                                Registrar advertência
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Painel lateral de orientações -->
        <div>
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5 class="sp-card-title">
                        <i class="bi bi-info-circle-fill"></i>Orientações
                    </h5>
                </div>
                <div class="sp-card-body">
                    <div style="display:flex;flex-direction:column;gap:1rem;">
                        <div>
                            <strong style="font-size:.875rem;color:var(--sp-text-primary);display:block;margin-bottom:.25rem;">
                                <i class="bi bi-shield-check" style="color:var(--sp-primary-dark);margin-right:.35rem;"></i>Rastreabilidade
                            </strong>
                            <span style="font-size:.8125rem;color:var(--sp-text-secondary);">
                                Descreva a ocorrência com fatos objetivos, datas e contexto verificável.
                            </span>
                        </div>
                        <div>
                            <strong style="font-size:.875rem;color:var(--sp-text-primary);display:block;margin-bottom:.25rem;">
                                <i class="bi bi-pen-fill" style="color:var(--sp-primary-dark);margin-right:.35rem;"></i>Assinatura e ciência
                            </strong>
                            <span style="font-size:.8125rem;color:var(--sp-text-secondary);">
                                O sistema seguirá o fluxo de notificação e assinatura conforme a política configurada.
                            </span>
                        </div>
                        <div>
                            <strong style="font-size:.875rem;color:var(--sp-text-primary);display:block;margin-bottom:.25rem;">
                                <i class="bi bi-paperclip" style="color:var(--sp-primary-dark);margin-right:.35rem;"></i>Evidências
                            </strong>
                            <span style="font-size:.8125rem;color:var(--sp-text-secondary);">
                                Anexe documentos ou imagens quando necessário para fortalecer o histórico da ocorrência.
                            </span>
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
    const reasonField  = document.getElementById('reason');
    const charCountEl  = document.getElementById('charCount');
    reasonField.addEventListener('input', function () {
        const n = this.value.length;
        charCountEl.textContent = n;
        charCountEl.style.color = n < 50
            ? 'var(--sp-danger)'
            : n > 4500
                ? 'var(--sp-warning)'
                : 'var(--sp-success)';
    });

    // Responsivo: colapsa para 1 coluna em mobile
    const grid = document.querySelector('.sp-warning-create-grid');
    function adjustGrid() {
        if (grid) grid.style.gridTemplateColumns = window.innerWidth < 768 ? '1fr' : '1fr 300px';
    }
    adjustGrid();
    window.addEventListener('resize', adjustGrid);
</script>
<?= $this->endSection() ?>
