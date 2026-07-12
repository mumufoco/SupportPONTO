<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Minhas escalas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Minhas escalas</h2>
            <p class="text-muted mb-0">Consulte seus turnos planejados no período e acompanhe horários previstos.</p>
        </div>
        <a href="<?= sp_dashboard_url() ?>" class="btn btn-secondary"><i class="fas fa-home me-2"></i>Dashboard</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="<?= current_url() ?>" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="start">Data inicial</label>
                    <input type="date" class="form-control" id="start" name="start" value="<?= esc($startDate ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="end">Data final</label>
                    <input type="date" class="form-control" id="end" name="end" value="<?= esc($endDate ?? '') ?>">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="fas fa-filter me-2"></i>Aplicar</button>
                    <a href="<?= current_url() ?>" class="btn btn-outline-secondary"><i class="fas fa-undo"></i></a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center"><h5 class="mb-0">Escalas do período</h5><span class="badge bg-light text-dark"><?= count($schedules ?? []) ?> registro(s)</span></div>
        <div class="card-body p-0">
            <?php if (empty($schedules ?? [])): ?>
                <div class="p-4 text-center text-muted">Nenhuma escala encontrada para o período selecionado.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Data</th><th>Turno</th><th>Horário</th><th>Status</th><th>Observações</th></tr></thead>
                        <tbody>
                            <?php $statuses = ['scheduled' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary', 'absent' => 'warning']; ?>
                            <?php foreach (($schedules ?? []) as $schedule): ?>
                                <tr>
                                    <td><?= esc(date('d/m/Y', strtotime((string) ($schedule->date ?? 'now')))) ?></td>
                                    <td><?= esc((string) ($schedule->shift_name ?? '-')) ?></td>
                                    <td><?= esc(substr((string) ($schedule->start_time ?? ''),0,5)) ?><?= !empty($schedule->end_time) ? ' - ' . esc(substr((string) $schedule->end_time,0,5)) : '' ?></td>
                                    <td><span class="badge bg-<?= $statuses[$schedule->status ?? 'scheduled'] ?? 'light text-dark' ?>"><?= esc(ucfirst((string) ($schedule->status ?? 'scheduled'))) ?></span></td>
                                    <td><?= esc((string) ($schedule->notes ?? '—')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>