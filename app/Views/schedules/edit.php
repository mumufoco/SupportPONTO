<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar escala<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-edit me-2"></i>Editar escala</h2>
            <p class="text-muted mb-0">Atualize colaborador, turno, data e situação da escala selecionada.</p>
        </div>
        <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Voltar</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="<?= sp_schedules_update_url((int) ($schedule->id ?? 0)) ?>" class="row g-3">
                <?= csrf_field() ?>
                <div class="col-md-6">
                    <label class="form-label" for="employee_id">Colaborador</label>
                    <select class="form-select" id="employee_id" name="employee_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach (($employees ?? []) as $employee): ?>
                            <option value="<?= (int) $employee->id ?>" <?= (int) old('employee_id', $schedule->employee_id ?? 0) === (int) $employee->id ? 'selected' : '' ?>><?= esc($employee->name) ?><?= !empty($employee->department) ? ' · ' . esc($employee->department) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="shift_id">Turno</label>
                    <select class="form-select" id="shift_id" name="shift_id" required>
                        <option value="">Selecione...</option>
                        <?php foreach (($shifts ?? []) as $shift): ?>
                            <option value="<?= (int) $shift->id ?>" <?= (int) old('shift_id', $schedule->shift_id ?? 0) === (int) $shift->id ? 'selected' : '' ?>><?= esc($shift->name) ?> (<?= esc(substr((string) $shift->start_time,0,5)) ?> - <?= esc(substr((string) $shift->end_time,0,5)) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="date">Data</label>
                    <input type="date" class="form-control" id="date" name="date" value="<?= esc(old('date', $schedule->date ?? '')) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="status">Status</label>
                    <select class="form-select" id="status" name="status" required>
                        <?php $statuses = ['scheduled'=>'Agendada','completed'=>'Concluída','cancelled'=>'Cancelada','absent'=>'Ausência']; ?>
                        <?php foreach ($statuses as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= old('status', $schedule->status ?? 'scheduled') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="small text-muted">Criada em <?= !empty($schedule->created_at) ? esc(date('d/m/Y H:i', strtotime((string) $schedule->created_at))) : '-' ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notes">Observações</label>
                    <textarea class="form-control" id="notes" name="notes" rows="4" placeholder="Observações operacionais da escala"><?= esc(old('notes', $schedule->notes ?? '')) ?></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end gap-2 pt-2 border-top mt-2">
                    <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Salvar alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>