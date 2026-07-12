<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Editar Feriado<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.sp-form-card {
    background: var(--sp-bg-surface);
    border: 1px solid var(--sp-border);
    border-radius: 1rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 1.25rem;
}
.sp-form-card__head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--sp-border);
    background: var(--sp-gray-50, rgba(0,0,0,.02));
}
.sp-form-card__icon {
    width: 2.1rem; height: 2.1rem;
    border-radius: .5rem;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
.sp-form-card__icon.c-yellow  { background: rgba(255,193,7,.15);   color: #d97706 }
.sp-form-card__icon.c-blue    { background: rgba(13,110,253,.12);  color: #0d6efd }
.sp-form-card__icon.c-red     { background: rgba(220,53,69,.12);   color: #dc3545 }
.sp-form-card__icon.c-green   { background: rgba(25,135,84,.12);   color: #198754 }
.sp-form-card__title { font-weight: 700; font-size: .95rem; margin: 0 }
.sp-form-card__sub   { font-size: .75rem; color: var(--sp-text-muted); margin: 0 }
.sp-form-card__body  { padding: 1.5rem }

.sp-holiday-preview {
    border-radius: .75rem;
    padding: 1.25rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: background .2s;
}
.sp-holiday-preview__date {
    background: rgba(255,255,255,.25);
    border-radius: .6rem;
    min-width: 3.5rem;
    text-align: center;
    padding: .4rem .6rem;
    line-height: 1.15;
}
.sp-holiday-preview__day   { font-size: 1.6rem; font-weight: 800; display: block }
.sp-holiday-preview__month { font-size: .7rem;  font-weight: 600; text-transform: uppercase; letter-spacing: .05em; display: block }
.sp-holiday-preview__info  { flex: 1; min-width: 0 }
.sp-holiday-preview__name  { font-weight: 700; font-size: 1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis }
.sp-holiday-preview__meta  { font-size: .78rem; opacity: .85; margin-top: .15rem }

.sp-toggle-card {
    border: 1.5px solid var(--sp-border);
    border-radius: .75rem;
    padding: .9rem 1rem;
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    cursor: pointer;
    transition: border-color .15s, background .15s;
    user-select: none;
}
.sp-toggle-card:has(input:checked) {
    border-color: var(--bs-primary, #0d6efd);
    background: rgba(13,110,253,.04);
}
.sp-toggle-card .form-check-input { margin-top: .15rem; flex-shrink: 0 }
.sp-toggle-card__body  { flex: 1 }
.sp-toggle-card__label { font-weight: 600; font-size: .9rem; display: block; margin-bottom: .1rem }
.sp-toggle-card__desc  { font-size: .78rem; color: var(--sp-text-muted); line-height: 1.4 }

.sp-type-badge {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .25rem .7rem; border-radius: 999px;
    font-size: .75rem; font-weight: 600;
}
</style>

<div class="container-fluid sp-responsive-screen py-4">

    <?= view('components/page_header', [
        'title'    => 'Editar Feriado',
        'subtitle' => 'Ajuste as informações do feriado ou dia não trabalhado.',
        'icon'     => 'bi bi-calendar-event-fill',
        'actions'  => [
            ['label' => 'Feriados', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_route_url('settings.holidays')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <form method="POST"
          action="<?= sp_route_url('settings.holidays.update', (int) $holiday->id) ?>"
          id="form-holiday-edit">
        <?= csrf_field() ?>

        <div class="row g-4">

            <!-- ── Coluna principal ──────────────────────────────────── -->
            <div class="col-lg-8">

                <!-- Card: Identificação -->
                <div class="sp-form-card">
                    <div class="sp-form-card__head">
                        <div class="sp-form-card__icon c-yellow"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <p class="sp-form-card__title">Identificação</p>
                            <p class="sp-form-card__sub">Nome, data e categoria do feriado</p>
                        </div>
                    </div>
                    <div class="sp-form-card__body">

                        <div class="mb-4">
                            <label for="name" class="form-label fw-semibold">
                                Nome do feriado <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   class="form-control form-control-lg"
                                   value="<?= esc(old('name', $holiday->name)) ?>"
                                   placeholder="Ex: Corpus Christi, Dia do Trabalho…"
                                   maxlength="255"
                                   required
                                   autocomplete="off">
                            <div class="form-text">Máx. 255 caracteres. Será exibido no calendário e nos relatórios.</div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-5">
                                <label for="date" class="form-label fw-semibold">
                                    Data <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       name="date"
                                       id="date"
                                       class="form-control"
                                       value="<?= esc(old('date', $holiday->date)) ?>"
                                       required>
                            </div>
                            <div class="col-sm-7">
                                <label for="type" class="form-label fw-semibold">
                                    Tipo <span class="text-danger">*</span>
                                </label>
                                <select name="type" id="type" class="form-select" required>
                                    <?php
                                    $types = [
                                        'national'    => ['label' => 'Nacional',            'icon' => 'bi-flag-fill',        'color' => 'primary'],
                                        'state'       => ['label' => 'Estadual',             'icon' => 'bi-geo-alt-fill',     'color' => 'info'],
                                        'municipal'   => ['label' => 'Municipal',            'icon' => 'bi-building',         'color' => 'secondary'],
                                        'company'     => ['label' => 'Empresa',              'icon' => 'bi-briefcase-fill',   'color' => 'success'],
                                        'non_working' => ['label' => 'Dia Não Trabalhado',   'icon' => 'bi-slash-circle',     'color' => 'warning'],
                                    ];
                                    $currentType = old('type', $holiday->type);
                                    foreach ($types as $val => $meta):
                                    ?>
                                    <option value="<?= esc($val) ?>" <?= $currentType === $val ? 'selected' : '' ?>>
                                        <?= esc($meta['label']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-0">
                            <label for="description" class="form-label fw-semibold">
                                Observação
                                <span class="badge bg-light text-muted border ms-1" style="font-size:.7rem">Opcional</span>
                            </label>
                            <textarea name="description"
                                      id="description"
                                      class="form-control"
                                      rows="2"
                                      maxlength="500"
                                      placeholder="Informação adicional exibida nos relatórios…"><?= esc(old('description', $holiday->description ?? '')) ?></textarea>
                            <div class="form-text d-flex justify-content-between">
                                <span>Máx. 500 caracteres.</span>
                                <span id="desc-counter" class="text-muted small">0 / 500</span>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Card: Comportamento -->
                <div class="sp-form-card">
                    <div class="sp-form-card__head">
                        <div class="sp-form-card__icon c-blue"><i class="bi bi-sliders"></i></div>
                        <div>
                            <p class="sp-form-card__title">Comportamento</p>
                            <p class="sp-form-card__sub">Como este feriado afeta o registro de ponto</p>
                        </div>
                    </div>
                    <div class="sp-form-card__body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="sp-toggle-card w-100 h-100" for="recurring">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="recurring"
                                           id="recurring"
                                           value="1"
                                           <?= old('recurring', $holiday->recurring ?? 0) ? 'checked' : '' ?>>
                                    <div class="sp-toggle-card__body">
                                        <span class="sp-toggle-card__label">
                                            <i class="bi bi-arrow-repeat text-primary me-1"></i>Recorrente
                                        </span>
                                        <span class="sp-toggle-card__desc">
                                            O feriado se repete todo ano na mesma data (ex: Natal, Ano Novo).
                                        </span>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="sp-toggle-card w-100 h-100" for="blocks_punch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="blocks_punch"
                                           id="blocks_punch"
                                           value="1"
                                           <?= old('blocks_punch', $holiday->blocks_punch ?? 1) ? 'checked' : '' ?>>
                                    <div class="sp-toggle-card__body">
                                        <span class="sp-toggle-card__label">
                                            <i class="bi bi-lock-fill text-danger me-1"></i>Bloquear ponto
                                        </span>
                                        <span class="sp-toggle-card__desc">
                                            Impede novos registros de ponto sem autorização explícita do administrador.
                                        </span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ações -->
                <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                    <a href="<?= sp_route_url('settings.holidays') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary px-4" id="btn-save">
                        <i class="bi bi-check-lg me-1"></i>Salvar Alterações
                    </button>
                </div>

            </div><!-- /col-lg-8 -->

            <!-- ── Coluna lateral ────────────────────────────────────── -->
            <div class="col-lg-4">

                <!-- Preview dinâmico -->
                <div class="sp-form-card mb-3">
                    <div class="sp-form-card__head">
                        <div class="sp-form-card__icon c-yellow"><i class="bi bi-eye"></i></div>
                        <div>
                            <p class="sp-form-card__title">Pré-visualização</p>
                            <p class="sp-form-card__sub">Como aparece no calendário</p>
                        </div>
                    </div>
                    <div class="sp-form-card__body p-3">
                        <div class="sp-holiday-preview text-white" id="preview-card" style="background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);">
                            <div class="sp-holiday-preview__date">
                                <span class="sp-holiday-preview__day"  id="prev-day">—</span>
                                <span class="sp-holiday-preview__month" id="prev-month">—</span>
                            </div>
                            <div class="sp-holiday-preview__info">
                                <div class="sp-holiday-preview__name" id="prev-name">Nome do feriado</div>
                                <div class="sp-holiday-preview__meta" id="prev-meta">—</div>
                            </div>
                            <div id="prev-icons" class="d-flex flex-column gap-1 align-items-end"></div>
                        </div>
                    </div>
                </div>

                <!-- Info: Tipos de feriado -->
                <div class="sp-form-card">
                    <div class="sp-form-card__head">
                        <div class="sp-form-card__icon c-blue"><i class="bi bi-info-circle"></i></div>
                        <div>
                            <p class="sp-form-card__title">Tipos de feriado</p>
                        </div>
                    </div>
                    <div class="sp-form-card__body p-0">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item d-flex gap-2 py-2">
                                <span class="sp-type-badge bg-primary bg-opacity-10 text-primary">Nacional</span>
                                <span class="text-muted">Feriado definido por lei federal (CLT). Obrigatório em todo o Brasil.</span>
                            </li>
                            <li class="list-group-item d-flex gap-2 py-2">
                                <span class="sp-type-badge bg-info bg-opacity-10 text-info">Estadual</span>
                                <span class="text-muted">Decretado pelo governo do estado onde a empresa opera.</span>
                            </li>
                            <li class="list-group-item d-flex gap-2 py-2">
                                <span class="sp-type-badge bg-secondary bg-opacity-10 text-secondary">Municipal</span>
                                <span class="text-muted">Feriado do município da empresa.</span>
                            </li>
                            <li class="list-group-item d-flex gap-2 py-2">
                                <span class="sp-type-badge bg-success bg-opacity-10 text-success">Empresa</span>
                                <span class="text-muted">Ponto facultativo ou data comemorativa definida internamente.</span>
                            </li>
                            <li class="list-group-item d-flex gap-2 py-2 border-0">
                                <span class="sp-type-badge bg-warning bg-opacity-10 text-warning">Não Trabalhado</span>
                                <span class="text-muted">Dia sem expediente por decisão operacional, sem natureza de feriado legal.</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Metadados do registro -->
                <div class="sp-form-card mt-3">
                    <div class="sp-form-card__head">
                        <div class="sp-form-card__icon c-green"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <p class="sp-form-card__title">Registro</p>
                        </div>
                    </div>
                    <div class="sp-form-card__body p-0">
                        <ul class="list-unstyled mb-0 small">
                            <li class="d-flex justify-content-between px-3 py-2 border-bottom">
                                <span class="text-muted">ID</span>
                                <span class="font-monospace fw-semibold">#<?= (int) $holiday->id ?></span>
                            </li>
                            <li class="d-flex justify-content-between px-3 py-2 border-bottom">
                                <span class="text-muted">Status</span>
                                <?php if (!empty($holiday->active)): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Ativo</span>
                                <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary border">Inativo</span>
                                <?php endif; ?>
                            </li>
                            <?php if (!empty($holiday->created_at)): ?>
                            <li class="d-flex justify-content-between px-3 py-2 border-bottom">
                                <span class="text-muted">Criado em</span>
                                <span><?= date('d/m/Y', strtotime($holiday->created_at)) ?></span>
                            </li>
                            <?php endif; ?>
                            <?php if (!empty($holiday->updated_at)): ?>
                            <li class="d-flex justify-content-between px-3 py-2">
                                <span class="text-muted">Atualizado</span>
                                <span><?= date('d/m/Y H:i', strtotime($holiday->updated_at)) ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

            </div><!-- /col-lg-4 -->

        </div><!-- /row -->
    </form>
</div>

<script <?= csp_script_nonce_attr() ?>>
(function () {
    const typeColors = {
        national:    ['#0d6efd', '#0a58ca'],
        state:       ['#0dcaf0', '#0aa2c0'],
        municipal:   ['#6c757d', '#565e64'],
        company:     ['#198754', '#146c43'],
        non_working: ['#f59e0b', '#d97706'],
    };
    const typeLabels = {
        national:    'Nacional',
        state:       'Estadual',
        municipal:   'Municipal',
        company:     'Empresa',
        non_working: 'Dia Não Trabalhado',
    };
    const months = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

    const nameEl    = document.getElementById('name');
    const dateEl    = document.getElementById('date');
    const typeEl    = document.getElementById('type');
    const recurEl   = document.getElementById('recurring');
    const blockEl   = document.getElementById('blocks_punch');
    const descEl    = document.getElementById('description');
    const descCount = document.getElementById('desc-counter');

    function updatePreview() {
        const name = nameEl.value.trim() || 'Nome do feriado';
        const type = typeEl.value;
        const [c1, c2] = typeColors[type] || ['#6c757d', '#495057'];

        // Date
        let dayStr = '—', monthStr = '—';
        if (dateEl.value) {
            const parts = dateEl.value.split('-');
            if (parts.length === 3) {
                dayStr   = String(parseInt(parts[2], 10));
                monthStr = months[parseInt(parts[1], 10) - 1] || '—';
            }
        }

        document.getElementById('preview-card').style.background =
            `linear-gradient(135deg, ${c1} 0%, ${c2} 100%)`;
        document.getElementById('prev-day').textContent   = dayStr;
        document.getElementById('prev-month').textContent = monthStr;
        document.getElementById('prev-name').textContent  = name;
        document.getElementById('prev-meta').textContent  = typeLabels[type] || type;

        // Icons
        const iconsEl = document.getElementById('prev-icons');
        iconsEl.innerHTML = '';
        if (recurEl.checked) {
            const ic = document.createElement('i');
            ic.className = 'bi bi-arrow-repeat';
            ic.title = 'Recorrente';
            ic.style.cssText = 'font-size:.9rem;opacity:.9';
            iconsEl.appendChild(ic);
        }
        if (blockEl.checked) {
            const ic = document.createElement('i');
            ic.className = 'bi bi-lock-fill';
            ic.title = 'Bloqueia ponto';
            ic.style.cssText = 'font-size:.9rem;opacity:.9';
            iconsEl.appendChild(ic);
        }
    }

    function updateDescCounter() {
        const len = descEl.value.length;
        descCount.textContent = `${len} / 500`;
        descCount.className = len > 450 ? 'text-warning small fw-semibold' : 'text-muted small';
    }

    [nameEl, dateEl, typeEl, recurEl, blockEl].forEach(el =>
        el.addEventListener('change', updatePreview));
    nameEl.addEventListener('input', updatePreview);
    descEl.addEventListener('input', updateDescCounter);

    // Spinner on submit
    document.getElementById('form-holiday-edit').addEventListener('submit', function () {
        const btn = document.getElementById('btn-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Salvando…';
    });

    // Init
    updatePreview();
    updateDescCounter();
})();
</script>

<?= $this->endSection() ?>
