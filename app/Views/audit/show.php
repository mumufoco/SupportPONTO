<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Log de Auditoria #<?= (int) $log->id ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $entityType = $log->entity_type ?? $log->table_name ?? null;
    $entityId   = $log->entity_id   ?? $log->record_id   ?? null;
    $level      = $log->level ?? 'info';
    $levelMap   = ['error' => 'danger', 'critical' => 'dark', 'warning' => 'warning', 'info' => 'info'];
    $levelClass = $levelMap[$level] ?? 'secondary';
    $textClass  = in_array($level, ['warning', 'info']) ? 'text-dark' : '';
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Detalhe do Log de Auditoria',
        'subtitle' => 'Evento #' . (int) $log->id . ' — ' . esc($log->action ?? ''),
        'icon'     => 'bi bi-shield-exclamation',
        'actions'  => [
            ['label' => 'Voltar à auditoria', 'icon' => 'bi bi-arrow-left-circle', 'url' => base_url('audit')],
        ],
    ]) ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title"><i class="bi bi-info-circle-fill"></i>Informações do Evento</h2>
                </div>
                <div class="sp-data-card__body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted small">ID</dt>
                        <dd class="col-sm-8"><code><?= (int) $log->id ?></code></dd>

                        <dt class="col-sm-4 text-muted small">Data / Hora</dt>
                        <dd class="col-sm-8"><?= esc(date('d/m/Y H:i:s', strtotime((string) $log->created_at))) ?></dd>

                        <dt class="col-sm-4 text-muted small">Ação</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-secondary font-monospace"><?= esc($log->action ?? '-') ?></span>
                        </dd>

                        <dt class="col-sm-4 text-muted small">Nível</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $levelClass ?> <?= $textClass ?>">
                                <?= esc(strtoupper($level)) ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4 text-muted small">Usuário</dt>
                        <dd class="col-sm-8">
                            <?php if (isset($user) && $user): ?>
                                <i class="bi bi-person-fill me-1"></i><?= esc(is_object($user) ? $user->name : ($user['name'] ?? 'Desconhecido')) ?>
                            <?php else: ?>
                                <em class="text-muted">Sistema</em>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4 text-muted small">Entidade</dt>
                        <dd class="col-sm-8">
                            <?php if ($entityType): ?>
                                <code><?= esc($entityType) ?></code>
                                <?php if ($entityId): ?><span class="text-muted ms-1">#<?= esc((string) $entityId) ?></span><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4 text-muted small">Descrição</dt>
                        <dd class="col-sm-8">
                            <?php if (!empty($log->description)): ?>
                                <p class="mb-0 small"><?= nl2br(esc($log->description)) ?></p>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </dd>

                        <?php if (!empty($log->ip_address)): ?>
                        <dt class="col-sm-4 text-muted small">IP</dt>
                        <dd class="col-sm-8"><code><?= esc($log->ip_address) ?></code></dd>
                        <?php endif; ?>

                        <?php if (!empty($log->url)): ?>
                        <dt class="col-sm-4 text-muted small">URL</dt>
                        <dd class="col-sm-8"><small><code><?= esc($log->url) ?></code></small></dd>
                        <?php endif; ?>

                        <?php if (!empty($log->method)): ?>
                        <dt class="col-sm-4 text-muted small">Método HTTP</dt>
                        <dd class="col-sm-8"><span class="badge bg-primary"><?= esc($log->method) ?></span></dd>
                        <?php endif; ?>

                        <?php if (!empty($log->user_agent)): ?>
                        <dt class="col-sm-4 text-muted small">User Agent</dt>
                        <dd class="col-sm-8"><small class="text-muted"><?= esc(substr((string)($log->user_agent ?? ''), 0, 150)) ?></small></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($oldData !== null || $newData !== null): ?>
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title"><i class="bi bi-file-diff-fill"></i>Dados Alterados</h2>
                </div>
                <div class="sp-data-card__body p-0">
                    <?php if ($oldData !== null): ?>
                    <div class="p-3 border-bottom">
                        <small class="fw-semibold text-danger d-block mb-1"><i class="bi bi-dash-circle-fill me-1"></i>Antes</small>
                        <pre class="small mb-0 text-break" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;"><?= esc(json_encode($oldData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                    <?php endif; ?>
                    <?php if ($newData !== null): ?>
                    <div class="p-3">
                        <small class="fw-semibold text-success d-block mb-1"><i class="bi bi-plus-circle-fill me-1"></i>Depois</small>
                        <pre class="small mb-0 text-break" style="max-height:200px;overflow-y:auto;white-space:pre-wrap;"><?= esc(json_encode($newData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
