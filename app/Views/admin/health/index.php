<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Saúde do sistema<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$items = $items ?? [];
$details = $details ?? ['status' => 'unknown', 'alerts' => [], 'timestamp' => null, 'modules' => [], 'summary' => []];
$statusVariant = match ($details['status'] ?? 'unknown') {
    'healthy' => 'success',
    'degraded' => 'warning',
    default => 'danger',
};
$modules = $details['modules'] ?? [];
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Saúde do sistema',
        'subtitle' => 'Diagnóstico operacional de banco, permissões, migrations, versão, filas, DeepFace e logs, sem expor segredos sensíveis.',
        'icon' => 'bi bi-heart-pulse-fill',
        'actions' => [
                                            ],
    ]) ?>

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <?= view('components/status_pill', ['variant' => $statusVariant, 'label' => 'Saúde geral: ' . strtoupper((string) ($details['status'] ?? 'unknown'))]) ?>
        <small class="text-muted">Última leitura: <?= esc((string) ($details['timestamp'] ?? 'n/d')) ?></small>
        <small class="text-muted">Checks: <?= esc((string) ($details['summary']['checks_count'] ?? count($modules))) ?></small>
    </div>

    <div class="sp-health-grid">
        <?php foreach ($items as $item): ?>
            <div class="sp-health-card">
                <strong><?= esc((string) ($item['value'] ?? 'N/D')) ?></strong>
                <span><?= esc((string) ($item['label'] ?? 'Módulo')) ?></span>
                <div class="mt-3"><?= view('components/status_pill', ['variant' => $item['status'] ?? 'secondary', 'label' => ucfirst((string) ($item['status'] ?? 'unknown'))]) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mt-1">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Checks detalhados</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Status</th>
                                <th>Mensagem</th>
                                <th>Detalhes</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($modules as $key => $module): ?>
                                <?php
                                $variant = match ($module['status'] ?? 'error') {
                                    'ok' => 'success',
                                    'warning' => 'warning',
                                    default => 'danger',
                                };
                                $meta = $module['meta'] ?? [];
                                ?>
                                <tr>
                                    <td><strong><?= esc((string) ($module['label'] ?? $key)) ?></strong></td>
                                    <td><?= view('components/status_pill', ['variant' => $variant, 'label' => strtoupper((string) ($module['status'] ?? 'error'))]) ?></td>
                                    <td><?= esc((string) ($module['message'] ?? '')) ?></td>
                                    <td>
                                        <?php if (! empty($meta)): ?>
                                            <details>
                                                <summary>ver</summary>
                                                <pre class="small bg-light rounded p-2 mt-2 mb-0"><?= esc(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
                                            </details>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Alertas operacionais</h2>
                    <?php if (!empty($details['alerts'])): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($details['alerts'] as $alert): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-3 flex-wrap">
                                        <div>
                                            <strong><?= esc((string) ($alert['module'] ?? 'modulo')) ?></strong>
                                            <div class="text-muted small"><?= esc((string) ($alert['message'] ?? '')) ?></div>
                                        </div>
                                        <?= view('components/status_pill', [
                                            'variant' => (($alert['severity'] ?? '') === 'critical') ? 'danger' : 'warning',
                                            'label' => strtoupper((string) ($alert['severity'] ?? 'warning')),
                                        ]) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Nenhum alerta operacional ativo nesta leitura.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mt-4 mb-0">
        <strong>Endpoint interno:</strong> <code>/healthz</code> retorna liveness simples. Diagnóstico detalhado usa <code>/health/detailed</code> ou <code>/healthz/detailed</code> com admin autenticado ou header <code>X-Health-Token</code>.
    </div>
</div>
<?= $this->endSection() ?>
