<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($shift->name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$typeLabels = [
    'morning' => 'Manhã',
    'afternoon' => 'Tarde',
    'night' => 'Noite',
    'custom' => 'Personalizado',
];
$typeBadges = [
    'morning' => 'sp-badge-warning',
    'afternoon' => 'sp-badge-info',
    'night' => 'sp-badge-neutral',
    'custom' => 'sp-badge-success',
];
$isActive = !empty($shift->active);
$shiftStart = substr((string) $shift->start_time, 0, 5);
$shiftEnd = substr((string) $shift->end_time, 0, 5);
?>
<div class="container-fluid sp-responsive-screen">
    <?= view('components/page_header', [
        'title'    => $shift->name,
        'subtitle' => 'Detalhes do turno, funcionários escalados e estatísticas.',
        'icon'     => 'bi bi-calendar2-week-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_shifts_index_url()],
            ['label' => 'Editar', 'icon' => 'bi bi-pencil-fill', 'url' => sp_shifts_edit_url($shift->id)],
        ],
    ]) ?>

    <div class="d-flex align-items-center gap-2 mb-3">
        <div style="width:1.5rem;height:1.5rem;border-radius:50%;border:2px solid var(--sp-border);background:<?= sp_style_color($shift->color ?? '#6C757D') ?>;"></div>
        <span class="sp-badge <?= $isActive ? 'sp-badge-success' : 'sp-badge-danger' ?>">
            <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
            <?= $isActive ? 'Ativo' : 'Inativo' ?>
        </span>
        <span class="sp-badge <?= esc($typeBadges[$shift->type] ?? 'sp-badge-neutral') ?>">
            <?= esc($typeLabels[$shift->type] ?? $shift->type) ?>
        </span>
    </div>

    <!-- KPIs -->
    <div class="sp-grid-4 mb-3">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Duração Total</div>
                <div class="stat-card-value"><?= number_format($duration, 1) ?>h</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-people-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Funcionários Escalados</div>
                <div class="stat-card-value"><?= count($assignedEmployees) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-calendar-check-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Escalas Criadas</div>
                <div class="stat-card-value"><?= (int) ($statistics['total_schedules'] ?? 0) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon warning"><i class="bi bi-calendar-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Escalas Futuras</div>
                <div class="stat-card-value"><?= (int) ($statistics['upcoming_schedules'] ?? 0) ?></div>
            </div>
        </div>
    </div>

    <div class="sp-grid-main">
        <!-- Coluna esquerda -->
        <div>
            <div class="sp-card">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-info-circle-fill"></i> Detalhes do Turno</span>
                </div>
                <div class="sp-card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr>
                                <th style="width:30%">Tipo</th>
                                <td>
                                    <span class="sp-badge <?= esc($typeBadges[$shift->type] ?? 'sp-badge-neutral') ?>">
                                        <?= esc($typeLabels[$shift->type] ?? $shift->type) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Horário</th>
                                <td><i class="bi bi-clock text-muted me-2"></i><strong><?= esc($shiftStart) ?></strong> até <strong><?= esc($shiftEnd) ?></strong></td>
                            </tr>
                            <tr>
                                <th>Duração</th>
                                <td><strong><?= number_format($duration, 1) ?>h</strong></td>
                            </tr>
                            <tr>
                                <th>Intervalo</th>
                                <td><?= $shift->break_duration > 0 ? (int) $shift->break_duration . ' minutos' : 'Sem intervalo' ?></td>
                            </tr>
                            <tr>
                                <th>Cor</th>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width:1.25rem;height:1.25rem;border-radius:50%;background:<?= sp_style_color($shift->color ?? '#6C757D') ?>;"></div>
                                        <code><?= esc($shift->color ?? '#6C757D') ?></code>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="sp-badge <?= $isActive ? 'sp-badge-success' : 'sp-badge-danger' ?>">
                                        <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                        <?= $isActive ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Criado em</th>
                                <td><?= esc(date('d/m/Y H:i', strtotime((string) $shift->created_at))) ?></td>
                            </tr>
                            <?php if (!empty($shift->updated_at)): ?>
                            <tr>
                                <th>Última atualização</th>
                                <td><?= esc(date('d/m/Y H:i', strtotime((string) $shift->updated_at))) ?></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if (!empty($shift->description)): ?>
                        <div class="mt-3 pt-3 border-top">
                            <h6 class="text-muted mb-2">Descrição:</h6>
                            <p class="mb-0"><?= nl2br(esc($shift->description)) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sp-card" id="assigned-employees">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-people-fill"></i> Funcionários Escalados (<?= count($assignedEmployees) ?>)</span>
                </div>
                <div class="sp-card-body p-0">
                    <?php if (empty($assignedEmployees)): ?>
                        <div class="sp-empty m-3">
                            <div class="sp-empty-icon"><i class="bi bi-people"></i></div>
                            <p class="sp-empty-title">Nenhum funcionário escalado neste turno</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Departamento</th>
                                        <th>Próxima Escala</th>
                                        <th class="text-center">Total de Escalas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedEmployees as $emp): ?>
                                        <tr>
                                            <td>
                                                <strong><?= esc($emp->name ?? '-') ?></strong><br>
                                                <small class="text-muted"><?= esc($emp->position ?? 'N/A') ?></small>
                                            </td>
                                            <td><?= esc($emp->department ?? 'N/A') ?></td>
                                            <td>
                                                <?php if (!empty($emp->next_schedule)): ?>
                                                    <?= esc(date('d/m/Y', strtotime($emp->next_schedule))) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Nenhuma</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="sp-badge sp-badge-primary"><?= (int) ($emp->total_schedules ?? 0) ?></span>
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

        <!-- Coluna direita -->
        <div>
            <div class="sp-card">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-lightning-fill"></i> Ações Rápidas</span>
                </div>
                <div class="sp-card-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="<?= sp_schedules_create_url() ?>?shift_id=<?= rawurlencode((string) $shift->id) ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar-plus-fill text-primary me-2"></i>
                            <strong>Criar Escala</strong>
                            <small class="d-block text-muted">Agendar este turno para um colaborador</small>
                        </a>
                        <a href="<?= sp_schedules_index_url() ?>?shift_id=<?= rawurlencode((string) $shift->id) ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-calendar3 text-info me-2"></i>
                            <strong>Ver Todas as Escalas</strong>
                            <small class="d-block text-muted">Consultar escalas que usam este turno</small>
                        </a>
                        <form method="POST" action="<?= sp_shifts_clone_url($shift->id) ?>" class="d-inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="list-group-item list-group-item-action text-start w-100">
                                <i class="bi bi-copy text-secondary me-2"></i>
                                <strong>Clonar Turno</strong>
                                <small class="d-block text-muted">Criar uma cópia deste turno (inativa)</small>
                            </button>
                        </form>
                        <button type="button" class="list-group-item list-group-item-action text-start w-100"
                                onclick="confirmDeleteShift(<?= (int) $shift->id ?>, '<?= esc(addslashes($shift->name ?? ''), 'js') ?>')">
                            <i class="bi bi-trash-fill text-danger me-2"></i>
                            <strong class="text-danger">Excluir Turno</strong>
                            <small class="d-block text-muted">Remover permanentemente este turno</small>
                        </button>
                    </div>
                </div>
            </div>

            <div class="sp-card">
                <div class="sp-card-header">
                    <span class="sp-card-title"><i class="bi bi-bar-chart-fill"></i> Estatísticas</span>
                </div>
                <div class="sp-card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Escalas Totais</span>
                            <strong><?= (int) ($statistics['total_schedules'] ?? 0) ?></strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Escalas Futuras</span>
                            <strong><?= (int) ($statistics['upcoming_schedules'] ?? 0) ?></strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Escalas Concluídas</span>
                            <strong><?= (int) ($statistics['completed_schedules'] ?? 0) ?></strong>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <span class="text-muted">Escalas Canceladas</span>
                            <strong><?= (int) ($statistics['cancelled_schedules'] ?? 0) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmação de exclusão -->
<div class="modal fade" id="deleteShiftModal" tabindex="-1" aria-labelledby="deleteShiftModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger" id="deleteShiftModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar exclusão
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o turno <strong id="deleteShiftName"></strong>?</p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta ação não pode ser desfeita.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteShiftForm" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash-fill me-2"></i>Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
function confirmDeleteShift(id, name) {
    document.getElementById('deleteShiftName').textContent = name;
    document.getElementById('deleteShiftForm').action = '<?= sp_shifts_delete_url(999999999) ?>'.replace('999999999', id);
    new bootstrap.Modal(document.getElementById('deleteShiftModal')).show();
}
</script>
<?= $this->endSection() ?>
