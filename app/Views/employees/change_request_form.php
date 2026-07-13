<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Solicitar Alteração Cadastral<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$emp    = $employee;
$empId  = (int)($emp->id ?? 0);
$fields = $allowedFields ?? [];
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Solicitar Alteração Cadastral',
        'subtitle' => 'Preencha o campo que deseja alterar e justifique o motivo',
        'icon'     => 'bi bi-pencil-square',
        'actions'  => [
            ['label' => 'Voltar ao perfil', 'icon' => 'bi bi-arrow-left', 'url' => site_url('employees/' . $empId)],
            ['label' => 'Minhas solicitações', 'icon' => 'bi bi-clock-history', 'url' => site_url('employees/change-request/status/' . $empId)],
        ],
    ]) ?>


    <?php if (!empty($pending)): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-hourglass-split flex-shrink-0 fs-5 mt-1"></i>
        <div>
            <strong>Você tem <?= count($pending) ?> solicitação(ões) pendente(s)</strong> aguardando revisão do administrador.
            <a href="<?= site_url('employees/change-request/status/' . $empId) ?>" class="alert-link ms-1">Ver status →</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title"><i class="bi bi-pencil-square"></i>Nova solicitação</h2>
                </div>
                <div class="sp-data-card__body">
                    <form method="post" action="<?= site_url('employees/change-request/store') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="employee_id" value="<?= $empId ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="field_key">
                                Qual informação deseja alterar? <span class="text-danger">*</span>
                            </label>
                            <select name="field_key" id="field_key" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($fields as $key => $label): ?>
                                <option value="<?= esc($key) ?>" <?= old('field_key') === $key ? 'selected' : '' ?>>
                                    <?= esc($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="currentValueWrap" style="display:none">
                            <label class="form-label fw-semibold text-muted small">Valor atual no cadastro</label>
                            <div id="currentValueDisplay" class="form-control bg-light text-muted" style="min-height:38px"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="requested_value">
                                Novo valor desejado <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="requested_value" id="requested_value"
                                   class="form-control" required maxlength="500"
                                   value="<?= esc(old('requested_value')) ?>"
                                   placeholder="Digite o novo valor...">
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold" for="justification">
                                Justificativa <span class="text-danger">*</span>
                            </label>
                            <textarea name="justification" id="justification" rows="4"
                                      class="form-control" required minlength="20" maxlength="1000"
                                      placeholder="Explique o motivo da alteração (mínimo 20 caracteres)..."><?= esc(old('justification')) ?></textarea>
                            <div class="form-text">
                                <span id="justifCount">0</span> / 1000 caracteres
                                <span class="text-muted ms-2">— mínimo 20</span>
                            </div>
                        </div>

                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Sua solicitação será analisada pelo administrador. Você receberá uma notificação com a decisão.
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?= site_url('employees/' . $empId) ?>" class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send me-1"></i>Enviar solicitação
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title"><i class="bi bi-person-badge"></i>Dados atuais</h2>
                </div>
                <div class="sp-data-card__body p-0">
                    <table class="table table-sm mb-0">
                        <?php
                        $displayFields = [
                            'name'       => 'Nome',
                            'email'      => 'E-mail',
                            'phone'      => 'Telefone',
                            'cpf'        => 'CPF',
                            'department' => 'Departamento',
                            'position'   => 'Cargo',
                        ];
                        foreach ($displayFields as $fk => $fl):
                            $val = is_object($emp) ? ($emp->$fk ?? '—') : ($emp[$fk] ?? '—');
                        ?>
                        <tr>
                            <td class="text-muted small ps-3 py-2" style="width:40%"><?= esc($fl) ?></td>
                            <td class="fw-medium py-2 pe-3"><?= esc($val ?: '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(() => {
    // Valores atuais do cadastro para exibir no formulário
    const currentValues = {
        <?php
        $fks = ['name','email','phone','cpf','address','birth_date','department','position'];
        $parts = [];
        foreach ($fks as $fk) {
            $v = is_object($emp) ? ($emp->$fk ?? '') : ($emp[$fk] ?? '');
            $parts[] = json_encode($fk) . ':' . json_encode((string)$v);
        }
        echo implode(',', $parts);
        ?>
    };

    const fieldSelect   = document.getElementById('field_key');
    const currentWrap   = document.getElementById('currentValueWrap');
    const currentDisp   = document.getElementById('currentValueDisplay');
    const justifEl      = document.getElementById('justification');
    const justifCount   = document.getElementById('justifCount');

    fieldSelect?.addEventListener('change', function() {
        const val = currentValues[this.value];
        if (val !== undefined) {
            currentDisp.textContent = val || '(vazio)';
            currentWrap.style.display = '';
        } else {
            currentWrap.style.display = 'none';
        }
    });

    justifEl?.addEventListener('input', function() {
        justifCount.textContent = this.value.length;
        justifCount.className = this.value.length < 20 ? 'text-danger fw-bold' : 'text-success';
    });

    // Restore field on back
    if (fieldSelect?.value) fieldSelect.dispatchEvent(new Event('change'));
    if (justifEl) { justifCount.textContent = justifEl.value.length; }
})();
</script>
<?= $this->endSection() ?>
