<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Limites Virtuais<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Limites Virtuais',
        'subtitle' => 'Gerencie áreas permitidas para registro de ponto e acompanhe o panorama de cobertura operacional.',
        'icon' => 'bi bi-geo-alt-fill',
        'actions' => [
            ['label' => 'Novo Limite', 'icon' => 'bi bi-plus-lg', 'url' => sp_geofences_create_url()],
            ['label' => 'Ver mapa', 'icon' => 'bi bi-map', 'url' => sp_geofences_map_url()],
        ],
    ]) ?>

    <?php
    $activeCount = count(array_filter($geofences, fn($g) => $g->active));
    $inactiveCount = count($geofences) - $activeCount;
    $avgRadius = empty($geofences) ? 0 : array_sum(array_map(fn($g) => $g->radius_meters, $geofences)) / count($geofences);
    ?>

    <div class="sp-grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-geo-alt-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Total de limites</div>
                <div class="stat-card-value"><?= count($geofences) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Ativas</div>
                <div class="stat-card-value"><?= (int) $activeCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-pause-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Inativas</div>
                <div class="stat-card-value"><?= (int) $inactiveCount ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-bullseye"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Raio médio</div>
                <div class="stat-card-value"><?= round($avgRadius) ?> m</div>
            </div>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-list-ul"></i> Lista de limites virtuais</span>
        </div>
        <div class="sp-card-body p-0">
            <?php if (empty($geofences)): ?>
                <div class="sp-empty m-3">
                    <div class="sp-empty-icon"><i class="bi bi-geo-alt"></i></div>
                    <p class="sp-empty-title">Nenhum limite virtual cadastrado</p>
                    <a href="<?= sp_geofences_create_url() ?>" class="sp-btn sp-btn-primary sp-btn-sm">
                        <i class="bi bi-plus-lg"></i> Criar primeira geofence
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
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
                                            <i class="bi bi-geo-alt-fill me-1 text-primary"></i>
                                            <?= number_format($geofence->center_lat, 6) ?>, <?= number_format($geofence->center_lng, 6) ?>
                                        </small>
                                        <br>
                                        <a href="https://www.google.com/maps?q=<?= urlencode((string) $geofence->center_lat) ?>,<?= urlencode((string) $geofence->center_lng) ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none small">
                                            <i class="bi bi-box-arrow-up-right me-1"></i>Ver no Google Maps
                                        </a>
                                    </td>
                                    <td><span class="sp-badge sp-badge-info"><?= (int) $geofence->radius_meters ?> metros</span></td>
                                    <td>
                                        <span class="sp-badge <?= $geofence->active ? 'sp-badge-success' : 'sp-badge-danger' ?>">
                                            <i class="bi <?= $geofence->active ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                            <?= $geofence->active ? 'Ativa' : 'Inativa' ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= date('d/m/Y H:i', strtotime($geofence->created_at)) ?></small></td>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <a href="<?= sp_geofences_show_url((int) ($geofence->id ?? 0)) ?>" class="icon-action" title="Visualizar">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <a href="<?= sp_geofences_edit_url((int) ($geofence->id ?? 0)) ?>" class="icon-action icon-action-edit" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <form action="<?= sp_geofences_toggle_url((int) ($geofence->id ?? 0)) ?>" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="icon-action <?= $geofence->active ? 'icon-action-warning' : 'icon-action-success' ?>" title="<?= $geofence->active ? 'Desativar' : 'Ativar' ?>">
                                                    <i class="bi <?= $geofence->active ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="icon-action icon-action-danger" title="Excluir"
                                                    onclick="confirmDeleteGeofence(<?= (int) ($geofence->id ?? 0) ?>, '<?= esc(addslashes($geofence->name ?? ''), 'js') ?>')">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
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

<!-- Modal confirmação de exclusão -->
<div class="modal fade" id="deleteGeofenceModal" tabindex="-1" aria-labelledby="deleteGeofenceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger" id="deleteGeofenceModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar exclusão
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o limite virtual <strong id="deleteGeofenceName"></strong>?</p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta ação não pode ser desfeita.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteGeofenceForm" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash-fill me-2"></i>Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
function confirmDeleteGeofence(id, name) {
    document.getElementById('deleteGeofenceName').textContent = name;
    document.getElementById('deleteGeofenceForm').action = '<?= sp_geofences_delete_url(999999999) ?>'.replace('999999999', id);
    new bootstrap.Modal(document.getElementById('deleteGeofenceModal')).show();
}
</script>
<?= $this->endSection() ?>
