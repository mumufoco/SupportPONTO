<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Auditoria e Conformidade<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php
$navigationContext = is_array($navigationContext ?? null) ? $navigationContext : ['enabled' => false];
$stats   = $stats   ?? [];
$filters = $filters ?? [];
$levels  = $levels  ?? ['info','warning','error','critical'];
$levelLabel = ['info'=>'Info','warning'=>'Atenção','error'=>'Erro','critical'=>'Crítico'];
$levelClass = ['info'=>'bg-info text-dark','warning'=>'bg-warning text-dark','error'=>'bg-danger','critical'=>'bg-dark'];

$dateFrom = $filters['date_from'] ?? date('Y-m-01');
$dateTo   = $filters['date_to']   ?? date('Y-m-d');
$headerActions = [];
if (($navigationContext['enabled'] ?? false) === true) {
    $headerActions[] = ['label' => $navigationContext['backLabel'] ?? 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => $navigationContext['backUrl'] ?? site_url('dashboard/admin')];
}
$headerActions[] = ['label' => 'Exportar CSV',     'icon' => 'bi bi-file-earmark-spreadsheet', 'url' => site_url('audit/export') . '?date_from=' . $dateFrom . '&date_to=' . $dateTo];
$headerActions[] = ['label' => 'Exportar AFD',     'icon' => 'bi bi-file-earmark-text',        'url' => site_url('audit/afd')    . '?date_from=' . $dateFrom . '&date_to=' . $dateTo];
?>

