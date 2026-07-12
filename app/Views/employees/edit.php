<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar colaborador<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-employee-shell sp-responsive-screen">
    <?= view('components/page_header', [
        'title' => 'Editar colaborador',
        'subtitle' => 'Atualize os dados do colaborador, revise o vínculo profissional e ajuste os parâmetros operacionais.',
        'icon' => 'bi bi-pencil-square',
        'actions' => [
            ['label' => 'Ver perfil', 'icon' => 'bi bi-person-vcard-fill', 'url' => site_url('employees/' . ($employee->id ?? ''))],
            ['label' => 'Voltar para listagem', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('employees')],
        ],
    ]) ?>

    <?php if (!empty($formOptions['warnings'] ?? [])): ?>
        <div class="sp-callout-warning">
            <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Atenção</strong>
            <div>Existem pendências em configurações de apoio ao cadastro. Revise os pontos abaixo:</div>
            <ul class="mb-0 mt-2">
                <?php foreach (($formOptions['warnings'] ?? []) as $warning): ?>
                    <li><?= esc($warning) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="<?= site_url('employees/' . ($employee->id ?? '')) ?>" method="post" class="sp-employee-shell">
        <?= csrf_field() ?>
        <input type="hidden" name="_method" value="PUT">
        <?= $this->include('employees/partials/_personal_data') ?>
        <?= $this->include('employees/partials/_professional_data') ?>
        <?= $this->include('employees/partials/_operational_settings') ?>
        <?php $submitLabel = 'Salvar alterações'; ?>
        <?= $this->include('employees/partials/_form_actions') ?>
    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= $this->include('employees/partials/edit_scripts') ?>
<?= $this->endSection() ?>
