<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= esc($shift->name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
use App\Libraries\UI\ComponentBuilder;
use App\Libraries\UI\UIHelper;

$typeLabels = [
    'morning' => 'Manhã',
    'afternoon' => 'Tarde',
    'night' => 'Noite',
    'custom' => 'Personalizado'
];
?>

<!-- Page Header -->
<div class="sp-page-section">
    <?= ComponentBuilder::card([
        'content' => UIHelper::flex([
            '<div>
                <div class="sp-page-title-row">
                    <div class="sp-color-chip sp-color-chip-lg" style="background: ' . sp_style_color($shift->color ?? '#6C757D') . ';"></div>
                    <h2 class="sp-page-title sp-m-0">
                        ' . esc($shift->name) . '
                    </h2>
                    ' . UIHelper::statusBadge($shift->active ? 'active' : 'inactive') . '
                </div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="' . sp_dashboard_url() . '">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="' . sp_shifts_index_url() . '">Turnos</a></li>
                        <li class="breadcrumb-item active">' . esc($shift->name) . '</li>
                    </ol>
                </nav>
            </div>',
            '<div class="sp-page-actions">
                ' . ComponentBuilder::button([
                    'text' => 'Editar',
                    'icon' => 'fa-edit',
                    'url' => sp_shifts_edit_url($shift->id),
                    'style' => 'primary',
                ]) . '
                <form method="POST" action="' . sp_shifts_clone_url($shift->id) . '" class="sp-inline-form">
                    ' . csrf_field() . '
                    ' . ComponentBuilder::button([
                        'text' => 'Clonar',
                        'icon' => 'fa-copy',
                        'style' => 'outline-secondary',
                        'type' => 'submit'
                    ]) . '
                </form>
                ' . ComponentBuilder::button([
                    'text' => 'Voltar',
                    'icon' => 'fa-arrow-left',
                    'url' => sp_shifts_index_url(),
                    'style' => 'outline-secondary',
                ]) . '
            </div>'
        ], 'between', 'center')
    ]) ?>
</div>

<!-- Statistics Cards -->
<div class="sp-grid-stats">

    <?= ComponentBuilder::statCard([
        'value' => number_format($duration, 1) . 'h',
        'label' => 'Duração Total',
        'icon' => 'fa-clock',
        'color' => 'primary'
    ]) ?>

    <?= ComponentBuilder::statCard([
        'value' => count($assignedEmployees),
        'label' => 'Funcionários Escalados',
        'icon' => 'fa-users',
        'color' => 'success',
        'url' => '#assigned-employees'
    ]) ?>

    <?= ComponentBuilder::statCard([
        'value' => $statistics['total_schedules'] ?? 0,
        'label' => 'Escalas Criadas',
        'icon' => 'fa-calendar-check',
        'color' => 'info'
    ]) ?>

    <?= ComponentBuilder::statCard([
        'value' => $statistics['upcoming_schedules'] ?? 0,
        'label' => 'Escalas Futuras',
        'icon' => 'fa-calendar',
        'color' => 'warning'
    ]) ?>

</div>

