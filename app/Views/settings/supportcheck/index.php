<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>SupportCHECK - Integracao<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'SupportCHECK',
        'subtitle' => 'Integracao com o sistema de gestao documental.',
        'icon'     => 'bi bi-plug-fill',
        'actions'  => [
            ['label' => 'Configuracoes', 'icon' => 'bi bi-sliders', 'url' => route_to('settings')],
        ],
    ]) ?>

    <div id="sc-status-bar" class="alert d-flex align-items-center gap-2 mb-3 <?= $enabled ? 'alert-light border' : 'alert-warning' ?>">
        <?php if (! $enabled): ?>
            <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
            <div>Integracao SupportCHECK <strong>desabilitada</strong>. Verifique <code>SUPPORTCHECK_ENABLED</code>, <code>SUPPORTCHECK_BASE_URL</code> e <code>SUPPORTCHECK_API_TOKEN</code> no <code>.env</code>.</div>
        <?php else: ?>
            <span id="sc-status-icon" class="spinner-border spinner-border-sm text-secondary" role="status" aria-hidden="true"></span>
            <div id="sc-status-text" class="text-muted">Verificando conexao com <strong><?= esc($base_url) ?></strong>...</div>
        <?php endif; ?>
    </div>

    <div id="sc-feedback" class="alert d-none mb-3" role="alert"></div>

    <div class="row g-3">

        <div class="col-md-6">
            <div class="sp-card h-100">
                <div class="sp-card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Enviar Relatorio de Ponto</h5>
                </div>
                <div class="sp-card-body">
                    <p class="text-muted small">Gera e envia o relatorio mensal de ponto para o SupportCHECK. O envio automatico ocorre no dia 1 de cada mes as 02:00.</p>
                    <form id="form-send-report">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="sr-year">Ano</label>
                                <select id="sr-year" name="year" class="form-select form-select-sm" required>
                                    <?php for ($y = date('Y'); $y >= date('Y') - 3; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="sr-month">Mes</label>
                                <select id="sr-month" name="month" class="form-select form-select-sm" required>
                                    <?php foreach (['1'=>'Janeiro','2'=>'Fevereiro','3'=>'Marco','4'=>'Abril','5'=>'Maio','6'=>'Junho','7'=>'Julho','8'=>'Agosto','9'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'] as $n => $m): ?>
                                    <option value="<?= $n ?>" <?= $n == date('n', strtotime('first day of last month')) ? 'selected' : '' ?>><?= $m ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="sr-employee">
                                    Funcionario <span class="text-muted fw-normal">(opcional - vazio = todos)</span>
                                </label>
                                <select id="sr-employee" name="employee_id" class="form-select form-select-sm">
                                    <option value="">Todos os funcionarios ativos</option>
                                    <?php foreach ($employees as $emp): ?>
                                    <option value="<?= esc((string) ($emp->id ?? '')) ?>"><?= esc($emp->name ?? '') ?></option>
                                    <?php endforeach ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-send-report" <?= ! $enabled ? 'disabled' : '' ?>>
                            <span id="btn-send-label"><i class="bi bi-send me-1"></i>Enviar agora</span>
                            <span id="btn-send-spinner" class="d-none"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Enviando...</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="sp-card h-100">
                <div class="sp-card-header">
                    <h5 class="mb-0"><i class="bi bi-arrow-repeat me-2"></i>Sincronizar Estrutura e Funcionarios</h5>
                </div>
                <div class="sp-card-body">
                    <p class="text-muted small">Reenvia empresa, cargos, departamentos, unidades e todos os funcionarios ativos para o SupportCHECK.</p>
                    <button type="button" class="btn btn-secondary btn-sm" id="btn-sync-all" <?= ! $enabled ? 'disabled' : '' ?>>
                        <span id="btn-sync-label"><i class="bi bi-arrow-repeat me-1"></i>Sincronizar tudo</span>
                        <span id="btn-sync-spinner" class="d-none"><span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Sincronizando...</span>
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

function showFeedback(success, message, details) {
    details = details || [];
    const el = document.getElementById('sc-feedback');
    el.className = 'alert ' + (success ? 'alert-success' : 'alert-danger');
    const icon = success ? '<i class="bi bi-check-circle-fill me-1"></i>' : '<i class="bi bi-x-circle-fill me-1"></i>';
    el.innerHTML = '<p class="mb-0">' + icon + message + '</p>';
    if (details.length > 0) {
        const ul = document.createElement('ul');
        ul.className = 'mt-2 mb-0';
        details.forEach(function(d) { const li = document.createElement('li'); li.textContent = d; ul.appendChild(li); });
        el.appendChild(ul);
    }
    el.classList.remove('d-none');
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function setLoading(btnId, labelId, spinnerId, loading) {
    document.getElementById(btnId).disabled = loading;
    document.getElementById(labelId).classList.toggle('d-none', loading);
    document.getElementById(spinnerId).classList.toggle('d-none', !loading);
}

<?php if ($enabled): ?>
(async function checkStatus() {
    try {
        const res  = await fetch('<?= route_to('settings.supportcheck.ping') ?>');
        const data = await res.json();
        const bar  = document.getElementById('sc-status-bar');
        const icon = document.getElementById('sc-status-icon');
        const text = document.getElementById('sc-status-text');
        if (data.online) {
            bar.className = 'alert alert-success d-flex align-items-center gap-2 mb-3';
            icon.outerHTML = '<i class="bi bi-check-circle-fill text-success fs-5" id="sc-status-icon"></i>';
            text.innerHTML = 'Conectado a <strong><?= esc($base_url) ?></strong> &mdash; verificado as ' + data.checked_at;
        } else {
            bar.className = 'alert alert-danger d-flex align-items-center gap-2 mb-3';
            icon.outerHTML = '<i class="bi bi-x-circle-fill text-danger fs-5" id="sc-status-icon"></i>';
            text.innerHTML = 'Sem resposta de <strong><?= esc($base_url) ?></strong>. Verifique a URL e o token. Verificado as ' + data.checked_at;
        }
    } catch(e) {
        const bar = document.getElementById('sc-status-bar');
        bar.className = 'alert alert-warning d-flex align-items-center gap-2 mb-3';
        document.getElementById('sc-status-text').textContent = 'Nao foi possivel verificar o status da conexao.';
    }
})();
<?php endif; ?>

document.getElementById('form-send-report').addEventListener('submit', async function(e) {
    e.preventDefault();
    setLoading('btn-send-report', 'btn-send-label', 'btn-send-spinner', true);
    const fd = new FormData(this);
    try {
        const res  = await fetch('<?= route_to('settings.supportcheck.send-report') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
        });
        const data = await res.json();
        const details = [];
        if (data.data && Array.isArray(data.data.errors) && data.data.errors.length > 0) {
            data.data.errors.forEach(function(err) { details.push('Falha: ' + err); });
        }
        showFeedback(data.success !== undefined ? data.success : res.ok, data.message || (res.ok ? 'Enviado com sucesso.' : 'Erro ao enviar.'), details);
    } catch(err) {
        showFeedback(false, 'Erro de comunicacao: ' + err.message);
    } finally {
        setLoading('btn-send-report', 'btn-send-label', 'btn-send-spinner', false);
    }
});

document.getElementById('btn-sync-all').addEventListener('click', async function() {
    setLoading('btn-sync-all', 'btn-sync-label', 'btn-sync-spinner', true);
    try {
        const res  = await fetch('<?= route_to('settings.supportcheck.sync-all') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken },
        });
        const data = await res.json();
        const details = [];
        if (data.data && data.data.employees) {
            const emp = data.data.employees;
            if (emp.sent !== undefined) details.push('Funcionarios: ' + emp.sent + ' enviados, ' + (emp.failed || 0) + ' falhas');
        }
        showFeedback(data.success !== undefined ? data.success : res.ok, data.message || (res.ok ? 'Sincronizacao concluida.' : 'Erro na sincronizacao.'), details);
    } catch(err) {
        showFeedback(false, 'Erro de comunicacao: ' + err.message);
    } finally {
        setLoading('btn-sync-all', 'btn-sync-label', 'btn-sync-spinner', false);
    }
});
</script>
<?= $this->endSection() ?>
