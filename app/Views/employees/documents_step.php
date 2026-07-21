<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Documentos do colaborador<?= $this->endSection() ?>

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
    display: flex; align-items: center; gap: .75rem;
    padding: 1rem 1.5rem; border-bottom: 1px solid var(--sp-border);
    background: var(--sp-gray-50, rgba(0,0,0,.02));
}
.sp-form-card__icon {
    width: 2.1rem; height: 2.1rem; border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.sp-form-card__icon.c-blue   { background: rgba(13,110,253,.12);  color: #0d6efd }
.sp-form-card__icon.c-purple { background: rgba(111, 66,193,.12); color: #6f42c1 }
.sp-form-card__title { font-weight: 700; font-size: 1rem; margin: 0 }
.sp-form-card__sub   { font-size: .78rem; color: var(--sp-text-muted); margin: 0 }
.sp-form-card__body  { padding: 1.5rem }
</style>

<div class="container-fluid sp-employee-shell sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => 'Etapa 4 de 4 — Upload de Documentos',
        'subtitle' => 'Colaborador criado com sucesso! Envie a foto e os documentos abaixo (opcional).',
        'icon'     => 'bi bi-cloud-upload-fill',
    ]) ?>

    <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div><strong><?= esc($employee->name ?? '') ?></strong> foi cadastrado com sucesso.</div>
    </div>

    <?= $this->include('employees/partials/_document_uploads') ?>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= site_url('employees/' . (int) ($employee->id ?? 0)) ?>" class="btn btn-outline-secondary">
            Pular por enquanto
        </a>
        <a href="<?= site_url('employees/' . (int) ($employee->id ?? 0)) ?>" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Concluir cadastro
        </a>
    </div>
</div>
<?= $this->endSection() ?>
