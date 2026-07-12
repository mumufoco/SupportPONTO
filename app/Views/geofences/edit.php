<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Geofence<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #map {
        height: 500px;
        border-radius: 0.75rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    }

    .map-instructions {
        background-color: #fff8e1;
        border-left: 4px solid #F4C542;
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1rem;
    }

    .coordinate-display {
        font-family: 'Courier New', monospace;
        background-color: #f5f5f5;
        padding: 0.5rem;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1 sp-page-title-sage">
                <i class="fas fa-edit me-2"></i>Editar Geofence
            </h2>
            <p class="text-muted mb-0 sp-text-sm-muted">Modifique as configurações da área geográfica</p>
        </div>
        <a href="<?= sp_geofences_index_url() ?>" class="btn btn-outline-secondary sp-radius-lg">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Map Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-map me-2"></i>Atualizar Localização
                    </h5>
                </div>
                <div class="card-body">
                    <div class="map-instructions">
                        <h6 class="mb-2">
                            <i class="fas fa-lightbulb me-2"></i>Dica:
                        </h6>
                        <ul class="mb-0 ps-3">
                            <li>Clique no mapa ou arraste o marcador para alterar a localização</li>
                            <li>Ajuste o raio no formulário para visualizar a área de cobertura</li>
                            <li>Use sua localização atual se necessário</li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-primary" id="getLocationBtn">
                            <i class="fas fa-crosshairs me-2"></i>Usar Minha Localização Atual
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="resetMapBtn">
                            <i class="fas fa-undo me-2"></i>Restaurar Localização Original
                        </button>
                    </div>

                    <div id="map"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Form Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>Configurações
                    </h5>
                </div>
                <div class="card-body">
                    <form action="<?= sp_safe_url(sp_geofences_update_url((int) ($geofence->id ?? 0))) ?>" method="POST" id="geofenceForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="_method" value="PUT">

                        <!-- Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Nome <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>"
                                   id="name"
                                   name="name"
                                   value="<?= sp_attr(old('name', $geofence->name)) ?>"
                                   placeholder="Ex: Escritório Central"
                                   required>
                            <?php if (session('errors.name')): ?>
                                <div class="invalid-feedback"><?= esc(session('errors.name')) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descrição</label>
                            <textarea class="form-control <?= session('errors.description') ? 'is-invalid' : '' ?>"
                                      id="description"
                                      name="description"
                                      rows="3"
                                      placeholder="Descrição opcional..."><?= esc(old('description', $geofence->description)) ?></textarea>
                            <?php if (session('errors.description')): ?>
                                <div class="invalid-feedback"><?= esc(session('errors.description')) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Coordinates -->
                        <div class="mb-3">
                            <label class="form-label">
                                Coordenadas <span class="text-danger">*</span>
                            </label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number"
                                           class="form-control form-control-sm <?= session('errors.latitude') ? 'is-invalid' : '' ?>"
                                           id="latitude"
                                           name="latitude"
                                           step="0.000001"
                                           placeholder="Latitude"
                                           value="<?= sp_attr(old('latitude', $geofence->center_lat)) ?>"
                                           required
                                           readonly>
                                    <?php if (session('errors.latitude')): ?>
                                        <div class="invalid-feedback"><?= esc(session('errors.latitude')) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-6">
                                    <input type="number"
                                           class="form-control form-control-sm <?= session('errors.longitude') ? 'is-invalid' : '' ?>"
                                           id="longitude"
                                           name="longitude"
                                           step="0.000001"
                                           placeholder="Longitude"
                                           value="<?= sp_attr(old('longitude', $geofence->center_lng)) ?>"
                                           required
                                           readonly>
                                    <?php if (session('errors.longitude')): ?>
                                        <div class="invalid-feedback"><?= esc(session('errors.longitude')) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="text-muted">Clique no mapa para atualizar</small>
                        </div>

                        <!-- Radius -->
                        <div class="mb-3">
                            <label for="radius_meters" class="form-label">
                                Raio (metros) <span class="text-danger">*</span>
                            </label>
                            <input type="number"
                                   class="form-control <?= session('errors.radius_meters') ? 'is-invalid' : '' ?>"
                                   id="radius_meters"
                                   name="radius_meters"
                                   min="10"
                                   max="5000"
                                   value="<?= sp_attr(old('radius_meters', $geofence->radius_meters)) ?>"
                                   required>
                            <div class="form-text">
                                <i class="fas fa-ruler me-1"></i>
                                <span id="radiusDisplay"><?= (int) $geofence->radius_meters ?></span> metros
                            </div>
                            <?php if (session('errors.radius_meters')): ?>
                                <div class="invalid-feedback"><?= esc(session('errors.radius_meters')) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Active Status -->
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       id="active"
                                       name="active"
                                       <?= old('active', $geofence->active) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="active">
                                    <i class="fas fa-check-circle me-1 text-success"></i>
                                    Geofence ativa
                                </label>
                            </div>
                        </div>

                        <!-- Info Box -->
                        <div class="alert alert-warning">
                            <h6 class="mb-2">
                                <i class="fas fa-clock me-2"></i>Informações:
                            </h6>
                            <ul class="mb-0 small ps-3">
                                <li>Criado em: <?= date('d/m/Y H:i', strtotime($geofence->created_at)) ?></li>
                                <li>ID: #<?= (int) $geofence->id ?></li>
                                <li>Status: <?= $geofence->active ? 'Ativa' : 'Inativa' ?></li>
                            </ul>
                        </div>

                        <!-- Summary -->
                        <div class="alert alert-info" id="summary">
                            <h6 class="mb-2">
                                <i class="fas fa-info-circle me-2"></i>Resumo:
                            </h6>
                            <ul class="mb-0 small ps-3">
                                <li>Localização: <span id="summaryLocation">-</span></li>
                                <li>Raio: <span id="summaryRadius">-</span> metros</li>
                                <li>Área: <span id="summaryArea">-</span> m²</li>
                            </ul>
                        </div>

                        <!-- Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Salvar Alterações
                            </button>
                            <a href="<?= sp_geofences_index_url() ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<!-- Leaflet JS -->
<script <?= csp_script_nonce_attr() ?> src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Geolocator -->
<script <?= csp_script_nonce_attr() ?> src="<?= sp_safe_url(asset_url('assets/js/geolocator.js')) ?>"></script>

<script <?= csp_script_nonce_attr() ?>>
    let map, marker, circle;
    const originalLat = parseFloat(<?= json_encode((float) $geofence->center_lat, JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    const originalLng = parseFloat(<?= json_encode((float) $geofence->center_lng, JSON_HEX_TAG | JSON_HEX_AMP) ?>);
    const originalRadius = parseInt(<?= json_encode((int) $geofence->radius_meters, JSON_HEX_TAG | JSON_HEX_AMP) ?>);

    let currentLat = parseFloat(document.getElementById('latitude').value) || originalLat;
    let currentLng = parseFloat(document.getElementById('longitude').value) || originalLng;
    let currentRadius = parseInt(document.getElementById('radius_meters').value) || originalRadius;

    // Initialize map
    function initMap() {
        map = L.map('map').setView([currentLat, currentLng], 16);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Add marker (draggable)
        marker = L.marker([currentLat, currentLng], {
            draggable: true
        }).addTo(map);

        // Add circle
        circle = L.circle([currentLat, currentLng], {
            color: '#ff9800',
            fillColor: '#ff9800',
            fillOpacity: 0.2,
            radius: currentRadius
        }).addTo(map);

        // Click on map to set location
        map.on('click', function(e) {
            updateLocation(e.latlng.lat, e.latlng.lng);
        });

        // Drag marker
        marker.on('dragend', function(e) {
            const pos = marker.getLatLng();
            updateLocation(pos.lat, pos.lng);
        });

        updateSummary();
    }

    // Update location
    function updateLocation(lat, lng) {
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        map.panTo([lat, lng]);

        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);

        currentLat = lat;
        currentLng = lng;

        updateSummary();
    }

    // Update radius
    document.getElementById('radius_meters').addEventListener('input', function() {
        const radius = parseInt(this.value) || originalRadius;
        circle.setRadius(radius);
        currentRadius = radius;
        document.getElementById('radiusDisplay').textContent = radius;
        updateSummary();
    });

    // Update summary
    function updateSummary() {
        const lat = parseFloat(document.getElementById('latitude').value);
        const lng = parseFloat(document.getElementById('longitude').value);
        const radius = parseInt(document.getElementById('radius_meters').value);

        document.getElementById('summaryLocation').textContent =
            `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        document.getElementById('summaryRadius').textContent = radius;

        // Calculate area (πr²)
        const area = Math.PI * radius * radius;
        document.getElementById('summaryArea').textContent = area.toLocaleString('pt-BR', {
            maximumFractionDigits: 0
        });
    }

    // Get current location
    document.getElementById('getLocationBtn').addEventListener('click', function() {
        Geolocator.requestLocation(
            function(position) {
                updateLocation(position.lat, position.lng);
                map.setView([position.lat, position.lng], 17);
            },
            function(error) {
                alert('Erro ao obter localização: ' + error.message);
            }
        );
    });

    // Reset to original location
    document.getElementById('resetMapBtn').addEventListener('click', function() {
        updateLocation(originalLat, originalLng);
        document.getElementById('radius_meters').value = originalRadius;
        circle.setRadius(originalRadius);
        document.getElementById('radiusDisplay').textContent = originalRadius;
        map.setView([originalLat, originalLng], 16);
        updateSummary();
    });

    // Initialize on load
    document.addEventListener('DOMContentLoaded', initMap);
</script>
<?= $this->endSection() ?>
