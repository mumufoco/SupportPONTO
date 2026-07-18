<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Escala<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-admin-listing-shell">
    <?= view('components/page_header', [
        'title' => 'Escala',
        'subtitle' => 'Organize jornadas disponíveis, acompanhe status e mantenha a estrutura operacional de horários sob controle.',
        'icon' => 'bi bi-calendar2-week-fill',
        'actions' => [
            ['label' => 'Nova Escala', 'icon' => 'bi bi-plus-circle-fill', 'url' => sp_schedules_create_url()],
                    ],
    ]) ?>

    <div class="sp-schedule-toolbar">
        <form method="GET" action="<?= sp_schedules_index_url() ?>" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label" for="search">Busca</label>
                <input type="text" id="search" name="search" class="form-control" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Nome da escala ou jornada">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Todos</option>
                    <option value="active" <?= (($filters['status'] ?? '') === 'active') ? 'selected' : '' ?>>Ativo</option>
                    <option value="inactive" <?= (($filters['status'] ?? '') === 'inactive') ? 'selected' : '' ?>>Inativo</option>
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
                <a href="<?= sp_schedules_index_url() ?>" class="btn btn-outline-secondary flex-fill"><i class="bi bi-arrow-counterclockwise me-1"></i>Limpar</a>
            </div>
        </form>
    </div>

    <div class="sp-standard-table-wrapper">
        <?php if (empty($schedules ?? [])): ?>
            <?= view('components/empty_state', ['icon' => 'bi bi-calendar2-x-fill', 'title' => 'Nenhuma escala encontrada', 'description' => 'Crie ou ajuste filtros para visualizar jornadas cadastradas.']) ?>
        <?php else: ?>
            <?php
                $statusLabels = [
                    'scheduled' => ['label' => 'Agendado', 'badge' => 'sp-badge-info', 'icon' => 'bi-calendar-check-fill'],
                    'completed' => ['label' => 'Concluído', 'badge' => 'sp-badge-success', 'icon' => 'bi-check-circle-fill'],
                    'cancelled' => ['label' => 'Cancelado', 'badge' => 'sp-badge-danger', 'icon' => 'bi-x-circle-fill'],
                    'absent'    => ['label' => 'Ausente', 'badge' => 'sp-badge-warning', 'icon' => 'bi-exclamation-triangle-fill'],
                ];
            ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Turno</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th class="text-center">Recorrente</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($schedules ?? []) as $schedule): ?>
                            <?php
                                $scheduleId = (int) ($schedule->id ?? 0);
                                $statusInfo = $statusLabels[$schedule->status ?? ''] ?? ['label' => esc($schedule->status ?? '-'), 'badge' => 'sp-badge-neutral', 'icon' => 'bi-question-circle-fill'];
                                $shiftStart = substr((string) ($schedule->start_time ?? ''), 0, 5);
                                $shiftEnd = substr((string) ($schedule->end_time ?? ''), 0, 5);
                            ?>
                            <tr>
                                <td><strong><?= esc($schedule->employee_name ?? '-') ?></strong></td>
                                <td>
                                    <?= esc($schedule->shift_name ?? '-') ?>
                                    <?php if ($shiftStart && $shiftEnd): ?>
                                        <br><small class="text-muted"><?= esc($shiftStart) ?> - <?= esc($shiftEnd) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= esc(!empty($schedule->date) ? date('d/m/Y', strtotime($schedule->date)) : '-') ?></td>
                                <td>
                                    <span class="sp-badge <?= esc($statusInfo['badge']) ?>">
                                        <i class="bi <?= esc($statusInfo['icon']) ?>"></i>
                                        <?= esc($statusInfo['label']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($schedule->is_recurring)): ?>
                                        <i class="bi bi-arrow-repeat text-primary" title="Escala recorrente"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-icon-actions">
                                        <a href="<?= sp_schedules_edit_url($scheduleId) ?>" class="icon-action icon-action-edit" title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <form method="POST" action="<?= sp_schedules_delete_url($scheduleId) ?>" class="d-inline"
                                              onsubmit="return confirm('Excluir esta escala?')">
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
<?= $this->endSection() ?>
