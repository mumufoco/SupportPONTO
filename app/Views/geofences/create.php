<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Novo Limite Virtual<?= $this->endSection() ?>

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
        background-color: #f0f9ff;
        border-left: 4px solid #9DB89D;
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
                <i class="fas fa-plus-circle me-2"></i>Novo Limite Virtual
            </h2>
            <p class="text-muted mb-0 sp-text-sm-muted">Defina uma nova área geográfica de trabalho</p>
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
                        <i class="fas fa-map me-2"></i>Selecione a Localização no Mapa
                    </h5>
                </div>
                <div class="card-body">
                    <div class="map-instructions">
                        <h6 class="mb-2">
                            <i class="fas fa-info-circle me-2"></i>Instruções:
                        </h6>
                        <ol class="mb-0 ps-3">
                            <li>Clique no mapa para definir o centro da geofence</li>
                            <li>O marcador azul mostra a localização selecionada</li>
                            <li>O círculo mostra a área de cobertura (ajuste o raio no formulário)</li>
                            <li>Use os botões abaixo para obter sua localização atual</li>
                        </ol>
                    </div>

                    <div class="mb-3">
                        <button type="button" class="btn btn-primary" id="getLocationBtn">
                            <i class="fas fa-crosshairs me-2"></i>Usar Minha Localização Atual
                        </button>
                        <button type="button" class="btn btn-outline-secondary ms-2" id="resetMapBtn">
                            <i class="fas fa-redo me-2"></i>Resetar Mapa
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
                        <i class="fas fa-cog me-2"></i>Configurações do Limite Virtual
                    </h5>
                </div>
                <div class="card-body">
                    <form action="<?= sp_safe_url(sp_geofences_store_url()) ?>" method="POST" id="geofenceForm">
                        <?= csrf_field() ?>

                        <!-- Name -->
                        <div class="mb-3">
                            <label for="name" class="form-label">
                                Nome <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>"
                                   id="name"
                                   name="name"
                                   value="<?= sp_attr(old('name')) ?>"
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
                                      placeholder="Descrição opcional..."><?= sp_text(old('description')) ?></textarea>
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
                                           value="<?= sp_attr(old('latitude', '-23.550520')) ?>"
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
                                           value="<?= sp_attr(old('longitude', '-46.633308')) ?>"
                                           required
                                           readonly>
                                    <?php if (session('errors.longitude')): ?>
                                        <div class="invalid-feedback"><?= esc(session('errors.longitude')) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <small class="text-muted">Clique no mapa para atualizar as coordenadas</small>
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
                                   value="<?= sp_attr(old('radius_meters', '100')) ?>"
                                   required>
                            <div class="form-text">
                                <i class="fas fa-ruler me-1"></i>
                                <span id="radiusDisplay">100</span> metros
                                (mín: 10m, máx: 5000m)
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
                                       <?= old('active', true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="active">
                                    <i class="fas fa-check-circle me-1 text-success"></i>
                                    Ativar limite virtual imediatamente
                                </label>
                            </div>
                        </div>

                        <!-- Summary -->
                        <div class="alert alert-info" id="summary">
                            <h6 class="mb-2">
                                <i class="fas fa-info-circle me-2"></i>Resumo:
                            </h6>
                            <ul class="mb-0 small ps-3">
                                <li>Localização: <span id="summaryLocation">Não definida</span></li>
                                <li>Raio: <span id="summaryRadius">100</span> metros</li>
                                <li>Área: <span id="summaryArea">31,416</span> m²</li>
                            </ul>
                        </div>

                        <!-- Buttons -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Criar Limite Virtual
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

    // Fix Leaflet default marker icons broken when loaded via CDN
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });
    let map, marker, circle;
    const defaultLat = parseFloat(document.getElementById('latitude').value) || -23.550520;
    const defaultLng = parseFloat(document.getElementById('longitude').value) || -46.633308;
    const defaultRadius = parseInt(document.getElementById('radius_meters').value) || 100;

    // Initialize map
    function initMap() {
        map = L.map('map').setView([defaultLat, defaultLng], 15);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // Add marker and circle
        marker = L.marker([defaultLat, defaultLng], {
            draggable: true
        }).addTo(map);

        circle = L.circle([defaultLat, defaultLng], {
            color: '#2196f3',
            fillColor: '#2196f3',
            fillOpacity: 0.2,
            radius: defaultRadius
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

    // Update location (marker, circle, inputs)
    function updateLocation(lat, lng) {
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        map.panTo([lat, lng]);

        document.getElementById('latitude').value = lat.toFixed(6);
        document.getElementById('longitude').value = lng.toFixed(6);

        updateSummary();
    }

    // Update radius
    document.getElementById('radius_meters').addEventListener('input', function() {
        const radius = parseInt(this.value) || 100;
        circle.setRadius(radius);
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

    // Reset map
    document.getElementById('resetMapBtn').addEventListener('click', function() {
        updateLocation(defaultLat, defaultLng);
        map.setView([defaultLat, defaultLng], 15);
    });

    // Initialize on load
    document.addEventListener('DOMContentLoaded', initMap);
</script>
<?= $this->endSection() ?>
