<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Feriados e Dias Não Trabalhados<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-calendar-x me-2 text-warning"></i>Feriados e Dias Não Trabalhados</h1>
            <p class="text-muted mb-0">Gerencie feriados e dias em que o registro de ponto é bloqueado.</p>
        </div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddHoliday">
            <i class="bi bi-plus-lg me-2"></i>Novo Feriado / Dia
        </button>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control" placeholder="Nome do feriado...">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos os tipos</option>
                        <?php foreach (['national' => 'Nacional', 'state' => 'Estadual', 'municipal' => 'Municipal', 'company' => 'Empresa', 'non_working' => 'Dia Não Trabalhado'] as $val => $label): ?>
                            <option value="<?= esc($val) ?>" <?= ($filters['type'] ?? '') === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Por página</label>
                    <select name="per_page" class="form-select">
                        <?php foreach ([10, 25, 50, 100] as $opt): ?>
                            <option value="<?= $opt ?>" <?= (int)($filters['per_page'] ?? 25) === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="<?= sp_route_url('settings.holidays') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card">
        <div class="card-body p-0">
            <?php if (empty($holidays)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-calendar-check fs-1 d-block mb-3"></i>
                    <p class="mb-0">Nenhum feriado cadastrado. Clique em <strong>Novo Feriado / Dia</strong> para começar.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Data</th>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th class="text-center">Recorrente</th>
                                <th class="text-center">Bloqueia Ponto</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($holidays as $h): ?>
                            <tr>
                                <td>
                                    <strong><?= esc(date('d/m/Y', strtotime((string) $h->date))) ?></strong>
                                    <?php if (!empty($h->recurring)): ?>
                                        <br><small class="text-muted"><?= esc(date('d/m', strtotime((string) $h->date))) ?> todo ano</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= esc($h->name) ?>
                                    <?php if (!empty($h->description)): ?>
                                        <br><small class="text-muted"><?= esc($h->description) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeColors = ['national' => 'primary', 'state' => 'info', 'municipal' => 'secondary', 'company' => 'success', 'non_working' => 'warning'];
                                    $typeColor = $typeColors[$h->type ?? ''] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= esc($typeColor) ?>">
                                        <?= esc(\App\Models\HolidayModel::typeLabel($h->type ?? '')) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?= !empty($h->recurring) ? '<i class="bi bi-arrow-repeat text-success" title="Recorrente todo ano"></i>' : '<i class="bi bi-dash text-muted"></i>' ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($h->blocks_punch)): ?>
                                        <span class="badge bg-danger"><i class="bi bi-lock-fill me-1"></i>Bloqueado</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-secondary border"><i class="bi bi-unlock me-1"></i>Livre</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($h->active)): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a href="<?= sp_route_url('settings.holidays.edit', (int) $h->id) ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="<?= sp_route_url('settings.holidays.toggle', (int) $h->id) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm <?= !empty($h->active) ? 'btn-outline-warning' : 'btn-outline-success' ?>" title="<?= !empty($h->active) ? 'Desativar' : 'Ativar' ?>">
                                                <i class="bi <?= !empty($h->active) ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="<?= sp_route_url('settings.holidays.delete', (int) $h->id) ?>" class="d-inline"
                                              onsubmit="return confirm('Excluir feriado \'<?= esc(addslashes($h->name)) ?>\'?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($pager): ?>
                    <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                        <small class="text-muted"><?= (int) ($meta['total'] ?? 0) ?> registros encontrados</small>
                        <?= $pager->links('default', 'default_full') ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Feriado -->
<div class="modal fade" id="modalAddHoliday" tabindex="-1" aria-labelledby="modalAddHolidayLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= sp_route_url('settings.holidays.store') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddHolidayLabel">
                        <i class="bi bi-calendar-plus me-2"></i>Novo Feriado / Dia Não Trabalhado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="h_name" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="h_name" class="form-control" placeholder="Ex: Corpus Christi" required maxlength="255" value="<?= esc(old('name')) ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="h_date" class="form-label">Data <span class="text-danger">*</span></label>
                            <input type="date" name="date" id="h_date" class="form-control" required value="<?= esc(old('date')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="h_type" class="form-label">Tipo <span class="text-danger">*</span></label>
                            <select name="type" id="h_type" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="national" <?= old('type') === 'national' ? 'selected' : '' ?>>Nacional</option>
                                <option value="state" <?= old('type') === 'state' ? 'selected' : '' ?>>Estadual</option>
                                <option value="municipal" <?= old('type') === 'municipal' ? 'selected' : '' ?>>Municipal</option>
                                <option value="company" <?= old('type') === 'company' ? 'selected' : '' ?>>Empresa</option>
                                <option value="non_working" <?= old('type') === 'non_working' ? 'selected' : '' ?>>Dia Não Trabalhado</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="h_description" class="form-label">Observação</label>
                        <input type="text" name="description" id="h_description" class="form-control" maxlength="500" placeholder="Opcional..." value="<?= esc(old('description')) ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="recurring" id="h_recurring" value="1" <?= old('recurring') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="h_recurring">
                                    <strong>Recorrente</strong><br>
                                    <small class="text-muted">Repetir todo ano nesta data</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" name="blocks_punch" id="h_blocks_punch" value="1" checked <?= old('blocks_punch', '1') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="h_blocks_punch">
                                    <strong>Bloquear ponto</strong><br>
                                    <small class="text-muted">Impede registro sem autorização</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
