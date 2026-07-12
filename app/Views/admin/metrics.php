<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Painel de Metricas<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
.sp-metric-card{border:1px solid var(--sp-border,rgba(0,0,0,.08));border-radius:10px;padding:1rem 1.25rem;background:var(--sp-card-bg,#fff)}
.sp-metric-val{font-size:1.8rem;font-weight:700;line-height:1;color:var(--sp-text-primary,#1a1a1a)}
.sp-metric-label{font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;color:var(--sp-text-muted,#888);margin-top:.25rem;font-weight:500}
.sp-chart-wrap{position:relative;height:200px}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$snap    = $snap    ?? [];
$series  = $series  ?? [];
$window  = $window  ?? 24;
$windows = [1=>'1h', 6=>'6h', 24=>'24h', 72=>'3d', 168=>'7d'];
$metaMap = [
    'errors_per_5min'        => ['label'=>'Erros / 5 min',           'color'=>'#dc3545'],
    'jobs_pending'           => ['label'=>'Jobs pendentes',           'color'=>'#fd7e14'],
    'jobs_failed'            => ['label'=>'Jobs com falha',           'color'=>'#b02a37'],
    'justifications_pending' => ['label'=>'Justificativas pendentes', 'color'=>'#ffc107'],
    'pending_punches'        => ['label'=>'Pontos a revisar',         'color'=>'#0dcaf0'],
    'employees_no_biometric' => ['label'=>'Sem biometria',            'color'=>'#6f42c1'],
    'punches_today'          => ['label'=>'Registros hoje',           'color'=>'#198754'],
    'employees_active_today' => ['label'=>'Ativos hoje',              'color'=>'#0d6efd'],
];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Painel de Metricas',
        'subtitle' => 'Series temporais coletadas a cada 5 minutos. Retencao: 30 dias.',
        'icon'     => 'bi bi-graph-up-arrow',
    ]) ?>

    <div class="mb-3 d-flex gap-2 flex-wrap align-items-center">
        <?php foreach($windows as $h=>$lbl): ?>
        <a href="?hours=<?= $h ?>" class="btn btn-sm <?= $window===$h ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
        <span class="ms-auto text-muted small">Atualiza a cada 5 min &bull; Retencao 30 dias</span>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach($metaMap as $key=>$meta): ?>
        <div class="col-6 col-md-3">
            <div class="sp-metric-card h-100">
                <div class="sp-metric-val"><?= number_format((float)($snap[$key] ?? 0)) ?></div>
                <div class="sp-metric-label"><?= esc($meta['label']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4">
        <?php foreach($metaMap as $key=>$meta): ?>
        <?php if(empty($series[$key])) continue; ?>
        <div class="col-12 col-md-6">
            <div class="sp-metric-card">
                <div class="fw-semibold small mb-2 text-muted"><?= esc($meta['label']) ?></div>
                <div class="sp-chart-wrap"><canvas id="chart_<?= $key ?>"></canvas></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" <?= csp_script_nonce_attr() ?>></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js" <?= csp_script_nonce_attr() ?>></script>
<script <?= csp_script_nonce_attr() ?>>
const SERIES = <?= json_encode($series) ?>;
const META   = <?= json_encode($metaMap) ?>;
const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
const gridC  = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.06)';
const lblC   = isDark ? '#999' : '#666';

Object.entries(SERIES).forEach(([key, pts]) => {
    const el = document.getElementById('chart_' + key);
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: { datasets: [{ data: pts,
            borderColor: META[key]?.color ?? '#0d6efd',
            backgroundColor: (META[key]?.color ?? '#0d6efd') + '22',
            borderWidth: 2, pointRadius: pts.length > 80 ? 0 : 3, tension: 0.35, fill: true }] },
        options: { responsive: true, maintainAspectRatio: false, parsing: false,
            scales: {
                x: { type: 'time', time: { tooltipFormat: 'dd/MM HH:mm' },
                     ticks: { color: lblC, maxTicksLimit: 7 }, grid: { color: gridC } },
                y: { beginAtZero: true, ticks: { color: lblC }, grid: { color: gridC } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>
<?= $this->endSection() ?>
