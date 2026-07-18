<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar dependente<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.sp-form-card {
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.sp-form-card__head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--sp-border);
    background: var(--sp-gray-50, rgba(0,0,0,.02));
}
.sp-form-card__icon {
    width: 2.1rem; height: 2.1rem;
    border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.sp-form-card__icon.c-blue   { background: rgba(13,110,253,.12);  color: #0d6efd }
.sp-form-card__icon.c-green  { background: rgba(25,135, 84,.12);  color: #198754 }
.sp-form-card__title { font-weight: 700; font-size: 1rem; margin: 0 }
.sp-form-card__sub   { font-size: .78rem; color: var(--sp-text-muted); margin: 0 }
.sp-form-card__body  { padding: 1.5rem }
.sp-form-actions {
    display: flex; justify-content: flex-end; align-items: center; gap: .75rem;
    padding: 1.25rem 1.5rem;
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
</style>

<div class="container-fluid sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => 'Editar dependente',
        'subtitle' => 'Atualize os dados do dependente.',
        'icon'     => 'bi bi-person-hearts',
        'actions'  => [
            ['label' => 'Voltar para listagem', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('employees/dependents')],
        ],
    ]) ?>

    <form action="<?= site_url('employees/dependents/' . (int) $dependent->id) ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">

        <div class="sp-form-card">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-blue"><i class="bi bi-person-badge-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Funcionário</p>
                    <p class="sp-form-card__sub">O vínculo com o funcionário não pode ser alterado após o cadastro.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <label class="form-label">Funcionário</label>
                <input type="text" class="form-control" value="<?= esc(($employee->name ?? '-') . ' — ' . ($employee->department ?? '-')) ?>" disabled>
            </div>
        </div>

        <div class="sp-form-card">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-blue"><i class="bi bi-person-vcard-fill"></i></div>
                <div>
                    <p class="sp-form-card__title">Dados do dependente</p>
                    <p class="sp-form-card__sub">Informações pessoais exigidas pelo eSocial.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="name" class="form-label">Nome completo <span class="text-danger">*</span></label>
                        <input type="text" id="name" name="name" class="form-control" required minlength="3" maxlength="150"
                               value="<?= esc($dependent->name ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="cpf" class="form-label">CPF <span class="text-danger">*</span></label>
                        <input type="text" id="cpf" name="cpf" class="form-control" required maxlength="14"
                               value="<?= esc(format_cpf($dependent->cpf ?? '')) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="birth_date" class="form-label">Data de nascimento <span class="text-danger">*</span></label>
                        <input type="date" id="birth_date" name="birth_date" class="form-control" required
                               value="<?= esc($dependent->birth_date ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="kinship_type" class="form-label">Grau de parentesco <span class="text-danger">*</span></label>
                        <select name="kinship_type" id="kinship_type" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php foreach ($kinshipTypes ?? [] as $case): ?>
                                <option value="<?= esc($case->value) ?>" <?= ($dependent->kinship_type ?? '') === $case->value ? 'selected' : '' ?>>
                                    <?= esc($case->label()) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-form-card">
            <div class="sp-form-card__head">
                <div class="sp-form-card__icon c-green"><i class="bi bi-shield-check"></i></div>
                <div>
                    <p class="sp-form-card__title">Benefícios fiscais e observações</p>
                    <p class="sp-form-card__sub">Usado para cálculo de IRRF, salário-família e eSocial.</p>
                </div>
            </div>
            <div class="sp-form-card__body">
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="irrf_dependent" id="irrf_dependent" value="1" <?= !empty($dependent->irrf_dependent) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="irrf_dependent">Conta para IRRF</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="family_allowance_dependent" id="family_allowance_dependent" value="1" <?= !empty($dependent->family_allowance_dependent) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="family_allowance_dependent">Conta para salário-família</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="has_disability" id="has_disability" value="1" <?= !empty($dependent->has_disability) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="has_disability">Possui deficiência</label>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" id="active" value="1" <?= !empty($dependent->active) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="active">Ativo</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">Observações</label>
                        <textarea name="notes" id="notes" class="form-control" rows="3"><?= esc($dependent->notes ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-form-actions">
            <a href="<?= site_url('employees/dependents') ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary btn-lg px-4">
                <i class="bi bi-save-fill me-1"></i> Salvar alterações
            </button>
        </div>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
document.getElementById('cpf')?.addEventListener('input', function(e) {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 9) v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
    else if (v.length > 6) v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
    else if (v.length > 3) v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
    e.target.value = v;
});
</script>
<?= $this->endSection() ?>