<div class="sp-grid-main">

    <!-- Left Column -->
    <div>
        <!-- Shift Details -->
        <?= ComponentBuilder::card([
            'title' => 'Detalhes do Turno',
            'icon' => 'fa-info-circle',
            'content' => '
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th class="sp-table-col-30">Tipo</th>
                            <td>' . ComponentBuilder::badge([
                                'text' => $typeLabels[$shift->type] ?? $shift->type,
                                'style' => match($shift->type) {
                                    'morning' => 'warning',
                                    'afternoon' => 'primary',
                                    'night' => 'dark',
                                    'custom' => 'success',
                                    default => 'secondary'
                                }
                            ]) . '</td>
                        </tr>
                        <tr>
                            <th>Horário</th>
                            <td>
                                <i class="fas fa-clock text-muted me-2"></i>
                                <strong>' . esc(substr((string) $shift->start_time, 0, 5)) . '</strong> até <strong>' . esc(substr((string) $shift->end_time, 0, 5)) . '</strong>
                            </td>
                        </tr>
                        <tr>
                            <th>Duração</th>
                            <td><strong>' . number_format($duration, 1) . 'h</strong></td>
                        </tr>
                        <tr>
                            <th>Intervalo</th>
                            <td>' . ($shift->break_duration > 0 ? (int) $shift->break_duration . ' minutos' : 'Sem intervalo') . '</td>
                        </tr>
                        <tr>
                            <th>Cor</th>
                            <td>
                                <div class="sp-shift-row">
                                    <div class="sp-color-chip" style="background: ' . sp_style_color($shift->color ?? '#6C757D') . ';"></div>
                                    <span class="sp-code-pill">' . esc($shift->color ?? '#6C757D') . '</span>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>' . UIHelper::statusBadge($shift->active ? 'active' : 'inactive') . '</td>
                        </tr>
                        <tr>
                            <th>Criado em</th>
                            <td>' . UIHelper::formatDateTime($shift->created_at) . '</td>
                        </tr>
                        ' . ($shift->updated_at ? '
                        <tr>
                            <th>Última atualização</th>
                            <td>' . UIHelper::formatDateTime($shift->updated_at) . '</td>
                        </tr>
                        ' : '') . '
                    </tbody>
                </table>

                ' . ($shift->description ? '
                <div class="mt-3 pt-3 border-top">
                    <h6 class="text-muted mb-2">Descrição:</h6>
                    <p class="mb-0">' . nl2br(esc($shift->description)) . '</p>
                </div>
                ' : '') . '
            '
        ]) ?>

        <!-- Assigned Employees -->
        <div id="assigned-employees" class="mt-4">
            <?= ComponentBuilder::card([
                'title' => 'Funcionários Escalados (' . count($assignedEmployees) . ')',
                'icon' => 'fa-users',
                'content' => count($assignedEmployees) > 0 ?
                    ComponentBuilder::table([
                        'columns' => [
                            [
                                'label' => 'Funcionário',
                                'key' => 'name',
                                'formatter' => function($name, $row) {
                                    return UIHelper::flex([
                                        UIHelper::avatar($name),
                                        '<div>
                                            <strong>' . esc($name) . '</strong><br>
                                            <small class="text-muted">' . esc($row->position ?? 'N/A') . '</small>
                                        </div>'
                                    ], 'start', 'center', 'sm');
                                }
                            ],
                            [
                                'label' => 'Departamento',
                                'key' => 'department',
                                'formatter' => fn($dept) => esc($dept ?? 'N/A')
                            ],
                            [
                                'label' => 'Próxima Escala',
                                'key' => 'next_schedule',
                                'formatter' => function($date) {
                                    if (!$date) return '<span class="text-muted">Nenhuma</span>';
                                    return UIHelper::formatDate($date);
                                }
                            ],
                            [
                                'label' => 'Total de Escalas',
                                'key' => 'total_schedules',
                                'formatter' => fn($count) => '<span class="badge bg-primary">' . (int) ($count ?? 0) . '</span>'
                            ]
                        ],
                        'data' => $assignedEmployees
                    ]) :
                    UIHelper::emptyState('Nenhum funcionário escalado neste turno', 'users')
            ]) ?>
        </div>
    </div>

    <!-- Right Column -->
    <div>
        <!-- Quick Actions -->
        <?= ComponentBuilder::card([
            'title' => 'Ações Rápidas',
            'icon' => 'fa-bolt',
            'class' => 'mb-4',
            'content' => '
                <div class="sp-actions-stack">
                    ' . ComponentBuilder::button([
                        'text' => 'Criar Escala',
                        'icon' => 'fa-calendar-plus',
                        'url' => sp_schedules_create_url() . '?shift_id=' . rawurlencode((string) $shift->id),
                        'style' => 'primary',
                        'class' => 'w-100'
                    ]) . '
                    ' . ComponentBuilder::button([
                        'text' => 'Ver Todas as Escalas',
                        'icon' => 'fa-calendar',
                        'url' => sp_schedules_index_url() . '?shift_id=' . rawurlencode((string) $shift->id),
                        'style' => 'outline-primary',
                        'class' => 'w-100'
                    ]) . '
                    ' . ComponentBuilder::button([
                        'text' => 'Editar Turno',
                        'icon' => 'fa-edit',
                        'url' => sp_shifts_edit_url($shift->id),
                        'style' => 'outline-secondary',
                        'class' => 'w-100'
                    ]) . '
                    <form method="POST" action="' . sp_shifts_clone_url($shift->id) . '">
                        ' . csrf_field() . '
                        ' . ComponentBuilder::button([
                            'text' => 'Clonar Turno',
                            'icon' => 'fa-copy',
                            'style' => 'outline-info',
                            'class' => 'w-100',
                            'type' => 'submit'
                        ]) . '
                    </form>
                    <hr>
                    <form method="POST" action="' . sp_shifts_delete_url($shift->id) . '"
                        onsubmit="return confirm(\'Tem certeza que deseja excluir este turno?\');">
                        ' . csrf_field() . '
                        <input type="hidden" name="_method" value="DELETE">
                        ' . ComponentBuilder::button([
                            'text' => 'Excluir Turno',
                            'icon' => 'fa-trash',
                            'style' => 'outline-danger',
                            'class' => 'w-100',
                            'type' => 'submit'
                        ]) . '
                    </form>
                </div>
            '
        ]) ?>

        <!-- Statistics -->
        <?= ComponentBuilder::card([
            'title' => 'Estatísticas',
            'icon' => 'fa-chart-bar',
            'content' => '
                <div class="list-group list-group-flush">
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Escalas Totais</span>
                        <strong>' . (int) ($statistics['total_schedules'] ?? 0) . '</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Escalas Futuras</span>
                        <strong>' . (int) ($statistics['upcoming_schedules'] ?? 0) . '</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Escalas Concluídas</span>
                        <strong>' . (int) ($statistics['completed_schedules'] ?? 0) . '</strong>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="text-muted">Escalas Canceladas</span>
                        <strong>' . (int) ($statistics['cancelled_schedules'] ?? 0) . '</strong>
                    </div>
                </div>
            '
        ]) ?>
    </div>

</div>

<?= $this->endSection() ?>
