<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Novo Cargo<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <h1 class="h4 mb-3">Novo Cargo</h1>

    <div class="card">
        <div class="card-body">
            <form action="<?= sp_route_url('settings.positions.store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Departamento</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach (($departments ?? []) as $department): ?>
                            <option value="<?= esc($department->id ?? $department['id'] ?? '') ?>">
                                <?= esc($department->name ?? $department['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= sp_route_url('settings.positions') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
