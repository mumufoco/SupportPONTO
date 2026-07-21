<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Cargo<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <h1 class="h4 mb-3">Editar Cargo</h1>

    <div class="card">
        <div class="card-body">
            <form action="<?= sp_route_url('settings.positions.update', ($position->id ?? $position['id'])) ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nome</label>
                    <input type="text" name="name" class="form-control" required value="<?= esc($position->name ?? $position['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Departamento</label>
                    <select name="department_id" class="form-select" required>
                        <option value="">Selecione</option>
                        <?php foreach (($departments ?? []) as $department): ?>
                            <?php
                                $deptId = $department->id ?? $department['id'] ?? '';
                                $deptName = $department->name ?? $department['name'] ?? '';
                                $selected = ($position->department_id ?? $position['department_id'] ?? null) == $deptId;
                            ?>
                            <option value="<?= esc($deptId) ?>" <?= $selected ? 'selected' : '' ?>>
                                <?= esc($deptName) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">CBO (Classificação Brasileira de Ocupações)</label>
                    <select name="cbo_occupation_id" id="cbo_occupation_id" class="form-select">
                        <option value="">Nenhum</option>
                        <?php
                            $currentCboId = $position->cbo_occupation_id ?? $position['cbo_occupation_id'] ?? null;
                        ?>
                        <?php foreach (($cboOccupations ?? []) as $occupation): ?>
                            <option value="<?= esc($occupation->id ?? '') ?>" <?= (string) $currentCboId === (string) ($occupation->id ?? '') ? 'selected' : '' ?>>
                                <?= esc(($occupation->code ?? '') . ' — ' . ($occupation->title ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Busque pelo código ou pelo nome da ocupação para indicar o CBO mais adequado a este cargo.</div>
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

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script <?= csp_script_nonce_attr() ?>>
new TomSelect('#cbo_occupation_id', {
    create: false,
    maxOptions: null,
    placeholder: 'Buscar por código ou ocupação...',
});
</script>
<?= $this->endSection() ?>
