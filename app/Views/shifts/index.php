<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Turnos de Trabalho<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Turnos de Trabalho',
        'subtitle' => 'Gerencie os turnos ativos e seus horários.',
        'icon'     => 'bi bi-calendar2-week-fill',
        'actions'  => [
                        ['label' => 'Novo Turno',   'icon' => 'bi bi-plus-circle-fill', 'url' => sp_shifts_create_url()],
        ],
    ]) ?>


    <!-- Estatísticas -->
    <div class="sp-grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-calendar2-week-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Total de Turnos</div>
                <div class="stat-card-value"><?= (int) ($statistics['total_shifts'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Turnos Ativos</div>
                <div class="stat-card-value"><?= (int) ($statistics['active_shifts'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-people-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Funcionários Escalados</div>
                <div class="stat-card-value"><?= (int) ($statistics['employees_scheduled'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Escalas (30 dias)</div>
                <div class="stat-card-value"><?= (int) ($statistics['upcoming_schedules'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="sp-filter-shell mb-3">
        <form method="GET" action="<?= sp_shifts_index_url() ?>" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Tipo</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['morning' => 'Manhã', 'afternoon' => 'Tarde', 'night' => 'Noite', 'custom' => 'Personalizado'] as $val => $lab): ?>
                        <option value="<?= $val ?>" <?= ($filters['type'] ?? '') === $val ? 'selected' : '' ?>><?= $lab ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativos</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small">Buscar</label>
                <input type="text" name="search" class="form-control form-control-sm"
                    placeholder="Nome ou descrição..." value="<?= esc($filters['search'] ?? '') ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="<?= sp_shifts_index_url() ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <!-- Tabela de turnos -->
    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-list-ul"></i> Turnos cadastrados</span>
        </div>
        <div class="sp-card-body p-0">
            <?php if (empty($shifts)): ?>
                <div class="sp-empty-state m-3">
                    <i class="bi bi-calendar2-x" style="font-size:2.5rem;display:block;margin-bottom:.5rem"></i>
                    Nenhum turno encontrado. <a href="<?= sp_shifts_create_url() ?>">Criar o primeiro turno</a>.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Turno</th>
                                <th>Horário</th>
                                <th>Intervalo</th>
                                <th>Funcionários</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $typeLabels = ['morning'=>'Manhã','afternoon'=>'Tarde','night'=>'Noite','custom'=>'Personalizado'];
                            foreach ($shifts as $shift):
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="rounded-circle" style="width:12px;height:12px;flex-shrink:0;background:<?= sp_style_color((string) ($shift->color ?? '#4fa14f')) ?>"></span>
                                            <div>
                                                <strong><?= esc($shift->name) ?></strong>
                                                <div class="text-muted small"><?= $typeLabels[$shift->type ?? ''] ?? esc((string) $shift->type) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-muted small">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= esc(substr((string) ($shift->start_time ?? ''), 0, 5)) ?> – <?= esc(substr((string) ($shift->end_time ?? ''), 0, 5)) ?>
                                    </td>
                                    <td class="text-muted small">
                                        <?php
                                        $mins = (int) ($shift->break_duration ?? 0);
                                        if ($mins === 0) {
                                            echo '–';
                                        } elseif ($mins >= 60) {
                                            $h = (int) floor($mins / 60);
                                            $m = $mins % 60;
                                            echo $h . 'h' . ($m > 0 ? ' ' . $m . 'min' : '');
                                        } else {
                                            echo $mins . 'min';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php $ec = (int) ($shift->employee_count ?? 0); ?>
                                        <span class="sp-badge <?= $ec > 0 ? 'sp-badge-primary' : 'sp-badge-neutral' ?>"><?= $ec ?></span>
                                    </td>
                                    <td>
                                        <span class="sp-status <?= !empty($shift->active) ? 'sp-status-active' : 'sp-status-inactive' ?>">
                                            <?= !empty($shift->active) ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <a href="<?= sp_shifts_show_url($shift->id) ?>" class="icon-action" title="Ver">
                                                <i class="bi bi-eye-fill"></i>
                                            </a>
                                            <a href="<?= sp_shifts_edit_url($shift->id) ?>" class="icon-action icon-action-edit" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <form method="POST" action="<?= sp_shifts_clone_url($shift->id) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="icon-action" title="Clonar">
                                                    <i class="bi bi-copy"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="<?= sp_shifts_delete_url($shift->id) ?>" class="d-inline"
                                                onsubmit="return confirm('Excluir este turno?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="_method" value="DELETE">
                                                <button type="submit" class="icon-action icon-action-danger" title="Excluir">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
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

</div>
<?= $this->endSection() ?>
