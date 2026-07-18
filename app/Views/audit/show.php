<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Log de Auditoria #<?= (int) $log->id ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $entityType = $log->entity_type ?? $log->table_name ?? null;
    $entityId   = $log->entity_id   ?? $log->record_id   ?? null;
    $level      = strtolower((string) ($log->level ?? 'info'));
    $levelLabels = ['info' => 'Info', 'warning' => 'Atenção', 'error' => 'Erro', 'critical' => 'Crítico'];
    $levelBadges = ['info' => 'sp-badge-info', 'warning' => 'sp-badge-warning', 'error' => 'sp-badge-danger', 'critical' => 'sp-badge-danger'];
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Detalhe do Log de Auditoria',
        'subtitle' => 'Evento #' . (int) $log->id . ' — ' . esc($log->action ?? ''),
        'icon'     => 'bi bi-shield-exclamation',
        'actions'  => [
            ['label' => 'Voltar à auditoria', 'icon' => 'bi bi-arrow-left-circle', 'url' => route_to('audit')],
        ],
    ]) ?>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-info-circle-fill"></i></span>
                Informações do evento
            </h2>
            <span class="sp-badge <?= esc($levelBadges[$level] ?? 'sp-badge-neutral') ?>">
                <?php if ($level === 'critical'): ?><i class="bi bi-exclamation-triangle-fill me-1"></i><?php endif; ?>
                <?= esc($levelLabels[$level] ?? ucfirst($level)) ?>
            </span>
        </div>
        <div class="sp-data-card__body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">ID</div>
                    <div class="fw-semibold"><code>#<?= (int) $log->id ?></code></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Data / Hora</div>
                    <div class="fw-semibold"><?= esc(format_datetime_br((string) $log->created_at, false)) ?></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Ação</div>
                    <div><code class="small"><?= esc($log->action ?? '—') ?></code></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Usuário</div>
                    <div class="fw-semibold">
                        <?php if (isset($user) && $user): ?>
                            <i class="bi bi-person-fill text-muted me-1"></i><?= esc(is_object($user) ? $user->name : ($user['name'] ?? 'Desconhecido')) ?>
                        <?php else: ?>
                            <span class="text-muted fw-normal">Sistema</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Entidade</div>
                    <div>
                        <?php if ($entityType): ?>
                            <code class="small"><?= esc($entityType) ?></code>
                            <?php if ($entityId): ?><span class="text-muted ms-1">#<?= esc((string) $entityId) ?></span><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">IP</div>
                    <div><code class="small"><?= esc($log->ip_address ?? '—') ?></code></div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Método HTTP</div>
                    <div>
                        <?php if (!empty($log->method)): ?>
                            <span class="sp-badge sp-badge-primary"><?= esc($log->method) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">URL</div>
                    <div class="small text-break"><?= !empty($log->url) ? '<code>' . esc($log->url) . '</code>' : '<span class="text-muted">—</span>' ?></div>
                </div>

                <div class="col-12">
                    <div class="text-muted small">Descrição</div>
                    <?php if (!empty($log->description)): ?>
                        <p class="mb-0"><?= nl2br(esc($log->description)) ?></p>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($log->user_agent)): ?>
                <div class="col-12">
                    <div class="text-muted small">User Agent</div>
                    <div class="small text-muted text-break"><?= esc((string) $log->user_agent) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(25,135,84,.12);color:#198754;"><i class="bi bi-file-diff-fill"></i></span>
                Dados alterados
            </h2>
        </div>
        <div class="sp-data-card__body">
            <?php if ($oldData === null && $newData === null): ?>
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-file-earmark-x"></i></div>
                    <p class="sp-empty-title">Nenhuma alteração de dados registrada</p>
                    <p class="text-muted small mb-0">Este evento não capturou valores antes/depois (comum em ações de leitura, login ou exportação).</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php if ($oldData !== null): ?>
                    <div class="col-lg-6">
                        <small class="fw-semibold text-danger d-block mb-1"><i class="bi bi-dash-circle-fill me-1"></i>Antes</small>
                        <pre class="small mb-0 text-break bg-body-tertiary rounded p-3" style="max-height:400px;overflow-y:auto;white-space:pre-wrap;"><?= esc(json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                    <?php endif; ?>
                    <?php if ($newData !== null): ?>
                    <div class="col-lg-6">
                        <small class="fw-semibold text-success d-block mb-1"><i class="bi bi-plus-circle-fill me-1"></i>Depois</small>
                        <pre class="small mb-0 text-break bg-body-tertiary rounded p-3" style="max-height:400px;overflow-y:auto;white-space:pre-wrap;"><?= esc(json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
