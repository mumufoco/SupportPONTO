<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Diagnóstico de suporte<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$report = $report ?? [];
$release = $report['release'] ?? [];
$health = $report['health'] ?? [];
$installation = $report['installation'] ?? [];
$logs = $report['logs'] ?? [];
$statusVariant = match ($report['status'] ?? 'critical') {
    'ok' => 'success',
    'warning' => 'warning',
    default => 'danger',
};
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Diagnóstico de suporte',
        'subtitle' => 'Consolida health, pré-check de instalação e sinais operacionais para agilizar troubleshooting em produção.',
        'icon' => 'bi bi-life-preserver',
        'actions' => [
                    ],
    ]) ?>

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <?= view('components/status_pill', ['variant' => $statusVariant, 'label' => 'Status geral: ' . strtoupper((string) ($report['status'] ?? 'critical'))]) ?>
        <small class="text-muted">Release: <?= esc((string) ($release['release'] ?? 'n/d')) ?> / pacote <?= esc((string) ($release['package'] ?? 'n/d')) ?></small>
        <small class="text-muted">Gerado em: <?= esc((string) ($report['generated_at'] ?? 'n/d')) ?></small>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Alertas operacionais</div><div class="fs-3 fw-bold"><?= esc((string) ($health['summary']['alerts_count'] ?? 0)) ?></div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Módulos críticos</div><div class="fs-3 fw-bold"><?= esc((string) ($health['summary']['critical_modules'] ?? 0)) ?></div></div></div></div>
        <div class="col-md-4"><div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Módulos degradados</div><div class="fs-3 fw-bold"><?= esc((string) ($health['summary']['degraded_modules'] ?? 0)) ?></div></div></div></div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Pré-check de instalação / bootstrap</h2>
            <div class="list-group list-group-flush">
                <?php foreach (($installation['checks'] ?? []) as $check): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between gap-3 flex-wrap">
                            <div>
                                <strong><?= esc((string) ($check['label'] ?? 'check')) ?></strong>
                                <div class="text-muted small"><?= esc((string) ($check['details'] ?? '')) ?></div>
                            </div>
                            <?= view('components/status_pill', ['variant' => (($check['severity'] ?? '') === 'ok') ? 'success' : ((($check['severity'] ?? '') === 'warning') ? 'warning' : 'danger'), 'label' => strtoupper((string) ($check['severity'] ?? 'warning'))]) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h2 class="h5 mb-3">Logs e suporte operacional</h2>
            <p class="text-muted">Falhas totais: <strong><?= esc((string) ($logs['report']['total_errors'] ?? 0)) ?></strong> · Falhas de login: <strong><?= esc((string) ($logs['report']['login_failures'] ?? 0)) ?></strong> · Banco: <strong><?= esc((string) ($logs['report']['database_errors'] ?? 0)) ?></strong></p>
            <?php if (! empty($logs['active_alerts'])): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($logs['active_alerts'] as $alert): ?>
                        <div class="list-group-item px-0">
                            <strong><?= esc((string) ($alert['message'] ?? 'alerta')) ?></strong>
                            <div class="small text-muted">Tipo: <?= esc((string) ($alert['type'] ?? 'n/d')) ?> · Ocorrências: <?= esc((string) ($alert['count'] ?? 0)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">Nenhum alerta ativo detectado na janela monitorada.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
