<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Backup<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

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
    ?>

    <!-- Status do backup -->
    <div class="sp-card mb-3">
        <div class="sp-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="sp-card-title">
                <i class="bi bi-shield-check"></i> Status do Backup
                <span class="badge <?= $status === 'ok' ? 'bg-success' : 'bg-warning text-dark' ?> ms-2">
                    <?= $status === 'ok' ? 'OK' : 'Atenção' ?>
                </span>
            </span>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="runBackupCheck()">
                <i class="bi bi-arrow-repeat me-1"></i>Verificar agora
            </button>
        </div>
        <div class="sp-card-body">
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <div class="small text-muted">Último backup</div>
                    <div class="fw-semibold"><?= esc($latestCheck->last_backup_at ?? 'Nenhum') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Tamanho</div>
                    <div class="fw-semibold"><?= esc($backups[0]['size_human'] ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Destino</div>
                    <div class="fw-semibold small"><?= esc($latestCheck->destination ?? '-') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Última checagem</div>
                    <div class="fw-semibold"><?= esc($latestCheck->checked_at ?? '-') ?></div>
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
                    <?php foreach ($readiness['checks'] as $label => $check): ?>
                        <span class="badge <?= ($check['status'] ?? 'error') === 'ok' ? 'bg-success' : 'bg-danger' ?> me-1 mb-1"
                              title="<?= esc($check['message'] ?? '') ?>"><?= esc($label) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Retenção -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-gear-fill"></i> Retenção</span>
        </div>
        <div class="sp-card-body">
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
    <div class="sp-card mb-3">
        <div class="sp-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="sp-card-title"><i class="bi bi-archive-fill"></i> Backups existentes</span>
            <button type="button" class="btn btn-sm btn-primary" onclick="generateBackup()">
                <i class="bi bi-plus-lg me-1"></i>Gerar backup agora
            </button>
        </div>
        <div class="sp-card-body">
            <?php if (empty($backups)): ?>
                <div class="text-muted small text-center py-3">
                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>Nenhum backup encontrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
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
                                    <td class="small"><?= esc($backup['filename']) ?></td>
                                    <td><?= esc($backup['size_human']) ?></td>
                                    <td><?= esc($backup['date']) ?></td>
                                    <td><?= (int) $backup['age_days'] ?> dia(s)</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="downloadBackup('<?= esc(addslashes($backup['filename']), 'js') ?>')">
                                            <i class="bi bi-download me-1"></i>Baixar
                                        </button>
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
    <div class="sp-card mb-4">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-clipboard-check-fill"></i> Teste de Restauração</span>
        </div>
        <div class="sp-card-body">
            <p class="text-muted small">
                Ter um backup salvo não garante que ele restaura corretamente. Registre aqui sempre que um
                backup for testado manualmente (ex.: restaurado em um ambiente separado de homologação).
            </p>
            <?php if ($latestRestoreTest): ?>
                <div class="alert alert-success py-2 mb-3 small">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Último teste registrado em <strong><?= esc($latestRestoreTest->tested_at) ?></strong>
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
