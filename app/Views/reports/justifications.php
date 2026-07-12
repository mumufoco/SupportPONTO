<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Justificativas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Relatório de justificativas',
        'subtitle' => 'Acompanhe solicitações do período com filtros por departamento e status.',
        'icon' => 'bi bi-file-earmark-text',
        'actions' => [
            ['label' => 'Voltar para relatórios', 'icon' => 'bi bi-arrow-left', 'url' => route_to('reports')],
        ],
    ]) ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Total</div><strong><?= esc((string) (($summary['total'] ?? 0))) ?></strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Pendentes</div><strong><?= esc((string) (($summary['pending'] ?? 0))) ?></strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Aprovadas</div><strong><?= esc((string) (($summary['approved'] ?? 0))) ?></strong></div></div>
        <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="small text-muted">Rejeitadas</div><strong><?= esc((string) (($summary['rejected'] ?? 0))) ?></strong></div></div>
    </div>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" action="<?= route_to('reports.justifications') ?>" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="month" class="form-label">Mês</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= esc($month ?? date('Y-m')) ?>">
                </div>
                <div class="col-md-3">
                    <label for="department" class="form-label">Departamento</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($departments ?? []) as $departmentOption): ?>
                            <option value="<?= esc((string) $departmentOption) ?>" <?= ($selectedDepartment ?? '') === $departmentOption ? 'selected' : '' ?>><?= esc((string) $departmentOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select">
                        <?php foreach (['all' => 'Todos', 'pending' => 'Pendentes', 'approved' => 'Aprovadas', 'rejected' => 'Rejeitadas'] as $statusValue => $statusLabel): ?>
                            <option value="<?= esc($statusValue) ?>" <?= ($selectedStatus ?? 'all') === $statusValue ? 'selected' : '' ?>><?= esc($statusLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Aplicar</button>
                    <a href="<?= route_to('reports.justifications') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Colaborador</th>
                            <th>Departamento</th>
                            <th>Motivo</th>
                            <th>Status</th>
                            <th>Solicitada em</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($justifications ?? [])): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">Nenhuma justificativa encontrada para os filtros informados.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($justifications ?? []) as $item): ?>
                        <?php
$summary = $summary ?? [];
                            $status = (string) ($item->status ?? 'pending');
                            $badgeClass = match ($status) {
                                'approved' => 'bg-success',
                                'rejected' => 'bg-danger',
                                default => 'bg-warning text-dark',
                            };
                        ?>
                        <tr>
                            <td><?= esc(format_date_br((string) ($item->justification_date ?? $item->date ?? '' ?? ''))) ?></td>
                            <td><?= esc((string) ($item->employee_name ?? '')) ?></td>
                            <td><?= esc((string) ($item->employee_department ?? '')) ?></td>
                            <td><?= esc((string) (($item->reason ?? $item->description ?? 'Sem descrição'))) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= esc(ucfirst($status)) ?></span></td>
                            <td><?= esc(format_datetime_br((string) ($item->created_at ?? ''), false)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
