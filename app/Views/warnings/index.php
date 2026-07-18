<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Advertências<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Advertências e medidas disciplinares',
        'subtitle' => 'Acompanhe ocorrências, emita novos registros e monitore o andamento de assinaturas.',
        'icon'     => 'bi bi-exclamation-triangle-fill',
        'actions'  => in_array($employee['role'] ?? '', ['admin', 'gestor', 'rh'])
            ? [['label' => 'Nova advertência', 'icon' => 'bi bi-plus-circle', 'url' => sp_warning_create_url()]]
            : [],
    ]) ?>

    <?php if (!empty($warning)): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc((string) $warning) ?></div>
    <?php endif; ?>

    <div class="sp-grid-4 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Total</div>
                <div class="stat-card-value"><?= esc((string) ($counts['all'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Pendentes</div>
                <div class="stat-card-value"><?= esc((string) ($counts['pendente'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Assinadas</div>
                <div class="stat-card-value"><?= esc((string) ($counts['assinado'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon danger"><i class="bi bi-x-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Recusadas</div>
                <div class="stat-card-value"><?= esc((string) ($counts['recusado'] ?? 0)) ?></div>
            </div>
        </div>
    </div>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label for="warning_type" class="form-label">Tipo</label>
                    <select name="warning_type" id="warning_type" class="form-select" onchange="this.form.submit()">
                        <option value="all"       <?= ($warningType ?? '') === 'all'       ? 'selected' : '' ?>>Todos</option>
                        <option value="verbal"    <?= ($warningType ?? '') === 'verbal'    ? 'selected' : '' ?>>Verbal (<?= esc((string) ($counts['verbal'] ?? 0)) ?>)</option>
                        <option value="escrita"   <?= ($warningType ?? '') === 'escrita'   ? 'selected' : '' ?>>Escrita (<?= esc((string) ($counts['escrita'] ?? 0)) ?>)</option>
                        <option value="suspensao" <?= ($warningType ?? '') === 'suspensao' ? 'selected' : '' ?>>Suspensão (<?= esc((string) ($counts['suspensao'] ?? 0)) ?>)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                        <option value="all"                 <?= ($status ?? '') === 'all'                 ? 'selected' : '' ?>>Todos</option>
                        <option value="pendente-assinatura"  <?= ($status ?? '') === 'pendente-assinatura'  ? 'selected' : '' ?>>Pendente (<?= esc((string) ($counts['pendente'] ?? 0)) ?>)</option>
                        <option value="assinado"             <?= ($status ?? '') === 'assinado'             ? 'selected' : '' ?>>Assinado (<?= esc((string) ($counts['assinado'] ?? 0)) ?>)</option>
                        <option value="recusado"             <?= ($status ?? '') === 'recusado'             ? 'selected' : '' ?>>Recusado (<?= esc((string) ($counts['recusado'] ?? 0)) ?>)</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <?php if (empty($warnings ?? [])): ?>
            <div class="sp-data-card__body">
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-inbox"></i></div>
                    <p class="sp-empty-title">Nenhuma advertência encontrada</p>
                    <p class="text-muted small mb-3">Altere os filtros ou registre uma nova ocorrência.</p>
                    <?php if (in_array($employee['role'] ?? '', ['admin', 'gestor', 'rh'])): ?>
                        <a href="<?= sp_warning_create_url() ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle me-1"></i>Nova advertência
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Funcionário</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Emitida por</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warnings as $warning): ?>
                            <tr>
                                <td><?= esc(format_date_br((string) $warning->occurrence_date)) ?></td>
                                <td>
                                    <strong><?= esc($warning->employee_name) ?></strong>
                                    <a href="<?= sp_warning_dashboard_url((int) $warning->employee_id) ?>"
                                       class="text-muted ms-1" title="Dashboard do funcionário">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                </td>
                                <td>
                                    <?php $typeBadges = [
                                        'verbal'    => '<span class="sp-badge sp-badge-warning">Verbal</span>',
                                        'escrita'   => '<span class="sp-badge sp-badge-danger">Escrita</span>',
                                        'suspensao' => '<span class="sp-badge sp-badge-neutral">Suspensão</span>',
                                    ]; ?>
                                    <?= $typeBadges[$warning->warning_type] ?? '' ?>
                                </td>
                                <td style="max-width:240px;">
                                    <?= esc(mb_strimwidth((string) $warning->reason, 0, 70, '…')) ?>
                                </td>
                                <td><?= esc($warning->issuer_name) ?></td>
                                <td>
                                    <?php $statusBadges = [
                                        'pendente-assinatura' => '<span class="sp-badge sp-badge-warning">Pendente</span>',
                                        'assinado'            => '<span class="sp-badge sp-badge-success">Assinado</span>',
                                        'recusado'            => '<span class="sp-badge sp-badge-danger">Recusado</span>',
                                    ]; ?>
                                    <?= $statusBadges[$warning->status] ?? '' ?>
                                </td>
                                <td>
                                    <div class="table-icon-actions">
                                        <a href="<?= sp_warning_show_url((int) $warning->id) ?>" class="icon-action" title="Visualizar">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        <?php if ($warning->pdf_path): ?>
                                            <a href="<?= sp_warning_download_url((int) $warning->id) ?>" class="icon-action icon-action-success" title="Baixar PDF">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($pager) && $pager->getPageCount() > 1): ?>
                <div class="sp-data-card__body border-top">
                    <?= $pager->links() ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
