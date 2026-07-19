<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Feriados e Dias Não Trabalhados<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
/* Botões de ação um pouco maiores que o padrão .icon-action (2.35rem/1.2rem),
   seguindo o pedido de deixá-los como os de settings/departments, porém maiores. */
.sp-icon-actions-lg .icon-action { width: 2.6rem; height: 2.6rem; font-size: 1.35rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $existingHolidayKeys = $existingHolidayKeys ?? [];
    $typeBadges = [
        'national'    => 'sp-badge-primary',
        'state'       => 'sp-badge-info',
        'municipal'   => 'sp-badge-neutral',
        'company'     => 'sp-badge-success',
        'non_working' => 'sp-badge-warning',
    ];
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Feriados e Dias Não Trabalhados',
        'subtitle' => 'Gerencie feriados e dias em que o registro de ponto é bloqueado.',
        'icon'     => 'bi bi-calendar-x',
        'actions'  => [
            [
                'label' => 'Feriados Nacionais', 'icon' => 'bi bi-flag-fill', 'url' => '#',
                'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalNationalHolidays"',
            ],
            [
                'label' => 'Feriados Locais (GO/Goiânia)', 'icon' => 'bi bi-geo-alt-fill', 'url' => '#',
                'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalLocalHolidays"',
            ],
            [
                'label' => 'Personalizado', 'icon' => 'bi bi-plus-lg', 'url' => '#',
                'attrs' => 'data-bs-toggle="modal" data-bs-target="#modalAddHoliday"',
            ],
        ],
    ]) ?>

    <!-- Filtros -->
    <div class="sp-data-card mb-3">
        <div class="sp-data-card__body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Buscar</label>
                    <input type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>" class="form-control" placeholder="Nome do feriado...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos os tipos</option>
                        <?php foreach (['national' => 'Nacional', 'state' => 'Estadual', 'municipal' => 'Municipal', 'company' => 'Empresa', 'non_working' => 'Dia Não Trabalhado'] as $val => $label): ?>
                            <option value="<?= esc($val) ?>" <?= ($filters['type'] ?? '') === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Buscar</button>
                    <a href="<?= sp_route_url('settings.holidays') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-calendar3"></i></span>
                Feriados cadastrados
            </h2>
            <span class="sp-badge sp-badge-neutral"><?= (int) ($meta['total'] ?? 0) ?> registro<?= (int) ($meta['total'] ?? 0) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="sp-data-card__body p-0">
            <?php if (empty($holidays)): ?>
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-calendar-check"></i></div>
                    <p class="sp-empty-title">Nenhum feriado cadastrado</p>
                    <p class="text-muted small mb-0">Use um dos botões acima para adicionar feriados nacionais, locais ou personalizados.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
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
                                    <span class="sp-badge <?= $typeBadges[$h->type ?? ''] ?? 'sp-badge-neutral' ?>">
                                        <?= esc(\App\Models\HolidayModel::typeLabel($h->type ?? '')) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?= !empty($h->recurring) ? '<i class="bi bi-arrow-repeat text-success" title="Recorrente todo ano"></i>' : '<i class="bi bi-dash text-muted"></i>' ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($h->blocks_punch)): ?>
                                        <span class="sp-badge sp-badge-danger"><i class="bi bi-lock-fill me-1"></i>Bloqueado</span>
                                    <?php else: ?>
                                        <span class="sp-badge sp-badge-neutral"><i class="bi bi-unlock me-1"></i>Livre</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($h->active)): ?>
                                        <span class="sp-badge sp-badge-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="sp-badge sp-badge-neutral">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="table-icon-actions sp-icon-actions-lg">
                                        <a href="<?= sp_route_url('settings.holidays.edit', (int) $h->id) ?>" class="icon-action icon-action-edit" title="Editar">
                                            <i class="bi bi-pencil-fill"></i>
                                        </a>
                                        <form method="POST" action="<?= sp_route_url('settings.holidays.toggle', (int) $h->id) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="icon-action <?= !empty($h->active) ? 'icon-action-warning' : 'icon-action-success' ?>" title="<?= !empty($h->active) ? 'Desativar' : 'Ativar' ?>">
                                                <i class="bi <?= !empty($h->active) ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="<?= sp_route_url('settings.holidays.delete', (int) $h->id) ?>" class="d-inline"
                                              onsubmit="return confirm('Excluir feriado \'<?= esc(addslashes($h->name)) ?>\'?')">
                                            <?= csrf_field() ?>
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

