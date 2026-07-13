<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Backup<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Backup',
        'subtitle' => 'Política de backup automático, agendamento, destino e restauração.',
        'icon'     => 'bi bi-cloud-arrow-down-fill',
        'actions'  => [
            ['label' => 'Configurações', 'icon' => 'bi bi-grid-fill', 'url' => sp_admin_settings_index_url()],
        ],
    ]) ?>


    <form action="<?= sp_safe_url(sp_route_url('admin.settings.backup.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-gear-fill"></i> Configurações de Backup</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Backup automático</label>
                        <select class="form-select" name="backup_enabled">
                            <option value="1" <?= ($settings['backup_enabled'] ?? '0') === '1' ? 'selected' : '' ?>>Habilitado</option>
                            <option value="0" <?= ($settings['backup_enabled'] ?? '0') !== '1' ? 'selected' : '' ?>>Desabilitado</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Frequência</label>
                        <select class="form-select" name="backup_frequency">
                            <?php
                            $freqs = ['daily' => 'Diário', 'weekly' => 'Semanal', 'monthly' => 'Mensal'];
                            $curF = $settings['backup_frequency'] ?? 'daily';
                            foreach ($freqs as $k => $l): ?>
                                <option value="<?= esc($k) ?>" <?= $curF === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Retenção (dias)</label>
                        <input type="number" class="form-control" name="backup_retention_days"
                               value="<?= esc($settings['backup_retention_days'] ?? '30') ?>"
                               min="1" max="365">
                        <div class="form-text">Backups mais antigos são removidos automaticamente</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Destino</label>
                        <select class="form-select" name="backup_storage">
                            <?php
                            $storages = ['local' => 'Local (servidor)', 's3' => 'Amazon S3', 'gcs' => 'Google Cloud Storage'];
                            $curS = $settings['backup_storage'] ?? 'local';
                            foreach ($storages as $k => $l): ?>
                                <option value="<?= esc($k) ?>" <?= $curS === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-card mt-4">
            <div class="sp-card-body d-flex gap-2 justify-content-between align-items-center">
                <a href="<?= sp_safe_url(sp_route_url('settings.backup.download')) ?>"
                   class="btn btn-outline-secondary"
                   onclick="return confirm('Iniciar download do backup agora?')">
                    <i class="bi bi-download me-2"></i>Baixar backup agora
                </a>
                <div class="d-flex gap-2">
                    <a href="<?= sp_safe_url(sp_admin_settings_index_url()) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar Backup
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
