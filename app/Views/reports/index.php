<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Relatórios<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $defaultMonth = date('Y-m');
    $selectedEmployeeId = $selectedEmployeeId ?? request()->getGet('employee_id');
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Relatórios e exportações',
        'subtitle' => 'Gere relatórios operacionais, exporte arquivos e solicite o AFD com filtros de período.',
        'icon'     => 'bi bi-bar-chart-fill',
        'actions'  => [
                                ],
    ]) ?>

    <!-- Atalhos de relatórios pré-definidos -->
    <div class="sp-shortcuts-grid mb-4">
        <a class="sp-shortcut-card" href="<?= route_to('reports.timesheet') ?>?month=<?= esc($defaultMonth) ?><?= $selectedEmployeeId ? '&employee_id=' . urlencode((string) $selectedEmployeeId) : '' ?>">
            <div class="icon" style="background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-calendar-range"></i></div>
            <strong>Espelho consolidado</strong>
            <span>Relatório mensal por colaborador com jornada prevista, saldo e registros diários.</span>
        </a>
        <a class="sp-shortcut-card" href="<?= route_to('reports.attendance') ?>?month=<?= esc($defaultMonth) ?>">
            <div class="icon" style="background:rgba(25,135,84,.12);color:#198754;"><i class="bi bi-graph-up-arrow"></i></div>
            <strong>Assiduidade</strong>
            <span>Compare horas trabalhadas, faltas operacionais e taxa de presença por período.</span>
        </a>
        <a class="sp-shortcut-card" href="<?= route_to('reports.justifications') ?>?month=<?= esc($defaultMonth) ?>">
            <div class="icon" style="background:rgba(13,202,240,.12);color:#0dcaf0;"><i class="bi bi-file-earmark-text"></i></div>
            <strong>Justificativas</strong>
            <span>Monitore aprovações, pendências e histórico de solicitações do período.</span>
        </a>
        <a class="sp-shortcut-card" href="<?= route_to('reports.late_arrivals') ?>?month=<?= esc($defaultMonth) ?>">
            <div class="icon" style="background:rgba(253,126,20,.12);color:#fd7e14;"><i class="bi bi-alarm"></i></div>
            <strong>Atrasos</strong>
            <span>Veja colaboradores com atrasos recorrentes e datas impactadas no mês.</span>
        </a>
    </div>

    <div class="row g-4">

        <!-- Relatório customizado -->
        <div class="col-12 col-xl-7">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-sliders"></i></span>
                        Relatório customizado
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <form id="form-report" action="<?= route_to('reports.generate') ?>" method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label for="report-type" class="form-label">Tipo de relatório</label>
                            <select name="type" id="report-type" class="form-select" required>
                                <option value="folha-ponto">Folha de ponto</option>
                                <option value="faltas-atrasos">Faltas e atrasos</option>
                                <option value="justificativas">Justificativas</option>
                                <option value="banco-horas">Banco de horas</option>
                                <option value="consolidado-mensal">Consolidado mensal</option>
                                <option value="advertencias">Advertências</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="report-format" class="form-label">Formato</label>
                            <select name="format" id="report-format" class="form-select" required>
                                <option value="html">Visualizar no navegador</option>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                                <option value="txt">TXT</option>
                                <option value="xml">XML</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="filters-start-date" class="form-label">Data inicial</label>
                            <input type="date" name="filters[start_date]" id="filters-start-date" class="form-control"
                                   value="<?= esc($defaultMonth . '-01') ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filters-end-date" class="form-label">Data final</label>
                            <input type="date" name="filters[end_date]" id="filters-end-date" class="form-control"
                                   value="<?= esc(date('Y-m-t', strtotime($defaultMonth . '-01'))) ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="filters-department" class="form-label">Departamento</label>
                            <select name="filters[department]" id="filters-department" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach (($departments ?? []) as $dept): ?>
                                    <?php $deptName = is_object($dept) ? $dept->name : (string) $dept; ?>
                                    <option value="<?= esc($deptName) ?>"><?= esc($deptName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" id="btn-generate" class="btn btn-primary">
                                <i class="bi bi-gear-wide-connected me-1"></i>Gerar relatório
                            </button>
                        </div>
                    </form>

                    <!-- Área de resultado inline (html) -->
                    <div id="report-result" class="mt-4" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong id="report-result-title" class="text-muted"></strong>
                            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('report-result').style.display='none'">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                            <table class="table table-sm table-hover align-middle" id="report-table">
                                <thead class="table-light sticky-top" id="report-thead"></thead>
                                <tbody id="report-tbody"></tbody>
                            </table>
                        </div>
                        <p class="text-muted small mt-1" id="report-count"></p>
                    </div>

                    <!-- Progresso de job assíncrono -->
                    <div id="report-async" class="mt-4" style="display:none;">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span id="report-async-msg">Aguardando processamento…</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div id="report-async-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 style="width:0%"></div>
                        </div>
                        <p class="text-muted small mt-2" id="report-async-status"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exportação AFD -->
        <div class="col-12 col-xl-5" id="afd-export">
            <div class="sp-data-card h-100">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(25,135,84,.12);color:#198754;"><i class="bi bi-filetype-txt"></i></span>
                        Exportação AFD
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <p class="text-muted">Informe o período de apuração para gerar o arquivo AFD (Arquivo de Fonte de Dados).</p>
                    <form id="form-afd" action="<?= route_to('reports.afd') ?>" method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-6">
                            <label for="afd-start-date" class="form-label">Data inicial</label>
                            <input type="date" name="filters[start_date]" id="afd-start-date" class="form-control"
                                   value="<?= esc($defaultMonth . '-01') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="afd-end-date" class="form-label">Data final</label>
                            <input type="date" name="filters[end_date]" id="afd-end-date" class="form-control"
                                   value="<?= esc(date('Y-m-t', strtotime($defaultMonth . '-01'))) ?>" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" id="btn-afd" class="btn btn-outline-primary">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i>Solicitar AFD
                            </button>
                        </div>
                    </form>

                    <!-- Progresso AFD -->
                    <div id="afd-async" class="mt-3" style="display:none;">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span id="afd-async-msg">Gerando AFD…</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div id="afd-async-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                 style="width:0%"></div>
                        </div>
                        <p class="text-muted small mt-2" id="afd-async-status"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Relatório por funcionário -->
    <div class="row g-4 mt-0">
        <div class="col-12">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(111,66,193,.12);color:#6f42c1;"><i class="bi bi-person-lines-fill"></i></span>
                        Relatório por funcionário
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <form id="form-employee-report" action="<?= route_to('reports.generate') ?>" method="post" class="row g-3">
                        <?= csrf_field() ?>
                        <div class="col-md-4">
                            <label for="emp-select" class="form-label">Funcionário <span class="text-danger">*</span></label>
                            <select name="filters[employee_id]" id="emp-select" class="form-select" required>
                                <option value="">Selecione…</option>
                                <?php foreach (($employees ?? []) as $emp): ?>
                                    <option value="<?= (int) $emp->id ?>">
                                        <?= esc($emp->name) ?><?= !empty($emp->department) ? ' — ' . esc($emp->department) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="emp-report-type" class="form-label">Tipo</label>
                            <select name="type" id="emp-report-type" class="form-select">
                                <option value="folha-ponto">Folha de ponto</option>
                                <option value="faltas-atrasos">Faltas e atrasos</option>
                                <option value="justificativas">Justificativas</option>
                                <option value="banco-horas">Banco de horas</option>
                                <option value="consolidado-mensal">Consolidado mensal</option>
                                <option value="advertencias">Advertências</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="emp-report-format" class="form-label">Formato</label>
                            <select name="format" id="emp-report-format" class="form-select">
                                <option value="html">Visualizar</option>
                                <option value="pdf">PDF</option>
                                <option value="excel">Excel</option>
                                <option value="csv">CSV</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="emp-start-date" class="form-label">Data inicial</label>
                            <input type="date" name="filters[start_date]" id="emp-start-date" class="form-control"
                                   value="<?= esc($defaultMonth . '-01') ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="emp-end-date" class="form-label">Data final</label>
                            <input type="date" name="filters[end_date]" id="emp-end-date" class="form-control"
                                   value="<?= esc(date('Y-m-t', strtotime($defaultMonth . '-01'))) ?>">
                        </div>
                        <div class="col-12 d-flex gap-2 align-items-center flex-wrap">
                            <button type="submit" id="btn-emp-report" class="btn btn-primary">
                                <i class="bi bi-person-check-fill me-1"></i>Gerar por funcionário
                            </button>
                            <a id="emp-timesheet-link" href="#" class="btn btn-outline-secondary" style="display:none;" target="_blank">
                                <i class="bi bi-calendar-range me-1"></i>Ver espelho detalhado
                            </a>
                        </div>
                    </form>

                    <!-- Resultado inline -->
                    <div id="emp-report-result" class="mt-4" style="display:none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong id="emp-report-title" class="text-muted"></strong>
                            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('emp-report-result').style.display='none'">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="table-responsive" style="max-height:420px;overflow-y:auto;">
                            <table class="table table-sm table-hover align-middle">
                                <thead class="table-light sticky-top" id="emp-report-thead"></thead>
                                <tbody id="emp-report-tbody"></tbody>
                            </table>
                        </div>
                        <p class="text-muted small mt-1" id="emp-report-count"></p>
                    </div>

                    <!-- Progresso async -->
                    <div id="emp-report-async" class="mt-4" style="display:none;">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span id="emp-async-msg">Gerando…</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div id="emp-async-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                 style="width:0%"></div>
                        </div>
                        <p class="text-muted small mt-2" id="emp-async-status"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    // ── Helpers ──────────────────────────────────────────────────────────────

    function esc(s) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(s ?? '')));
        return d.innerHTML;
    }

    function fmtDecimalHours(val) {
        if (val === null || val === undefined || val === '') return '-';
        const h = Math.floor(Math.abs(val));
        const m = Math.round((Math.abs(val) - h) * 60);
        const sign = val < 0 ? '-' : '';
        return sign + h + 'h' + String(m).padStart(2, '0') + 'm';
    }

    function fmtDate(s) {
        if (!s) return '-';
        const d = s.split(/[T ]/)[0];
        if (!d || d.length < 10) return s;
        const [y, mo, dd] = d.split('-');
        return dd + '/' + mo + '/' + y;
    }

    function fmtBool(v) { return v ? 'Sim' : 'Não'; }

    function setBtn(id, loading, orig) {
        const btn = document.getElementById(id);
        if (!btn) return;
        if (loading) {
            btn._orig = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Aguarde…';
        } else {
            btn.disabled = false;
            btn.innerHTML = orig || btn._orig || btn.innerHTML;
        }
    }

    // Fetch com timeout via AbortController (evita botão travado indefinidamente)
    async function fetchWithTimeout(url, options, timeoutMs) {
        timeoutMs = timeoutMs || 45000;
        const ctrl = new AbortController();
        const timer = setTimeout(function () { ctrl.abort(); }, timeoutMs);
        try {
            const resp = await spFetch(url, Object.assign({}, options, { signal: ctrl.signal }));
            clearTimeout(timer);
            return resp;
        } catch (err) {
            clearTimeout(timer);
            if (err.name === 'AbortError') {
                throw new Error('Tempo limite excedido (45s). Tente um período menor ou formato HTML.');
            }
            throw err;
        }
    }

    // ── Column definitions per report type ───────────────────────────────────

    const COLS = {
        'folha-ponto': [
            { key: 'employee_name', label: 'Colaborador' },
            { key: 'department',    label: 'Depto' },
            { key: 'date',          label: 'Data',     fmt: fmtDate },
            { key: 'total_worked',  label: 'Trabalhado', fmt: fmtDecimalHours },
            { key: 'expected',      label: 'Previsto',   fmt: fmtDecimalHours },
            { key: 'extra',         label: 'Extras',     fmt: fmtDecimalHours },
            { key: 'owed',          label: 'Débito',     fmt: fmtDecimalHours },
        ],
        'faltas-atrasos': [
            { key: 'date',           label: 'Data',      fmt: fmtDate },
            { key: 'employee_name',  label: 'Colaborador' },
            { key: 'department',     label: 'Depto' },
            { key: 'type',           label: 'Tipo' },
            { key: 'delay_minutes',  label: 'Atraso (min)' },
            { key: 'justified',      label: 'Justificado', fmt: fmtBool },
        ],
        'justificativas': [
            { key: 'justification_date', label: 'Data',       fmt: fmtDate },
            { key: 'employee_name',      label: 'Colaborador' },
            { key: 'justification_type', label: 'Tipo' },
            { key: 'category',           label: 'Categoria' },
            { key: 'status',             label: 'Status' },
            { key: 'reason',             label: 'Motivo' },
        ],
        'banco-horas': [
            { key: 'employee_name',       label: 'Colaborador' },
            { key: 'department',          label: 'Depto' },
            { key: 'extra_hours_balance', label: 'Saldo extras',  fmt: fmtDecimalHours },
            { key: 'owed_hours_balance',  label: 'Saldo débitos', fmt: fmtDecimalHours },
        ],
        'consolidado-mensal': [
            { key: 'employee_name',  label: 'Colaborador' },
            { key: 'department',     label: 'Depto' },
            { key: 'days_worked',    label: 'Dias' },
            { key: 'total_worked',   label: 'Trabalhado',  fmt: fmtDecimalHours },
            { key: 'total_expected', label: 'Previsto',    fmt: fmtDecimalHours },
            { key: 'extra',          label: 'Extras',      fmt: fmtDecimalHours },
            { key: 'owed',           label: 'Débito',      fmt: fmtDecimalHours },
            { key: 'late_count',     label: 'Atrasos' },
            { key: 'absence_count',  label: 'Faltas' },
        ],
        'horas-extras': [
            { key: 'employee_name', label: 'Colaborador' },
            { key: 'department',    label: 'Depto' },
            { key: 'date',          label: 'Data',       fmt: fmtDate },
            { key: 'extra',         label: 'Extras',     fmt: fmtDecimalHours },
            { key: 'is_weekend',    label: 'Fim de semana', fmt: fmtBool },
        ],
        'advertencias': [
            { key: 'occurrence_date', label: 'Data',        fmt: fmtDate },
            { key: 'employee_name',   label: 'Colaborador' },
            { key: 'department',      label: 'Depto' },
            { key: 'warning_type',    label: 'Tipo' },
            { key: 'reason',          label: 'Motivo' },
            { key: 'status',          label: 'Status' },
        ],
    };

    function colsFor(type) {
        return COLS[type] || null;
    }

    // ── Render HTML table ─────────────────────────────────────────────────────

    function renderTable(type, data) {
        const cols = colsFor(type);
        const thead = document.getElementById('report-thead');
        const tbody = document.getElementById('report-tbody');
        const title = document.getElementById('report-result-title');
        const count = document.getElementById('report-count');

        if (!data || data.length === 0) {
            document.getElementById('report-result').style.display = 'block';
            thead.innerHTML = '';
            tbody.innerHTML = '<tr><td class="text-center text-muted py-3" colspan="99">Nenhum registro encontrado para o período selecionado.</td></tr>';
            title.textContent = 'Sem resultados';
            count.textContent = '';
            return;
        }

        // Build columns from definition or from first row keys
        const activeCols = cols || Object.keys(data[0]).map(k => ({ key: k, label: k }));

        thead.innerHTML = '<tr>' + activeCols.map(c => '<th class="text-nowrap">' + esc(c.label) + '</th>').join('') + '</tr>';

        tbody.innerHTML = data.map(row => {
            const r = typeof row === 'object' && row !== null ? row : {};
            return '<tr>' + activeCols.map(c => {
                const raw = r[c.key];
                const formatted = c.fmt ? c.fmt(raw) : (raw !== null && raw !== undefined ? esc(String(raw)) : '-');
                return '<td>' + formatted + '</td>';
            }).join('') + '</tr>';
        }).join('');

        const typeLabels = {
            'folha-ponto': 'Folha de ponto',
            'faltas-atrasos': 'Faltas e atrasos',
            'justificativas': 'Justificativas',
            'banco-horas': 'Banco de horas',
            'consolidado-mensal': 'Consolidado mensal',
            'horas-extras': 'Horas extras',
            'advertencias': 'Advertências',
        };
        title.textContent = (typeLabels[type] || type) + ' — ' + data.length + ' registro' + (data.length !== 1 ? 's' : '');
        count.textContent = data.length + ' registro' + (data.length !== 1 ? 's' : '') + ' encontrado' + (data.length !== 1 ? 's' : '');
        document.getElementById('report-result').style.display = 'block';
    }

    // ── Async job polling ─────────────────────────────────────────────────────

    function pollJob(jobId, statusUrl, downloadUrl, msgEl, barEl, statusEl, asyncEl, onDone) {
        let attempts = 0;
        const MAX = 90; // 3 min max (2s interval)
        let prog = 5;
        barEl.style.width = prog + '%';

        function finish(hideAsync) {
            if (hideAsync) asyncEl.style.display = 'none';
            if (onDone) { onDone(); onDone = null; }
        }

        const interval = setInterval(async function () {
            attempts++;

            if (attempts > MAX) {
                clearInterval(interval);
                barEl.classList.remove('progress-bar-animated');
                msgEl.textContent = 'Tempo esgotado. Tente novamente.';
                statusEl.textContent = '';
                finish(false);
                return;
            }

            try {
                const r = await spFetch(statusUrl);
                const j = await r.json();
                const st = (j.status || '').toLowerCase();

                prog = Math.min(88, prog + 3);
                barEl.style.width = prog + '%';

                if (st === 'completed') {
                    clearInterval(interval);
                    barEl.style.width = '100%';
                    barEl.classList.remove('progress-bar-animated');
                    msgEl.textContent = 'Pronto! Iniciando download…';
                    statusEl.textContent = '';
                    setTimeout(function () {
                        finish(true);
                        window.location.href = downloadUrl;
                    }, 600);

                } else if (st === 'failed') {
                    clearInterval(interval);
                    barEl.classList.remove('progress-bar-animated');
                    barEl.classList.add('bg-danger');
                    msgEl.textContent = 'Erro: ' + (j.error || 'falha ao gerar o arquivo');
                    statusEl.textContent = 'Tente novamente com outro período.';
                    finish(false);

                } else {
                    const pct = j.progress || 0;
                    if (pct > 0) barEl.style.width = Math.min(88, pct) + '%';
                    statusEl.textContent = 'Aguardando worker… (' + attempts + 's)';
                }
            } catch (e) {
                statusEl.textContent = 'Verificando conectividade…';
            }
        }, 2000);
    }

    // ── Custom report form ────────────────────────────────────────────────────

    document.getElementById('form-report').addEventListener('submit', async function (e) {
        e.preventDefault();

        const type   = document.getElementById('report-type').value;
        const format = document.getElementById('report-format').value;

        setBtn('btn-generate', true);

        // Hide previous results
        document.getElementById('report-result').style.display  = 'none';
        document.getElementById('report-async').style.display   = 'none';

        let j = null;
        try {
            const fd = new FormData(this);
            const resp = await fetchWithTimeout(this.action, { method: 'POST', body: fd }, 45000);
            j = await resp.json();
        } catch (err) {
            alert('Erro de comunicação com o servidor.');
            return;
        } finally {
            setBtn('btn-generate', false);
        }

        if (!j || !j.success) {
            alert((j && (j.error || j.message)) || 'Erro ao gerar relatório.');
            return;
        }

        // Async job (pdf, excel, csv, xml, txt)
        if (j.queued) {
            const asyncEl  = document.getElementById('report-async');
            const msgEl    = document.getElementById('report-async-msg');
            const barEl    = document.getElementById('report-async-bar');
            const statusEl = document.getElementById('report-async-status');

            asyncEl.style.display = 'block';
            msgEl.textContent = 'Relatório enfileirado. Aguardando processamento…';
            barEl.style.width = '5%';
            statusEl.textContent = '';

            pollJob(j.job_id, j.status_url, j.download_url, msgEl, barEl, statusEl, asyncEl, null);
            return;
        }

        // HTML sync result
        const rows = Array.isArray(j.data) ? j.data : (j.data && Array.isArray(j.data.data) ? j.data.data : null);
        if (rows !== null) {
            renderTable(type, rows);
        } else {
            alert('Relatório gerado, mas sem dados para exibir.');
        }
    });

    // ── AFD form ──────────────────────────────────────────────────────────────

    document.getElementById('form-afd').addEventListener('submit', async function (e) {
        e.preventDefault();

        setBtn('btn-afd', true);
        document.getElementById('afd-async').style.display = 'none';

        let j = null;
        try {
            const fd = new FormData(this);
            const resp = await fetchWithTimeout(this.action, { method: 'POST', body: fd }, 45000);
            j = await resp.json();
        } catch (err) {
            alert('Erro de comunicação com o servidor.');
            return;
        } finally {
            setBtn('btn-afd', false);
        }

        if (!j || !j.success) {
            alert((j && (j.error || j.message)) || 'Erro ao solicitar AFD. Verifique o período informado.');
            return;
        }

        if (j.queued) {
            const asyncEl  = document.getElementById('afd-async');
            const msgEl    = document.getElementById('afd-async-msg');
            const barEl    = document.getElementById('afd-async-bar');
            const statusEl = document.getElementById('afd-async-status');

            asyncEl.style.display = 'block';
            msgEl.textContent = 'AFD enfileirado. Aguardando geração…';
            barEl.style.width = '5%';
            statusEl.textContent = '';

            pollJob(j.job_id, j.status_url, j.download_url, msgEl, barEl, statusEl, asyncEl, null);
        } else {
            alert(j.message || 'AFD solicitado.');
        }
    });


    // ── Employee report form ──────────────────────────────────────────────────

    // Update "Ver espelho detalhado" link when employee changes
    document.getElementById('emp-select').addEventListener('change', function () {
        const link = document.getElementById('emp-timesheet-link');
        const empId = this.value;
        if (empId) {
            const month = document.getElementById('emp-start-date').value.substring(0, 7);
            link.href = '<?= route_to("reports.timesheet") ?>?employee_id=' + empId + '&month=' + (month || '<?= esc($defaultMonth) ?>');
            link.style.display = 'inline-flex';
        } else {
            link.style.display = 'none';
        }
    });

    document.getElementById('emp-start-date').addEventListener('change', function () {
        const empId = document.getElementById('emp-select').value;
        const link = document.getElementById('emp-timesheet-link');
        if (empId && link.style.display !== 'none') {
            const month = this.value.substring(0, 7);
            link.href = '<?= route_to("reports.timesheet") ?>?employee_id=' + empId + '&month=' + (month || '<?= esc($defaultMonth) ?>');
        }
    });

    document.getElementById('form-employee-report').addEventListener('submit', async function (e) {
        e.preventDefault();

        const empId = document.getElementById('emp-select').value;
        if (!empId) { alert('Selecione um funcionário.'); return; }

        const type   = document.getElementById('emp-report-type').value;
        const format = document.getElementById('emp-report-format').value;

        setBtn('btn-emp-report', true);
        document.getElementById('emp-report-result').style.display = 'none';
        document.getElementById('emp-report-async').style.display  = 'none';

        let j = null;
        try {
            const fd2 = new FormData(this);
            const resp = await fetchWithTimeout(this.action, { method: 'POST', body: fd2 }, 45000);
            j = await resp.json();
        } catch (err) {
            alert('Erro de comunicação com o servidor.');
            return;
        } finally {
            setBtn('btn-emp-report', false);
        }

        if (!j || !j.success) {
            alert((j && (j.error || j.message)) || 'Erro ao gerar relatório.');
            return;
        }

        // Async job
        if (j.queued) {
            const asyncEl  = document.getElementById('emp-report-async');
            const msgEl    = document.getElementById('emp-async-msg');
            const barEl    = document.getElementById('emp-async-bar');
            const statusEl = document.getElementById('emp-async-status');
            asyncEl.style.display = 'block';
            msgEl.textContent = 'Relatório enfileirado. Aguardando…';
            barEl.style.width = '5%';
            statusEl.textContent = '';
            pollJob(j.job_id, j.status_url, j.download_url, msgEl, barEl, statusEl, asyncEl, null);
            return;
        }

        // HTML table
        const empName = document.getElementById('emp-select').selectedOptions[0]?.text || '';
        const data = Array.isArray(j.data) ? j.data : (j.data && Array.isArray(j.data.data) ? j.data.data : null);

        if (data !== null) {
            const thead = document.getElementById('emp-report-thead');
            const tbody = document.getElementById('emp-report-tbody');
            const title = document.getElementById('emp-report-title');
            const count = document.getElementById('emp-report-count');
            const resultEl = document.getElementById('emp-report-result');

            // Reuse column definitions
            const cols = colsFor(type);

            if (!data || data.length === 0) {
                thead.innerHTML = '';
                tbody.innerHTML = '<tr><td class="text-center text-muted py-3" colspan="99">Nenhum registro encontrado para o período selecionado.</td></tr>';
                title.textContent = empName + ' — sem dados';
                count.textContent = '';
            } else {
                const activeCols = cols || Object.keys(data[0]).map(k => ({ key: k, label: k }));
                thead.innerHTML = '<tr>' + activeCols.map(c => '<th class="text-nowrap">' + esc(c.label) + '</th>').join('') + '</tr>';
                tbody.innerHTML = data.map(row => {
                    const r = typeof row === 'object' && row !== null ? row : {};
                    return '<tr>' + activeCols.map(c => {
                        const raw = r[c.key];
                        const formatted = c.fmt ? c.fmt(raw) : (raw !== null && raw !== undefined ? esc(String(raw)) : '-');
                        return '<td>' + formatted + '</td>';
                    }).join('') + '</tr>';
                }).join('');
                title.textContent = empName + ' — ' + data.length + ' registro' + (data.length !== 1 ? 's' : '');
                count.textContent = data.length + ' registro' + (data.length !== 1 ? 's' : '') + ' encontrado' + (data.length !== 1 ? 's' : '');
            }
            resultEl.style.display = 'block';
        } else {
            alert('Relatório gerado sem dados para exibir.');
        }
    });

})();
</script>
<?= $this->endSection() ?>
