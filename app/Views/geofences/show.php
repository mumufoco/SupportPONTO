<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Detalhes da Geofence<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => esc($geofence->name ?? 'Geofence'),
        'subtitle' => 'Configuração completa da área geográfica e parâmetros operacionais.',
        'icon'     => 'bi bi-geo-alt-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_geofences_index_url()],
        ],
    ]) ?>

    <div class="row g-3">

        <!-- Dados principais -->
        <div class="col-lg-8">
            <div class="sp-profile-card h-100 mb-0">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-geo-alt-fill"></i> Dados Principais</h2>
                    <span class="sp-status <?= !empty($geofence->active) ? 'sp-status-active' : 'sp-status-inactive' ?>">
                        <?= !empty($geofence->active) ? 'Ativa' : 'Inativa' ?>
                    </span>
                </div>
                <div class="sp-profile-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted fw-normal">Nome</dt>
                                <dd class="col-7 fw-semibold"><?= esc($geofence->name ?? '–') ?></dd>

                                <dt class="col-5 text-muted fw-normal">Latitude</dt>
                                <dd class="col-7"><?= esc((string) ($geofence->center_lat ?? '–')) ?></dd>

                                <dt class="col-5 text-muted fw-normal">Longitude</dt>
                                <dd class="col-7"><?= esc((string) ($geofence->center_lng ?? '–')) ?></dd>

                                <dt class="col-5 text-muted fw-normal">Raio</dt>
                                <dd class="col-7"><?= esc((string) ($geofence->radius_meters ?? '–')) ?> m</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row small mb-0">
                                <dt class="col-5 text-muted fw-normal">Cor</dt>
                                <dd class="col-7 d-flex align-items-center gap-2">
                                    <span class="rounded-circle border" style="width:16px;height:16px;flex-shrink:0;background:<?= sp_style_color((string) ($geofence->color ?? '#4fa14f')) ?>"></span>
                                    <?= esc((string) ($geofence->color ?? '–')) ?>
                                </dd>

                                <dt class="col-5 text-muted fw-normal">Endereço</dt>
                                <dd class="col-7"><?= esc($geofence->address ?? '–') ?></dd>
                            </dl>
                        </div>
                        <?php if (!empty($geofence->description)): ?>
                            <div class="col-12">
                                <label class="small text-muted d-block mb-1">Descrição</label>
                                <p class="mb-0"><?= esc($geofence->description) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            <div class="sp-profile-card mb-0">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-lightning-charge-fill"></i> Ações Rápidas</h2>
                </div>
                <div class="sp-profile-card__body p-0">
                    <div class="list-group list-group-flush">
                        <a href="<?= sp_geofences_map_url() ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-map text-primary me-2"></i>
                            <strong>Ver mapa operacional</strong>
                            <small class="d-block text-muted">Visualizar esta geofence no mapa geral</small>
                        </a>
                        <?php if (!empty($geofence->center_lat) && !empty($geofence->center_lng)): ?>
                            <a href="https://www.google.com/maps?q=<?= urlencode((string) $geofence->center_lat . ',' . (string) $geofence->center_lng) ?>"
                               target="_blank" rel="noopener noreferrer" class="list-group-item list-group-item-action">
                                <i class="bi bi-box-arrow-up-right text-info me-2"></i>
                                <strong>Abrir no Google Maps</strong>
                                <small class="d-block text-muted">Ver as coordenadas em uma nova aba</small>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($geofence->id)): ?>
                            <a href="<?= sp_geofences_edit_url((int) $geofence->id) ?>" class="list-group-item list-group-item-action">
                                <i class="bi bi-pencil-square text-warning me-2"></i>
                                <strong>Editar Geofence</strong>
                                <small class="d-block text-muted">Alterar nome, coordenadas, raio ou status</small>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="sp-callout-neutral">
                <strong class="d-block mb-1 small">Informação</strong>
                <p class="text-muted small mb-0">
                    Responsável: <?= esc($employee['name'] ?? 'Administrador') ?><br>
                    Revise a geofence sempre que houver mudança de endereço, raio operacional ou local de registro autorizado.
                </p>
            </div>
        </div>

    </div>
</div>
<?= $this->endSection() ?>
