<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Limites Virtuais<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Limites Virtuais',
        'subtitle' => 'Gerencie áreas permitidas para registro de ponto e acompanhe o panorama de cobertura operacional.',
        'icon' => 'fas fa-map-marker-alt',
        'actions' => [
            ['label' => 'Novo Limite', 'icon' => 'fas fa-plus', 'url' => sp_geofences_create_url()],
            ['label' => 'Ver mapa', 'icon' => 'fas fa-map', 'url' => sp_geofences_map_url(), 'variant' => 'outline-primary'],
        ],
    ]) ?>

    <?php
    $activeCount = count(array_filter($geofences, fn($g) => $g->active));
    $inactiveCount = count($geofences) - $activeCount;
    $avgRadius = empty($geofences) ? 0 : array_sum(array_map(fn($g) => $g->radius_meters, $geofences)) / count($geofences);
    ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'fas fa-map-marked-alt',
                'iconColor' => 'primary',
                'value' => count($geofences),
                'label' => 'Total de limites',
            ]) ?>
        </div>
        <div class="col-md-3">
            <div class="card sp-surface-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Ativas</h6>
                        <h3 class="mb-0 text-success"><?= (int) $activeCount ?></h3>
                    </div>
                    <div class="sp-kpi-icon-surface success">
                        <i class="fas fa-check-circle text-success fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card sp-surface-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Inativas</h6>
                        <h3 class="mb-0 text-warning"><?= (int) $inactiveCount ?></h3>
                    </div>
                    <div class="sp-kpi-icon-surface warning">
                        <i class="fas fa-pause-circle text-warning fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card sp-surface-card h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Raio médio</h6>
                        <h3 class="mb-0"><?= round($avgRadius) ?> m</h3>
                    </div>
                    <div class="sp-kpi-icon-surface danger">
                        <i class="fas fa-draw-circle text-danger fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card sp-surface-card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Lista de limites virtuais</h5>
        </div>
        <div class="card-body">
            <?php if (empty($geofences)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-map-marker-alt fa-4x text-muted mb-3 opacity-50"></i>
                    <h5 class="text-muted">Nenhum limite virtual cadastrado</h5>
                    <p class="text-muted mb-4">Crie sua primeira cerca virtual para começar a monitorar localizações.</p>
                    <a href="<?= sp_geofences_create_url() ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Criar primeira geofence
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="geofencesTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Nome</th>
                                <th>Descrição</th>
                                <th>Coordenadas</th>
                                <th>Raio</th>
                                <th>Status</th>
                                <th>Criado em</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($geofences as $geofence): ?>
                                <tr>
                                    <td><?= (int) $geofence->id ?></td>
                                    <td><strong><?= esc($geofence->name) ?></strong></td>
                                    <td><small class="text-muted"><?= esc($geofence->description ?: '-') ?></small></td>
                                    <td>
                                        <small class="font-monospace">
                                            <i class="fas fa-location-dot me-1 text-primary"></i>
                                            <?= number_format($geofence->center_lat, 6) ?>, <?= number_format($geofence->center_lng, 6) ?>
                                        </small>
                                        <br>
                                        <a href="https://www.google.com/maps?q=<?= urlencode((string) $geofence->center_lat) ?>,<?= urlencode((string) $geofence->center_lng) ?>" target="_blank" class="text-decoration-none small">
                                            <i class="fas fa-external-link-alt me-1"></i>Ver no Google Maps
                                        </a>
                                    </td>
                                    <td><span class="badge bg-info"><?= (int) $geofence->radius_meters ?> metros</span></td>
                                    <td>
                                        <span class="badge <?= $geofence->active ? 'bg-success' : 'bg-secondary' ?>">
                                            <i class="fas fa-<?= $geofence->active ? 'check' : 'times' ?> me-1"></i>
                                            <?= $geofence->active ? 'Ativa' : 'Inativa' ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($geofence->created_at)) ?></small></td>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <a href="<?= sp_geofences_show_url((int) ($geofence->id ?? 0)) ?>" class="icon-action" title="Visualizar">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="<?= sp_geofences_edit_url((int) ($geofence->id ?? 0)) ?>" class="icon-action icon-action-edit" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="<?= sp_geofences_toggle_url((int) ($geofence->id ?? 0)) ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="icon-action <?= $geofence->active ? 'icon-action-warning' : 'icon-action-success' ?>" title="<?= $geofence->active ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="fas fa-<?= $geofence->active ? 'toggle-on' : 'toggle-off' ?>"></i>
                                                </button>
                                            </form>
                                            <form action="<?= sp_geofences_delete_url((int) ($geofence->id ?? 0)) ?>" method="POST" class="d-inline" onsubmit="return confirm('Deseja realmente excluir este limite virtual?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit" class="icon-action icon-action-danger" title="Excluir">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
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
