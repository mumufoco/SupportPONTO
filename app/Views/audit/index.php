<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Auditoria e Conformidade<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$navigationContext = is_array($navigationContext ?? null) ? $navigationContext : ['enabled' => false];
$stats   = $stats   ?? [];
$levels  = $levels  ?? ['info', 'warning', 'error', 'critical'];
$levelLabel = ['info' => 'Info', 'warning' => 'Atenção', 'error' => 'Erro', 'critical' => 'Crítico'];

$dateFrom = date('Y-m-01');
$dateTo   = date('Y-m-d');

$headerActions = [];
if (($navigationContext['enabled'] ?? false) === true) {
    $headerActions[] = ['label' => $navigationContext['backLabel'] ?? 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => $navigationContext['backUrl'] ?? site_url('dashboard/admin')];
}
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Auditoria e Conformidade',
        'subtitle' => 'Rastreabilidade completa — eventos críticos, segurança, biometria e conformidade MTE 671/2021.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => $headerActions,
    ]) ?>

    <?php if (($auditScopeLimited ?? false) === true): ?>
        <div class="alert alert-info d-flex gap-2 align-items-start mb-3 py-2">
            <i class="bi bi-funnel-fill mt-1 flex-shrink-0"></i>
            <small>Como gestor, você visualiza logs do seu departamento e eventos vinculados à sua equipe.</small>
        </div>
    <?php endif; ?>

    <div class="sp-grid-4 mb-4">
        <div class="stat-card">
            <div class="stat-card-icon primary"><i class="bi bi-clipboard-data-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Total de eventos</div>
                <div class="stat-card-value"><?= number_format((int) ($stats['total'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon success"><i class="bi bi-calendar-day-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Hoje</div>
                <div class="stat-card-value"><?= number_format((int) ($stats['today'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon info"><i class="bi bi-calendar-week-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Esta semana</div>
                <div class="stat-card-value"><?= number_format((int) ($stats['this_week'] ?? 0)) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-icon danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
            <div class="stat-card-content">
                <div class="stat-card-label">Críticos (30 dias)</div>
                <div class="stat-card-value"><?= number_format((int) ($stats['critical'] ?? 0)) ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($stats['active_users']) || !empty($stats['common_actions'])): ?>
    <div class="row g-3 mb-4">
        <?php if (!empty($stats['active_users'])): ?>
        <div class="col-md-6">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-people-fill"></i></span>
                        Usuários mais ativos <span class="text-muted small fw-normal">(7 dias)</span>
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <?php foreach ($stats['active_users'] as $u): ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span class="small"><?= esc($u['name'] ?? '—') ?></span>
                            <span class="sp-badge sp-badge-primary"><?= (int) ($u['count'] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($stats['common_actions'])): ?>
        <div class="col-md-6">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(255,193,7,.15);color:#997404;"><i class="bi bi-lightning-fill"></i></span>
                        Ações mais frequentes <span class="text-muted small fw-normal">(7 dias)</span>
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <?php foreach ($stats['common_actions'] as $a): ?>
                        <?php $act = is_object($a) ? $a->action : ($a['action'] ?? '—'); $cnt = is_object($a) ? $a->count : ($a['count'] ?? 0); ?>
                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                            <code class="small text-secondary"><?= esc($act) ?></code>
                            <span class="sp-badge sp-badge-neutral"><?= (int) $cnt ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="sp-data-card mb-4">
        <div class="sp-data-card__body">
            <form class="row g-3" onsubmit="return false;">
                <div class="col-md-3">
                    <label class="form-label" for="f-search">Busca livre</label>
                    <input type="text" id="f-search" class="form-control" placeholder="Palavra-chave, entidade, IP...">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="f-entity">Entidade</label>
                    <select id="f-entity" class="form-select">
                        <option value="">Todas as entidades</option>
                        <?php foreach (($entities ?? []) as $e): ?>
                            <option value="<?= esc($e['value']) ?>"><?= esc($e['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="f-level">Nível</label>
                    <select id="f-level" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($levels as $lvl): ?>
                            <option value="<?= esc($lvl) ?>"><?= esc($levelLabel[$lvl] ?? $lvl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="f-action">Ação</label>
                    <select id="f-action" class="form-select">
                        <option value="">Todas as ações</option>
                        <?php foreach (($actions ?? []) as $a): ?>
                            <option value="<?= esc($a['value']) ?>"><?= esc($a['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="f-date-from">De</label>
                    <input type="date" id="f-date-from" class="form-control" value="<?= esc($dateFrom) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="f-date-to">Até</label>
                    <input type="date" id="f-date-to" class="form-control" value="<?= esc($dateTo) ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="button" id="btn-filter" class="btn btn-primary flex-fill">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                    <button type="button" id="btn-clear" class="btn btn-outline-secondary">Limpar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-list-columns-reverse"></i></span>
                Eventos
            </h2>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download me-1"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="export-menu">
                    <li><a class="dropdown-item" data-export="csv" href="#"><i class="bi bi-file-earmark-text me-2 text-secondary"></i>CSV</a></li>
                    <li><a class="dropdown-item" data-export="pdf" href="#"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF</a></li>
                    <li><a class="dropdown-item" data-export="excel" href="#"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" data-export="afd" href="#"><i class="bi bi-filetype-txt me-2 text-primary"></i>AFD (Portaria 671/2021)</a></li>
                </ul>
            </div>
        </div>
        <div class="sp-data-card__body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:90px;">Nível</th>
                            <th style="width:180px;">Ação</th>
                            <th>Descrição</th>
                            <th style="width:150px;">Usuário</th>
                            <th style="width:150px;">Data/Hora</th>
                            <th class="text-end" style="width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="audit-tbody">
                        <tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Carregando eventos...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center py-2 px-3 border-top" id="audit-pagination-bar">
                <small class="text-muted" id="audit-info"></small>
                <nav><ul class="pagination pagination-sm mb-0" id="audit-pagination"></ul></nav>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
(function () {
    const ENDPOINT  = '<?= site_url('audit/data') ?>';
    const CSRF_NAME = '<?= csrf_token() ?>';
    let   csrfHash  = '<?= csrf_hash() ?>';
    const PAGE_SIZE = 25;

    const levelBadge = {
        info:     '<span class="sp-badge sp-badge-info">Info</span>',
        warning:  '<span class="sp-badge sp-badge-warning">Atenção</span>',
        error:    '<span class="sp-badge sp-badge-danger">Erro</span>',
        critical: '<span class="sp-badge sp-badge-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i>Crítico</span>',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function currentFilters() {
        return {
            search: document.getElementById('f-search').value.trim(),
            level: document.getElementById('f-level').value,
            action: document.getElementById('f-action').value,
            entity: document.getElementById('f-entity').value,
            dateFrom: document.getElementById('f-date-from').value,
            dateTo: document.getElementById('f-date-to').value,
        };
    }

    function buildPayload(start) {
        const f = currentFilters();
        return new URLSearchParams({
            [CSRF_NAME]:       csrfHash,
            draw:              1,
            start:             start,
            length:            PAGE_SIZE,
            'search[value]':   f.search,
            filter_level:      f.level,
            filter_action:     f.action,
            filter_entity:     f.entity,
            filter_start_date: f.dateFrom,
            filter_end_date:   f.dateTo,
        }).toString();
    }

    function renderPagination(total, start) {
        const pages   = Math.max(1, Math.ceil(total / PAGE_SIZE));
        const current = Math.floor(start / PAGE_SIZE);
        const pag = document.getElementById('audit-pagination');
        pag.innerHTML = '';

        function addItem(label, page, opts) {
            opts = opts || {};
            const li = document.createElement('li');
            li.className = 'page-item' + (opts.active ? ' active' : '') + (opts.disabled ? ' disabled' : '');
            const a = document.createElement('a');
            a.className = 'page-link';
            a.href = '#';
            a.innerHTML = label;
            if (!opts.disabled && !opts.active) {
                a.addEventListener('click', function (e) { e.preventDefault(); load(page * PAGE_SIZE); });
            }
            li.appendChild(a);
            pag.appendChild(li);
        }

        addItem('&laquo;', current - 1, { disabled: current === 0 });

        const windowStart = Math.max(0, current - 2);
        const windowEnd = Math.min(pages - 1, current + 2);
        if (windowStart > 0) { addItem('1', 0); if (windowStart > 1) addItem('…', 0, { disabled: true }); }
        for (let i = windowStart; i <= windowEnd; i++) addItem(String(i + 1), i, { active: i === current });
        if (windowEnd < pages - 1) { if (windowEnd < pages - 2) addItem('…', 0, { disabled: true }); addItem(String(pages), pages - 1); }

        addItem('&raquo;', current + 1, { disabled: current >= pages - 1 });
    }

    async function load(start) {
        start = start ?? 0;
        const tbody = document.getElementById('audit-tbody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Carregando...</td></tr>';

        try {
            const res  = await fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: buildPayload(start),
            });
            const json = await res.json();
            if (json.csrf_hash) csrfHash = json.csrf_hash;

            const data  = json.data ?? [];
            const total = json.recordsFiltered ?? 0;

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="sp-empty"><div class="sp-empty-icon"><i class="bi bi-shield-x"></i></div><p class="sp-empty-title">Nenhum evento encontrado</p><p class="text-muted small mb-0">Ajuste os filtros para ver mais resultados.</p></div></td></tr>';
                document.getElementById('audit-info').textContent = '';
                document.getElementById('audit-pagination').innerHTML = '';
                return;
            }

            tbody.innerHTML = data.map(function (r) {
                const lvl    = (r.level || 'info').toLowerCase();
                const badge  = levelBadge[lvl] || '<span class="sp-badge sp-badge-neutral">' + esc(lvl) + '</span>';
                const detUrl = r.id ? '<?= site_url('audit/') ?>' + r.id : '#';
                const descFull = r.description || '-';
                const desc   = esc(descFull.substring(0, 90)) + (descFull.length > 90 ? '…' : '');
                return '<tr>'
                    + '<td>' + badge + '</td>'
                    + '<td><code class="small text-dark">' + esc(r.action || '-') + '</code></td>'
                    + '<td class="small text-muted">' + desc + '</td>'
                    + '<td class="small">' + esc(r.user || 'Sistema') + '</td>'
                    + '<td class="small text-muted">' + esc(r.created_at || '-') + '</td>'
                    + '<td class="text-end"><div class="table-icon-actions justify-content-end"><a href="' + detUrl + '" class="icon-action" title="Detalhes"><i class="bi bi-eye-fill"></i></a></div></td>'
                    + '</tr>';
            }).join('');

            document.getElementById('audit-info').textContent =
                'Exibindo ' + (start + 1) + '–' + Math.min(start + PAGE_SIZE, total) + ' de ' + total + ' eventos';
            renderPagination(total, start);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Erro ao carregar eventos.</td></tr>';
        }
    }

    document.getElementById('btn-filter').addEventListener('click', () => load(0));
    document.getElementById('btn-clear').addEventListener('click', () => {
        ['f-search', 'f-level', 'f-action', 'f-entity'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('f-date-from').value = '<?= date('Y-m-01') ?>';
        document.getElementById('f-date-to').value   = '<?= date('Y-m-d') ?>';
        load(0);
    });
    document.getElementById('f-search').addEventListener('keydown', e => { if (e.key === 'Enter') load(0); });

    document.getElementById('export-menu').addEventListener('click', function (e) {
        const link = e.target.closest('[data-export]');
        if (!link) return;
        e.preventDefault();

        const f = currentFilters();
        const params = new URLSearchParams({ date_from: f.dateFrom, date_to: f.dateTo }).toString();
        const routes = {
            csv:   '<?= site_url('audit/export') ?>',
            pdf:   '<?= site_url('audit/export/pdf') ?>',
            excel: '<?= site_url('audit/export/excel') ?>',
            afd:   '<?= site_url('audit/afd') ?>',
        };
        window.location.href = routes[link.dataset.export] + '?' + params;
    });

    load(0);
})();
</script>

<?= $this->endSection() ?>
