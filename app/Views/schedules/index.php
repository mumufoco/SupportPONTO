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
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Horário</th>
                            <th>Status</th>
                            <th>Atualização</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($schedules ?? []) as $schedule): ?>
                            <tr>
                                <td><strong><?= esc($schedule['name'] ?? '-') ?></strong></td>
                                <td><?= esc($schedule['hours'] ?? '-') ?></td>
                                <td>
                                    <span class="sp-schedule-badge">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span><?= !empty($schedule['active']) ? 'Ativo' : 'Inativo' ?></span>
                                    </span>
                                </td>
                                <td><?= esc($schedule['updated_at'] ?? '-') ?></td>
                                <td class="text-end">
                                    <a href="<?= sp_route_url('settings.work-shifts') ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
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