<!-- Modal: Feriados Nacionais -->
<div class="modal fade" id="modalNationalHolidays" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="<?= sp_route_url('settings.holidays.bulk-store') ?>" id="formNationalHolidays">
                <?= csrf_field() ?>
                <input type="hidden" name="group" value="national">
                <input type="hidden" name="year" id="nationalHolidaysYear" value="<?= (int) $currentYear ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-flag-fill me-2"></i>Feriados Nacionais</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Ano de referência</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="year_toggle" id="yearToggleCurrent" value="<?= (int) $currentYear ?>" checked>
                            <label class="btn btn-outline-primary" for="yearToggleCurrent"><?= (int) $currentYear ?></label>
                            <input type="radio" class="btn-check" name="year_toggle" id="yearToggleNext" value="<?= (int) $nextYear ?>">
                            <label class="btn btn-outline-primary" for="yearToggleNext"><?= (int) $nextYear ?></label>
                        </div>
                        <div class="form-text">Carnaval, Sexta-feira Santa e Corpus Christi mudam de data todo ano — escolha o ano correto antes de confirmar.</div>
                    </div>

                    <?php foreach ([$currentYear, $nextYear] as $yr): ?>
                    <div class="national-year-block" data-year="<?= (int) $yr ?>" style="<?= $yr === $currentYear ? '' : 'display:none;' ?>">
                        <div class="list-group">
                            <?php foreach (($nationalCatalog[$yr] ?? []) as $key => $item): ?>
                                <?php $exists = isset($existingHolidayKeys[$item['date'] . '|' . $item['name']]); ?>
                                <label class="list-group-item d-flex align-items-start gap-2 <?= $exists ? 'text-muted' : '' ?>">
                                    <input type="checkbox" class="form-check-input mt-1" name="keys[]" value="<?= esc($key) ?>"
                                           <?= $exists ? 'disabled' : 'checked' ?>>
                                    <span class="flex-grow-1">
                                        <span class="fw-semibold"><?= esc($item['name']) ?></span>
                                        <span class="text-muted small ms-1"><?= esc(date('d/m/Y', strtotime($item['date']))) ?></span>
                                        <?php if ($exists): ?>
                                            <span class="sp-badge sp-badge-neutral ms-1">Já cadastrado</span>
                                        <?php else: ?>
                                            <span class="sp-badge <?= $typeBadges[$item['type']] ?? 'sp-badge-neutral' ?> ms-1"><?= esc(\App\Models\HolidayModel::typeLabel($item['type'])) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Adicionar selecionados</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Feriados Locais (Goiás/Goiânia) -->
<div class="modal fade" id="modalLocalHolidays" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= sp_route_url('settings.holidays.bulk-store') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="group" value="local_go">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-geo-alt-fill me-2"></i>Feriados Locais — Goiás e Goiânia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-3">
                        <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                        <small>Datas fixas todo ano. Confira antes de confirmar caso sua unidade fique em outro município.</small>
                    </div>
                    <div class="list-group">
                        <?php foreach (($localCatalog ?? []) as $key => $item): ?>
                            <?php $exists = isset($existingHolidayKeys[$item['date'] . '|' . $item['name']]); ?>
                            <label class="list-group-item d-flex align-items-start gap-2 <?= $exists ? 'text-muted' : '' ?>">
                                <input type="checkbox" class="form-check-input mt-1" name="keys[]" value="<?= esc($key) ?>"
                                       <?= $exists ? 'disabled' : 'checked' ?>>
                                <span class="flex-grow-1">
                                    <span class="fw-semibold"><?= esc($item['name']) ?></span>
                                    <span class="text-muted small ms-1"><?= esc(date('d/m', strtotime($item['date']))) ?></span>
                                    <?php if ($exists): ?>
                                        <span class="sp-badge sp-badge-neutral ms-1">Já cadastrado</span>
                                    <?php else: ?>
                                        <span class="sp-badge <?= $typeBadges[$item['type']] ?? 'sp-badge-neutral' ?> ms-1"><?= esc(\App\Models\HolidayModel::typeLabel($item['type'])) ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Adicionar selecionados</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Adicionar Feriado Personalizado -->
<div class="modal fade" id="modalAddHoliday" tabindex="-1" aria-labelledby="modalAddHolidayLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= sp_route_url('settings.holidays.store') ?>">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAddHolidayLabel">
                        <i class="bi bi-calendar-plus me-2"></i>Feriado Personalizado
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="h_name" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="h_name" class="form-control" placeholder="Ex: Aniversário da empresa" required maxlength="255" value="<?= esc(old('name')) ?>">
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

<script <?= csp_script_nonce_attr() ?>>
(function () {
    var yearInputs = document.querySelectorAll('input[name="year_toggle"]');
    var yearField = document.getElementById('nationalHolidaysYear');
    var blocks = document.querySelectorAll('.national-year-block');

    yearInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            yearField.value = input.value;
            blocks.forEach(function (block) {
                block.style.display = block.dataset.year === input.value ? '' : 'none';
            });
        });
    });
})();
</script>
<?= $this->endSection() ?>