<div class="container-fluid">

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

    <!-- Estatísticas -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-primary"><?= number_format((int)($stats['total'] ?? 0)) ?></div>
                    <div class="small text-muted">Total de eventos</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-success"><?= number_format((int)($stats['today'] ?? 0)) ?></div>
                    <div class="small text-muted">Hoje</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-info"><?= number_format((int)($stats['this_week'] ?? 0)) ?></div>
                    <div class="small text-muted">Esta semana</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm text-center <?= ($stats['critical'] ?? 0) > 0 ? 'border-danger border-2 border' : '' ?>">
                <div class="card-body py-3">
                    <div class="fs-2 fw-bold text-danger"><?= number_format((int)($stats['critical'] ?? 0)) ?></div>
                    <div class="small text-muted">Críticos</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Insights -->
    <?php if (!empty($stats['active_users']) || !empty($stats['common_actions'])): ?>
    <div class="row g-3 mb-4">
        <?php if (!empty($stats['active_users'])): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pb-0 pt-3">
                    <h6 class="fw-semibold mb-0 small"><i class="bi bi-people-fill text-primary me-2"></i>Usuários mais ativos (7 dias)</h6>
                </div>
                <div class="card-body pt-2 pb-2">
                    <?php foreach ($stats['active_users'] as $u): ?>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="small"><?= esc($u['name'] ?? '-') ?></span>
                        <span class="badge bg-primary rounded-pill"><?= (int)($u['count'] ?? 0) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($stats['common_actions'])): ?>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent pb-0 pt-3">
                    <h6 class="fw-semibold mb-0 small"><i class="bi bi-lightning-fill text-warning me-2"></i>Ações mais frequentes (7 dias)</h6>
                </div>
                <div class="card-body pt-2 pb-2">
                    <?php foreach ($stats['common_actions'] as $a): ?>
                    <?php $act = is_object($a) ? $a->action : ($a['action'] ?? '-'); $cnt = is_object($a) ? $a->count : ($a['count'] ?? 0); ?>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <code class="small text-secondary"><?= esc($act) ?></code>
                        <span class="badge bg-secondary rounded-pill"><?= (int)$cnt ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Filtros + Tabela de eventos -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent border-0 pt-3 pb-0">
            <h6 class="fw-semibold mb-3"><i class="bi bi-funnel-fill text-secondary me-2"></i>Filtros</h6>
            <div class="row g-2 align-items-end mb-3">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label small mb-1">Busca livre</label>
                    <input type="text" id="f-search" class="form-control form-control-sm" placeholder="Palavra-chave, usuário, entidade...">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label small mb-1">Nível</label>
                    <select id="f-level" class="form-select form-select-sm">
                        <option value="">Todos os níveis</option>
                        <?php foreach ($levels as $lvl): ?>
                        <option value="<?= esc($lvl) ?>"><?= esc($levelLabel[$lvl] ?? $lvl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label small mb-1">Ação</label>
                    <select id="f-action" class="form-select form-select-sm">
                        <option value="">Todas as ações</option>
                        <?php foreach (($actions ?? []) as $a): ?>
                        <?php $av = is_array($a) ? ($a['action'] ?? '') : ($a->action ?? ''); ?>
                        <?php if ($av): ?><option value="<?= esc($av) ?>"><?= esc($av) ?></option><?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <label class="form-label small mb-1">De</label>
                    <input type="date" id="f-date-from" class="form-control form-control-sm" value="<?= esc($dateFrom) ?>">
                </div>
                <div class="col-sm-3 col-lg-2">
                    <label class="form-label small mb-1">Até</label>
                    <input type="date" id="f-date-to" class="form-control form-control-sm" value="<?= esc($dateTo) ?>">
                </div>
                <div class="col-auto">
                    <button id="btn-filter" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Filtrar
                    </button>
                    <button id="btn-clear" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="audit-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width:100px;">Nível</th>
                            <th style="width:180px;">Ação</th>
                            <th>Descrição</th>
                            <th style="width:150px;">Usuário</th>
                            <th style="width:140px;">Data/Hora</th>
                            <th style="width:50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="audit-tbody">
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2"></div>Carregando eventos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Paginação -->
            <div class="card-footer bg-transparent d-flex justify-content-between align-items-center py-2 px-3" id="audit-pagination-bar">
                <small class="text-muted" id="audit-info"></small>
                <div id="audit-pagination" class="d-flex gap-1 flex-wrap"></div>
            </div>
        </div>
    </div>

</div>

<script <?= csp_script_nonce_attr() ?>>
(function () {
    const ENDPOINT  = '<?= site_url('audit/data') ?>';
    const CSRF_NAME = '<?= csrf_token() ?>';
    let   csrfHash  = '<?= csrf_hash() ?>';
    let   currentPage = 0;
    const PAGE_SIZE   = 25;

    const levelBadge = {
        info:     '<span class="badge bg-info text-dark">Info</span>',
        warning:  '<span class="badge bg-warning text-dark">Atenção</span>',
        error:    '<span class="badge bg-danger">Erro</span>',
        critical: '<span class="badge bg-dark">Crítico</span>',
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function buildPayload(start) {
        return new URLSearchParams({
            [CSRF_NAME]:       csrfHash,
            draw:              1,
            start:             start,
            length:            PAGE_SIZE,
            'search[value]':   document.getElementById('f-search').value.trim(),
            filter_level:      document.getElementById('f-level').value,
            filter_action:     document.getElementById('f-action').value,
            filter_start_date: document.getElementById('f-date-from').value,
            filter_end_date:   document.getElementById('f-date-to').value,
        }).toString();
    }

    async function load(start) {
        start = start ?? 0;
        const tbody = document.getElementById('audit-tbody');
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Carregando...</td></tr>';

        try {
            const res  = await fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: buildPayload(start),
            });
            const json = await res.json();
            if (json.csrf_hash) csrfHash = json.csrf_hash;

            const data  = json.data  ?? [];
            const total = json.recordsFiltered ?? 0;

            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><i class="bi bi-shield-x fs-2 d-block mb-2 opacity-50"></i>Nenhum evento encontrado.</td></tr>';
                document.getElementById('audit-info').textContent = '';
                document.getElementById('audit-pagination').innerHTML = '';
                return;
            }

            tbody.innerHTML = data.map(r => {
                const lvl     = (r.level || 'info').toLowerCase();
                const badge   = levelBadge[lvl] || `<span class="badge bg-secondary">${esc(lvl)}</span>`;
                const detBtn  = r.id ? `<a href="<?= site_url('audit/') ?>${r.id}" class="btn btn-sm btn-outline-secondary" title="Detalhes"><i class="bi bi-eye"></i></a>` : '';
                const dt      = r.created_at || '-';
                const desc    = esc((r.description || '-').substring(0, 90)) + (r.description?.length > 90 ? '…' : '');
                return `<tr>
                    <td class="ps-3">${badge}</td>
                    <td><code class="small text-dark">${esc(r.action || '-')}</code></td>
                    <td class="small text-muted">${desc}</td>
                    <td class="small">${esc(r.user || r.employee_name || (r.user_id ? 'ID:' + r.user_id : 'Sistema'))}</td>
                    <td class="small text-muted">${dt}</td>
                    <td class="text-end pe-2">${detBtn}</td>
                </tr>`;
            }).join('');

            // Paginação
            const pages   = Math.ceil(total / PAGE_SIZE);
            const current = Math.floor(start / PAGE_SIZE);
            currentPage   = current;

            document.getElementById('audit-info').textContent =
                `Exibindo ${start + 1}–${Math.min(start + PAGE_SIZE, total)} de ${total} eventos`;

            const pag = document.getElementById('audit-pagination');
            pag.innerHTML = '';
            for (let i = 0; i < pages; i++) {
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm ' + (i === current ? 'btn-primary' : 'btn-outline-secondary');
                btn.textContent = i + 1;
                btn.onclick = () => load(i * PAGE_SIZE);
                pag.appendChild(btn);
                if (pages > 10 && i === 2 && current > 4) { pag.insertAdjacentHTML('beforeend','<span class="align-self-center px-1">…</span>'); i = Math.max(i, current - 2); }
                if (pages > 10 && i === current + 3 && i < pages - 1) { pag.insertAdjacentHTML('beforeend','<span class="align-self-center px-1">…</span>'); i = pages - 2; }
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Erro ao carregar eventos.</td></tr>';
        }
    }

    document.getElementById('btn-filter').addEventListener('click', () => load(0));
    document.getElementById('btn-clear').addEventListener('click', () => {
        ['f-search','f-level','f-action'].forEach(id => document.getElementById(id).value = '');
        document.getElementById('f-date-from').value = '<?= date('Y-m-01') ?>';
        document.getElementById('f-date-to').value   = '<?= date('Y-m-d') ?>';
        load(0);
    });
    ['f-level','f-action'].forEach(id => document.getElementById(id).addEventListener('change', () => load(0)));
    document.getElementById('f-search').addEventListener('keydown', e => { if (e.key === 'Enter') load(0); });

    load(0);
})();
</script>

<?= $this->endSection() ?>
