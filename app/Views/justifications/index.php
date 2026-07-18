<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Justificativas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $navigationContext = is_array($navigationContext ?? null) ? $navigationContext : ['enabled' => false]; ?>
<?php
$headerActions = [
    ['label' => 'Nova justificativa', 'icon' => 'bi bi-plus-circle-fill', 'url' => site_url('justifications/create')],
];

if (($navigationContext['enabled'] ?? false) === true) {
    array_unshift($headerActions, [
        'label' => $navigationContext['backLabel'] ?? lang('OperationalNavigation.actions.backToDashboard'),
        'icon'  => 'bi bi-arrow-left-circle',
        'url'   => $navigationContext['backUrl'] ?? site_url('dashboard/admin'),
    ]);
}
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Justificativas',
        'subtitle' => 'Acompanhe solicitações, filtre pendências e acesse rapidamente novas submissões ou análises.',
        'icon'     => 'bi bi-file-earmark-text-fill',
        'actions'  => $headerActions,
    ]) ?>

    <?= view('components/admin/return_navigation_notice', ['context' => $navigationContext]) ?>


    <!-- Filtros -->
    <div class="sp-card">
        <div class="sp-card-body" style="padding:1rem;">
            <form method="GET" action="<?= site_url('justifications') ?>"
                  style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
                <div class="sp-form-group" style="margin:0;min-width:160px;flex:1;">
                    <label class="sp-label" for="status">Status</label>
                    <select name="status" id="status" class="sp-select">
                        <option value="">Todos</option>
                        <option value="pending"  <?= (($filters['status'] ?? '') === 'pending')   ? 'selected' : '' ?>>Pendente</option>
                        <option value="approved" <?= (($filters['status'] ?? '') === 'approved')  ? 'selected' : '' ?>>Aprovada</option>
                        <option value="rejected" <?= (($filters['status'] ?? '') === 'rejected')  ? 'selected' : '' ?>>Rejeitada</option>
                    </select>
                </div>
                <div class="sp-form-group" style="margin:0;min-width:160px;flex:1;">
                    <label class="sp-label" for="type">Tipo</label>
                    <select name="type" id="type" class="sp-select">
                        <option value="">Todos</option>
                        <option value="atraso" <?= (($filters['type'] ?? '') === 'atraso') ? 'selected' : '' ?>>Atraso</option>
                        <option value="falta"  <?= (($filters['type'] ?? '') === 'falta')  ? 'selected' : '' ?>>Falta</option>
                        <option value="ajuste" <?= (($filters['type'] ?? '') === 'ajuste') ? 'selected' : '' ?>>Ajuste</option>
                    </select>
                </div>
                <div class="sp-form-group" style="margin:0;min-width:200px;flex:2;">
                    <label class="sp-label" for="search">Busca</label>
                    <input type="text" name="search" id="search" class="sp-input"
                           value="<?= esc($filters['search'] ?? '') ?>"
                           placeholder="Buscar por colaborador ou motivo">
                </div>
                <div style="display:flex;gap:.5rem;align-items:flex-end;padding-bottom:1px;">
                    <button type="submit" class="sp-btn sp-btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="<?= site_url('justifications') ?>" class="sp-btn sp-btn-outline">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumo -->
    <div class="sp-grid-4">
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-primary-dark);line-height:1;">
                <?= esc($summary['total'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Total no período
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-warning);line-height:1;">
                <?= esc($summary['pending'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Pendentes
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-success);line-height:1;">
                <?= esc($summary['approved'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Aprovadas
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-danger);line-height:1;">
                <?= esc($summary['rejected'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Rejeitadas
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="sp-card">
        <?php if (empty($justifications ?? [])): ?>
            <div class="sp-card-body">
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-file-earmark-x-fill"></i></div>
                    <p class="sp-empty-title">Nenhuma justificativa encontrada</p>
                    <p class="sp-empty-text">Ajuste os filtros ou crie uma nova justificativa para iniciar o fluxo.</p>
                    <a href="<?= site_url('justifications/create') ?>" class="sp-btn sp-btn-primary sp-btn-sm">
                        <i class="bi bi-plus-circle"></i> Nova justificativa
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="sp-table-container">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Tipo</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th>Última atualização</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($justifications ?? []) as $item): ?>
                            <?php
                            $status = strtolower((string) ($item['status'] ?? 'pending'));
                            $statusMap = [
                                'pending'  => ['badge' => 'sp-badge-warning', 'label' => 'Pendente'],
                                'approved' => ['badge' => 'sp-badge-success', 'label' => 'Aprovada'],
                                'rejected' => ['badge' => 'sp-badge-danger',  'label' => 'Rejeitada'],
                            ];
                            $statusData = $statusMap[$status] ?? ['badge' => 'sp-badge-neutral', 'label' => ucfirst($status)];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= esc($item['employee_name'] ?? '-') ?></strong><br>
                                    <small style="color:var(--sp-text-muted);">
                                        <?= esc(mb_strimwidth((string) ($item['reason'] ?? '-'), 0, 60, '…')) ?>
                                    </small>
                                </td>
                                <td><?= esc(ucfirst($item['justification_type'] ?? $item['type'] ?? '-')) ?></td>
                                <td><?= esc(isset($item['justification_date']) ? date('d/m/Y', strtotime($item['justification_date'])) : (isset($item['date']) ? date('d/m/Y', strtotime($item['date'])) : '-')) ?></td>
                                <td>
                                    <span class="sp-badge <?= esc($statusData['badge']) ?>">
                                        <?= esc($statusData['label']) ?>
                                    </span>
                                </td>
                                <td><?= esc($item['updated_at'] ?? $item['created_at'] ?? '-') ?></td>
                                <td>
                                    <div class="table-icon-actions">
                                        <a href="<?= site_url('justifications/' . ($item['id'] ?? '')) ?>"
                                           class="icon-action" title="Visualizar">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
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
