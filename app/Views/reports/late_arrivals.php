<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Atrasos<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Relatório de atrasos',
        'subtitle' => 'Identifique colaboradores com atrasos no período e as datas impactadas.',
        'icon' => 'bi bi-alarm',
        'actions' => [
            ['label' => 'Voltar para relatórios', 'icon' => 'bi bi-arrow-left', 'url' => route_to('reports')],
        ],
    ]) ?>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form method="get" action="<?= route_to('reports.late_arrivals') ?>" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="month" class="form-label">Mês</label>
                    <input type="month" id="month" name="month" class="form-control" value="<?= esc($month ?? date('Y-m')) ?>">
                </div>
                <div class="col-md-4">
                    <label for="department" class="form-label">Departamento</label>
                    <select id="department" name="department" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (($departments ?? []) as $departmentOption): ?>
                            <option value="<?= esc((string) $departmentOption) ?>" <?= ($selectedDepartment ?? '') === $departmentOption ? 'selected' : '' ?>><?= esc((string) $departmentOption) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill">Aplicar</button>
                    <a href="<?= route_to('reports.late_arrivals') ?>" class="btn btn-outline-secondary">Limpar</a>
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
                            <th>Colaborador</th>
                            <th>Departamento</th>
                            <th>Total de atrasos</th>
                            <th>Datas registradas</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($lateArrivalsData ?? [])): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">Nenhum atraso encontrado para os filtros informados.</td></tr>
                    <?php endif; ?>
                    <?php foreach (($lateArrivalsData ?? []) as $item): ?>
                        <tr>
                            <td><?= esc((string) ($item['employee']->name ?? '')) ?></td>
                            <td><?= esc((string) ($item['employee']->department ?? '')) ?></td>
                            <td><span class="badge bg-warning text-dark"><?= esc((string) ($item['total_count'] ?? 0)) ?></span></td>
                            <td>
                                <?php
                                    $dates = [];
                                    foreach (($item['late_arrivals'] ?? []) as $late) {
                                        $dateValue = is_object($late) ? ($late->date ?? $late->punch_date ?? null) : ($late['date'] ?? $late['punch_date'] ?? null);
                                        if ($dateValue) {
                                            $dates[] = format_date_br((string) $dateValue);
                                        }
                                    }
                                ?>
                                <?= esc($dates !== [] ? implode(', ', $dates) : 'Sem detalhes') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
