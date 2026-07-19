<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Backup<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Backup',
        'subtitle' => 'Status real do backup, histórico de checagens e registro de teste de restauração.',
        'icon'     => 'bi bi-cloud-arrow-down-fill',
        'actions'  => [
            ['label' => 'Configurações', 'icon' => 'bi bi-grid-fill', 'url' => sp_admin_settings_index_url()],
        ],
    ]) ?>

    <?php
        $status = $latestCheck->status ?? 'warning';
        $risks = $latestCheck->risks ?? [];
        $readinessOk = ($readiness['status'] ?? 'not_ready') === 'ready';
        $readinessLabels = [
            'runtime_policy'   => 'Política de runtime',
            'driver'           => 'Driver do banco',
            'backup_path'      => 'Diretório de backup',
            'database_config'  => 'Configuração do banco',
            'pg_dump'          => 'pg_dump',
            'psql'             => 'psql',
            'proc_open'        => 'proc_open',
        ];
    ?>

    <!-- Status do backup -->
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-shield-check"></i></span>
                Status do Backup
                <span class="sp-badge <?= $status === 'ok' ? 'sp-badge-success' : 'sp-badge-warning' ?>">
                    <?= $status === 'ok' ? 'OK' : 'Atenção' ?>
                </span>
            </h2>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="runBackupCheck(this)">
                <i class="bi bi-arrow-repeat me-1"></i>Verificar agora
            </button>
        </div>
        <div class="sp-data-card__body">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="small text-muted">Último backup</div>
                    <div class="fw-semibold"><?= esc(format_datetime_br($latestCheck->last_backup_at ?? null) ?: 'Nenhum') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Tamanho</div>
                    <div class="fw-semibold"><?= esc($backups[0]['size_human'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Destino</div>
                    <div class="fw-semibold small text-truncate" style="max-width:100%" title="<?= esc($latestCheck->destination ?? '') ?>">
                        <?= esc($latestCheck->destination ?? '-') ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Última checagem</div>
                    <div class="fw-semibold"><?= esc(format_datetime_br($latestCheck->checked_at ?? null, true) ?: '-') ?></div>
                </div>
            </div>

            <div id="backupCheckResult">
                <?php if (empty($risks)): ?>
                    <div class="alert alert-success py-2 mb-0 small">
                        <i class="bi bi-check-circle-fill me-1"></i>Nenhum risco identificado.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning py-2 mb-0 small">
                        <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Riscos identificados:</strong>
                        <ul class="mb-0 mt-1">
                            <?php foreach ($risks as $risk): ?>
                                <li><?= esc($risk) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$readinessOk): ?>
                <div class="mt-3 pt-3" style="border-top:1px solid var(--sp-border)">
                    <div class="small text-muted mb-2">Prontidão do ambiente:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($readiness['checks'] as $key => $check): ?>
                            <span class="sp-badge <?= ($check['status'] ?? 'error') === 'ok' ? 'sp-badge-success' : 'sp-badge-danger' ?>"
                                  title="<?= esc($check['message'] ?? '') ?>"><?= esc($readinessLabels[$key] ?? $key) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Retenção -->
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-gear-fill"></i></span>
                Retenção
            </h2>
        </div>
        <div class="sp-data-card__body">
            <form action="<?= sp_safe_url(sp_route_url('admin.settings.backup.retention')) ?>" method="POST" class="row g-3 align-items-end">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Retenção (dias)</label>
                    <input type="number" class="form-control" name="backup_retention_days"
                           value="<?= esc($retentionDays) ?>" min="1" max="365">
                    <div class="form-text">Backups locais mais antigos são removidos automaticamente.</div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Backups existentes -->
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-archive-fill"></i></span>
                Backups existentes
            </h2>
            <button type="button" class="btn btn-sm btn-primary" onclick="generateBackup()">
                <i class="bi bi-plus-lg me-1"></i>Gerar backup agora
            </button>
        </div>
        <div class="sp-data-card__body p-0">
            <?php if (empty($backups)): ?>
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-inbox"></i></div>
                    <p class="sp-empty-title">Nenhum backup encontrado</p>
                    <p class="text-muted small mb-0">Clique em <strong>Gerar backup agora</strong> para criar o primeiro.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Arquivo</th>
                                <th>Tamanho</th>
                                <th>Data</th>
                                <th>Idade</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td class="small text-break"><?= esc($backup['filename']) ?></td>
                                    <td><?= esc($backup['size_human']) ?></td>
                                    <td class="small"><?= esc(format_datetime_br($backup['date'] ?? null, true)) ?></td>
                                    <td>
                                        <span class="sp-badge <?= (int) $backup['age_days'] > 2 ? 'sp-badge-warning' : 'sp-badge-neutral' ?>">
                                            <?= (int) $backup['age_days'] ?> dia<?= (int) $backup['age_days'] !== 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <button type="button" class="icon-action" title="Baixar"
                                                    onclick="downloadBackup('<?= esc(addslashes($backup['filename']), 'js') ?>')">
                                                <i class="bi bi-download"></i>
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

    <!-- Teste de restauração -->
    <div class="sp-data-card mb-4">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-clipboard-check-fill"></i></span>
                Teste de Restauração
            </h2>
        </div>
        <div class="sp-data-card__body">
            <p class="text-muted small">
                Ter um backup salvo não garante que ele restaura corretamente. Registre aqui sempre que um
                backup for testado manualmente (ex.: restaurado em um ambiente separado de homologação).
            </p>
            <?php if ($latestRestoreTest): ?>
                <div class="alert alert-success py-2 mb-3 small">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Último teste registrado em <strong><?= esc(format_datetime_br($latestRestoreTest->tested_at ?? null, true)) ?></strong>
                    <?php if (!empty($latestRestoreTest->notes)): ?> — <?= esc($latestRestoreTest->notes) ?><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning py-2 mb-3 small">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Nenhum teste de restauração registrado ainda.
                </div>
            <?php endif; ?>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <input type="text" class="form-control form-control-sm" id="restoreTestNotes"
                       placeholder="Observações (opcional)" style="max-width:320px">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="recordRestoreTest()">
                    <i class="bi bi-check2-square me-1"></i>Registrar teste de restauração
                </button>
            </div>
        </div>
    </div>

</div>
<?= view('admin/settings/partials/backup_scripts') ?>
<?= $this->endSection() ?>
