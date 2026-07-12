<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Férias<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4"> 
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Gestão de Férias</h1>
        <a href="<?= site_url('organizational') ?>" class="btn btn-outline-secondary">Voltar ao Organizacional</a>
    </div>

    <div class="alert alert-info">Funcionalidade inicial adicionada. Próximos passos: calendário de períodos, aprovação e bloqueio por jornada.</div>

    <div class="card">
        <div class="card-header"><strong>Colaboradores ativos (base para férias)</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Nome</th><th>Email</th><th>Setor</th></tr></thead>
                    <tbody>
                    <?php foreach (($employees ?? []) as $employee): ?>
                        <tr>
                            <td><?= esc($employee->name ?? $employee['name'] ?? '-') ?></td>
                            <td><?= esc($employee->email ?? $employee['email'] ?? '-') ?></td>
                            <td><?= esc($employee->department ?? $employee['department'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
