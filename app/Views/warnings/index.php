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


    <!-- Filtros -->
    <div class="sp-card">
        <div class="sp-card-body" style="padding:1rem;">
            <form method="get" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
                <div class="sp-form-group" style="margin:0;min-width:180px;flex:1;">
                    <label class="sp-label" for="warning_type">Tipo</label>
                    <select name="warning_type" id="warning_type" class="sp-select" onchange="this.form.submit()">
                        <option value="all"       <?= ($warningType ?? '') === 'all'       ? 'selected' : '' ?>>Todos</option>
                        <option value="verbal"    <?= ($warningType ?? '') === 'verbal'    ? 'selected' : '' ?>>Verbal (<?= esc($counts['verbal'] ?? 0) ?>)</option>
                        <option value="escrita"   <?= ($warningType ?? '') === 'escrita'   ? 'selected' : '' ?>>Escrita (<?= esc($counts['escrita'] ?? 0) ?>)</option>
                        <option value="suspensao" <?= ($warningType ?? '') === 'suspensao' ? 'selected' : '' ?>>Suspensão (<?= esc($counts['suspensao'] ?? 0) ?>)</option>
                    </select>
                </div>
                <div class="sp-form-group" style="margin:0;min-width:180px;flex:1;">
                    <label class="sp-label" for="status">Status</label>
                    <select name="status" id="status" class="sp-select" onchange="this.form.submit()">
                        <option value="all"               <?= ($status ?? '') === 'all'               ? 'selected' : '' ?>>Todos</option>
                        <option value="pendente-assinatura" <?= ($status ?? '') === 'pendente-assinatura' ? 'selected' : '' ?>>Pendente (<?= esc($counts['pendente'] ?? 0) ?>)</option>
                        <option value="assinado"          <?= ($status ?? '') === 'assinado'          ? 'selected' : '' ?>>Assinado (<?= esc($counts['assinado'] ?? 0) ?>)</option>
                        <option value="recusado"          <?= ($status ?? '') === 'recusado'          ? 'selected' : '' ?>>Recusado (<?= esc($counts['recusado'] ?? 0) ?>)</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="sp-card">
        <?php if (empty($warnings ?? [])): ?>
            <div class="sp-card-body">
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-inbox"></i></div>
                    <p class="sp-empty-title">Nenhuma advertência encontrada</p>
                    <p class="sp-empty-text">Altere os filtros ou registre uma nova ocorrência.</p>
                    <?php if (in_array($employee['role'] ?? '', ['admin', 'gestor', 'rh'])): ?>
                        <a href="<?= sp_warning_create_url() ?>" class="sp-btn sp-btn-primary sp-btn-sm">
                            <i class="bi bi-plus-circle"></i> Nova advertência
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="sp-table-container">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Funcionário</th>
                            <th>Tipo</th>
                            <th>Motivo</th>
                            <th>Emitida por</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warnings as $warning): ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($warning->occurrence_date)) ?></td>
                                <td>
                                    <strong><?= esc($warning->employee_name) ?></strong>
                                    <a href="<?= sp_warning_dashboard_url((int) $warning->employee_id) ?>"
                                       class="sp-btn sp-btn-sm sp-btn-icon" title="Dashboard do funcionário"
                                       style="margin-left:.25rem;">
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
                                    <?= esc(mb_strimwidth($warning->reason, 0, 70, '…')) ?>
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
                                    <div class="sp-table-actions" style="justify-content:flex-end;">
                                        <a href="<?= sp_warning_show_url((int) $warning->id) ?>"
                                           class="sp-btn sp-btn-sm sp-btn-secondary" title="Visualizar">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                        <?php if ($warning->pdf_path): ?>
                                            <a href="<?= sp_warning_download_url((int) $warning->id) ?>"
                                               class="sp-btn sp-btn-sm sp-btn-secondary" title="Baixar PDF">
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

            <?php if (!empty($pager)): ?>
                <div class="sp-card-footer">
                    <?= $pager->links() ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
