<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Estatísticas de turnos<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-chart-bar me-2"></i>Estatísticas de turnos</h2>
            <p class="text-muted mb-0">Acompanhe a distribuição dos turnos ativos, cobertura dos próximos dias e indicadores gerais da operação.</p>
        </div>
        <a href="<?= sp_shifts_index_url() ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Voltar</a>
    </div>

    <div class="row g-3 mb-4">
        <?php $cards = ['total_shifts'=>'Total de turnos','active_shifts'=>'Ativos','inactive_shifts'=>'Inativos','employees_scheduled'=>'Colaboradores escalados','upcoming_schedules'=>'Escalas nos próximos 30 dias']; ?>
        <?php foreach ($cards as $key => $label): ?>
            <div class="col-md-6 col-xl">
                <div class="card shadow-sm h-100"><div class="card-body"><div class="small text-muted text-uppercase"><?= esc($label) ?></div><div class="fs-3 fw-semibold"><?= esc((string) ($overallStats[$key] ?? 0)) ?></div></div></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0">Distribuição por tipo</h5></div>
                <div class="card-body">
                    <?php $labels = ['morning'=>'Manhã','afternoon'=>'Tarde','night'=>'Noite','custom'=>'Personalizado']; ?>
                    <?php foreach (($shiftBreakdown ?? []) as $type => $count): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><?= esc($labels[$type] ?? ucfirst((string) $type)) ?></span>
                            <span class="badge bg-primary rounded-pill"><?= esc((string) $count) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white"><h5 class="mb-0">Cobertura dos próximos 7 dias</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead><tr><th>Data</th><th>Dia</th><th class="text-end">Colaboradores escalados</th></tr></thead>
                            <tbody>
                                <?php foreach (($coverageReport ?? []) as $item): ?>
                                    <tr>
                                        <td><?= esc(date('d/m/Y', strtotime((string) ($item['date'] ?? 'now')))) ?></td>
                                        <td><?= esc((string) ($item['day_name'] ?? '-')) ?></td>
                                        <td class="text-end"><strong><?= esc((string) ($item['scheduled_count'] ?? 0)) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>