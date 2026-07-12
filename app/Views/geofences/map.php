<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Mapa de Limites Virtuais<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
#geofence-map {
    height: 560px;
    border-radius: var(--sp-radius-lg, 0.75rem);
    box-shadow: 0 2px 8px rgba(0,0,0,0.10);
    z-index: 0;
}
.gf-list-item {
    padding: .65rem .75rem;
    border-radius: var(--sp-radius-md, .5rem);
    cursor: pointer;
    border: 1px solid transparent;
    transition: background .15s, border-color .15s;
}
.gf-list-item:hover { background: var(--sp-surface-hover, #f5f5f5); border-color: var(--sp-border, #e0e0e0); }
.gf-list-item.active  { background: var(--sp-primary-pale, #e8f5e9); border-color: var(--sp-primary, #4caf50); }
.gf-dot {
    width: 12px; height: 12px; border-radius: 50%;
    display: inline-block; flex-shrink: 0; margin-top: 3px;
}
.gf-empty { text-align: center; color: var(--sp-text-muted, #888); padding: 2rem 1rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Mapa de Limites Virtuais',
        'subtitle' => 'Visualize e gerencie todas as áreas geográficas cadastradas para controle de ponto.',
        'icon'     => 'bi bi-geo-alt-fill',
        'actions'  => [
            ['label' => 'Novo Limite', 'icon' => 'bi bi-plus-circle-fill', 'url' => sp_geofences_create_url()],
                    ],
    ]) ?>

    <!-- Filtros -->
    <div class="sp-filter-toolbar mb-3">
        <form method="GET" action="<?= sp_geofences_map_url() ?>" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small mb-1" for="search">Buscar</label>
                <input type="text" id="search" name="search" class="form-control form-control-sm"
                       value="<?= esc($filters['search'] ?? '') ?>" placeholder="Nome do limite virtual">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="status">Status</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="active"   <?= (($filters['status'] ?? '') === 'active')   ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="bi bi-search me-1"></i>Filtrar
                </button>
                <a href="<?= sp_geofences_map_url() ?>" class="btn btn-outline-secondary btn-sm flex-fill">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Limpar
                </a>
            </div>
        </form>
    </div>

    <div class="row g-3">

        <!-- Mapa -->
        <div class="col-lg-8">
            <div class="sp-card">
                <div class="sp-card-header d-flex justify-content-between align-items-center">
                    <span class="sp-card-title"><i class="bi bi-map-fill me-2"></i>Mapa operacional</span>
                    <span class="badge bg-secondary" id="map-count">
                        <?= count($geofences) ?> limite<?= count($geofences) !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <div class="sp-card-body p-2">
                    <div id="geofence-map"></div>
                </div>
            </div>
        </div>

        <!-- Lista lateral -->
        <div class="col-lg-4">
            <div class="sp-card h-100">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-list-ul me-2"></i>Limites cadastrados</span>
                </div>
                <div class="sp-card-body p-2" style="max-height:600px;overflow-y:auto;">
                    <?php if (empty($geofences)): ?>
                        <div class="gf-empty">
                            <i class="bi bi-geo-alt fs-2 d-block mb-2"></i>
                            Nenhum limite virtual cadastrado.
                            <div class="mt-2">
                                <a href="<?= sp_geofences_create_url() ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-plus-circle-fill me-1"></i>Criar primeiro
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-1" id="gf-list">
                            <?php foreach ($geofences as $g): ?>
                                <?php
                                    $color  = !empty($g->color) ? esc($g->color) : '#4caf50';
                                    $active = !empty($g->active);
                                ?>
                                <div class="gf-list-item d-flex gap-2"
                                     data-id="<?= (int) $g->id ?>"
                                     data-lat="<?= (float) $g->center_lat ?>"
                                     data-lng="<?= (float) $g->center_lng ?>">
                                    <span class="gf-dot mt-1" style="background:<?= $color ?>;opacity:<?= $active ? '1' : '.4' ?>;"></span>
                                    <div class="flex-fill overflow-hidden">
                                        <div class="fw-semibold text-truncate" style="font-size:.9rem;">
                                            <?= esc($g->name) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.78rem;">
                                            <i class="bi bi-circle me-1"></i><?= number_format((float) $g->radius_meters) ?> m
                                            &nbsp;·&nbsp;
                                            <?php if ($active): ?>
                                                <span class="text-success"><i class="bi bi-check-circle-fill"></i> Ativo</span>
                                            <?php else: ?>
                                                <span class="text-secondary"><i class="bi bi-x-circle-fill"></i> Inativo</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-column gap-1 align-items-end" style="flex-shrink:0;">
                                        <a href="<?= sp_route_url('geofences.show', (int) $g->id) ?>"
                                           class="btn btn-outline-secondary btn-sm py-0 px-1" title="Detalhes">
                                            <i class="bi bi-eye-fill" style="font-size:.7rem;"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?> src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';


    // Fix Leaflet default marker icons broken when loaded via CDN
    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconUrl:       'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
        shadowUrl:     'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    });
    // Geofences passadas via PHP → JS
    const GEOFENCES = <?= json_encode(array_map(function($g) {
        return [
            'id'          => (int)   $g->id,
            'name'        => (string)$g->name,
            'description' => (string)($g->description ?? ''),
            'lat'         => (float) $g->center_lat,
            'lng'         => (float) $g->center_lng,
            'radius'      => (float) $g->radius_meters,
            'active'      => !empty($g->active),
            'color'       => !empty($g->color) ? $g->color : '#4caf50',
        ];
    }, $geofences ?? []), JSON_UNESCAPED_UNICODE) ?>;

    // Centro padrão: Brasil
    const DEFAULT_CENTER = [-14.235, -51.925];
    const DEFAULT_ZOOM   = 4;

    const map = L.map('geofence-map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19,
    }).addTo(map);

    // Layers por geofence
    const layers = {};

    function hexToRgb(hex) {
        const r = parseInt(hex.slice(1,3),16);
        const g = parseInt(hex.slice(3,5),16);
        const b = parseInt(hex.slice(5,7),16);
        return 'rgb(' + r + ',' + g + ',' + b + ')';
    }

    const bounds = [];

    GEOFENCES.forEach(function(g) {
        const opacity = g.active ? 0.22 : 0.08;
        const strokeOp = g.active ? 0.9 : 0.35;

        const circle = L.circle([g.lat, g.lng], {
            radius:      g.radius,
            color:       g.color,
            fillColor:   g.color,
            fillOpacity: opacity,
            weight:      g.active ? 2 : 1,
            opacity:     strokeOp,
            dashArray:   g.active ? null : '6 4',
        }).addTo(map);

        const statusBadge = g.active
            ? '<span style="background:#4caf50;color:#fff;padding:2px 8px;border-radius:20px;font-size:.75rem;">Ativo</span>'
            : '<span style="background:#9e9e9e;color:#fff;padding:2px 8px;border-radius:20px;font-size:.75rem;">Inativo</span>';

        const desc = g.description
            ? '<p style="margin:.25rem 0;color:#666;font-size:.82rem;">' + g.description + '</p>'
            : '';

        circle.bindPopup(
            '<div style="min-width:180px;">' +
                '<strong style="font-size:.95rem;">' + g.name + '</strong>' +
                '<div style="margin:.3rem 0;">' + statusBadge + '</div>' +
                desc +
                '<div style="font-size:.8rem;color:#555;margin-top:.4rem;">' +
                    '<i class="bi bi-circle"></i> Raio: <strong>' + g.radius.toLocaleString('pt-BR') + ' m</strong>' +
                '</div>' +
                '<div style="font-size:.75rem;color:#888;margin-top:.25rem;">' +
                    g.lat.toFixed(6) + ', ' + g.lng.toFixed(6) +
                '</div>' +
            '</div>',
            { maxWidth: 260 }
        );

        // Marcador central
        const marker = L.circleMarker([g.lat, g.lng], {
            radius: 5,
            color: g.color,
            fillColor: g.color,
            fillOpacity: g.active ? 0.9 : 0.4,
            weight: 2,
        }).addTo(map);
        marker.bindPopup(circle.getPopup());

        layers[g.id] = { circle, marker };
        bounds.push([g.lat, g.lng]);
    });

    // Ajustar zoom para mostrar todos os limites
    if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40] });
    }

    // Clique na lista → voar para geofence no mapa
    document.querySelectorAll('.gf-list-item').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target.closest('a')) return; // não interceptar clique no link de detalhes
            const id  = parseInt(this.dataset.id);
            const lat = parseFloat(this.dataset.lat);
            const lng = parseFloat(this.dataset.lng);

            // Destacar item na lista
            document.querySelectorAll('.gf-list-item').forEach(function(x) { x.classList.remove('active'); });
            this.classList.add('active');

            // Centralizar mapa
            map.flyTo([lat, lng], 15, { duration: 1 });

            // Abrir popup
            if (layers[id]) {
                setTimeout(function() { layers[id].circle.openPopup(); }, 900);
            }
        });
    });

})();
</script>
<?= $this->endSection() ?>
