<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Atribuição em massa<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-users-cog me-2"></i>Atribuição em massa de escalas</h2>
            <p class="text-muted mb-0">Selecione vários colaboradores, defina o turno e estabeleça o período de aplicação.</p>
        </div>
        <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="<?= sp_schedules_bulk_assign_store_url() ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-lg-7">
                    <label class="form-label" for="employee_ids">Colaboradores</label>
                    <select multiple size="12" class="form-select" id="employee_ids" name="employee_ids[]" required>
                        <?php foreach (($employees ?? []) as $employee): ?>
                            <option value="<?= (int) $employee->id ?>"><?= esc($employee->name) ?><?= !empty($employee->department) ? ' · ' . esc($employee->department) : '' ?><?= !empty($employee->position) ? ' · ' . esc($employee->position) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Segure Ctrl/Cmd para selecionar múltiplos colaboradores.</div>
                </div>
                <div class="col-lg-5">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="shift_id">Turno</label>
                            <select class="form-select" id="shift_id" name="shift_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach (($shifts ?? []) as $shift): ?>
                                    <option value="<?= (int) $shift->id ?>"><?= esc($shift->name) ?> (<?= esc(substr((string) $shift->start_time,0,5)) ?> - <?= esc(substr((string) $shift->end_time,0,5)) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="start_date">Data inicial</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?= esc(old('start_date', date('Y-m-d'))) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="end_date">Data final</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?= esc(old('end_date', date('Y-m-d', strtotime('+7 days')))) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Observações</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Observações da operação em massa"><?= esc(old('notes')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top mt-2">
                    <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Aplicar atribuição em massa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>