<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Nova Unidade de Trabalho<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <h1 class="h4 mb-3">Nova Unidade de Trabalho</h1>

    <div class="card">
        <div class="card-body">
            <form action="<?= sp_route_url('settings.work-units.store') ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= sp_route_url('settings.work-units') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
