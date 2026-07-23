<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dependentes<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Dependentes',
        'subtitle' => 'Cadastro de dependentes dos colaboradores, para fins de IRRF, salário-família e eSocial.',
        'icon'     => 'bi bi-person-hearts',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('employees')],
            ['label' => 'Novo dependente', 'icon' => 'bi bi-plus-lg', 'url' => site_url('employees/dependents/create')],
        ],
    ]) ?>

    <!-- Aviso de toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
        <div id="sp-toast" class="toast align-items-center text-white border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body fw-semibold" id="sp-toast-msg"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>

    <div class="sp-card">
        <div class="sp-card-header">
            <h5 class="sp-card-title"><i class="bi bi-people"></i> Dependentes cadastrados</h5>
            <span class="text-muted small"><?= count($dependents ?? []) ?> registros</span>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label">Colaborador</label>
                    <select name="employee_id" class="form-select">
                        <option value="">Todos os colaboradores</option>
                        <?php foreach ($employees ?? [] as $emp): ?>
                            <option value="<?= esc($emp->id) ?>" <?= (int) ($employeeId ?? 0) === (int) $emp->id ? 'selected' : '' ?>>
                                <?= esc($emp->name) ?> — <?= esc($emp->department ?? '-') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= ($statusFilter ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                        <option value="active" <?= ($statusFilter ?? '') === 'active' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= ($statusFilter ?? '') === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="<?= site_url('employees/dependents') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>

            <div class="sp-table-container">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Colaborador</th>
                            <th>Parentesco</th>
                            <th>Nascimento</th>
                            <th class="text-center">Benefícios</th>
                            <th class="text-center">Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($dependents ?? [])): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="sp-empty">
                                        <div class="sp-empty-icon"><i class="bi bi-person-hearts"></i></div>
                                        <p class="sp-empty-title">Nenhum dependente cadastrado</p>
                                        <a href="<?= site_url('employees/dependents/create') ?>" class="sp-btn sp-btn-primary sp-btn-sm">
                                            <i class="bi bi-plus-lg"></i> Cadastrar dependente
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($dependents as $dep): ?>
                                <?php
                                    $depId = (int) ($dep->id ?? 0);
                                    $isActive = (bool) ($dep->active ?? false);
                                    $birth = !empty($dep->birth_date) ? date('d/m/Y', strtotime($dep->birth_date)) : '-';
                                ?>
                                <tr>
                                    <td><strong><?= esc($dep->name ?? '') ?></strong></td>
                                    <td><?= esc(mask_cpf($dep->cpf ?? '')) ?></td>
                                    <td><?= esc($dep->employee_name ?? '-') ?><br><small class="text-muted"><?= esc($dep->employee_department ?? '-') ?></small></td>
                                    <td><?= esc($kinshipLabels[$dep->kinship_type ?? ''] ?? ($dep->kinship_type ?? '-')) ?></td>
                                    <td><?= esc($birth) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($dep->irrf_dependent)): ?><span class="badge bg-info text-dark" title="Conta para IRRF">IRRF</span><?php endif; ?>
                                        <?php if (!empty($dep->family_allowance_dependent)): ?><span class="badge bg-primary" title="Conta para salário-família">Sal. Família</span><?php endif; ?>
                                        <?php if (!empty($dep->has_disability)): ?><span class="badge bg-warning text-dark" title="Possui deficiência">PCD</span><?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="sp-badge <?= $isActive ? 'sp-badge-success' : 'sp-badge-danger' ?>">
                                            <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i>
                                            <?= $isActive ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-icon-actions">
                                            <a href="<?= site_url('employees/dependents/' . $depId . '/edit') ?>"
                                               class="icon-action icon-action-edit" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <button type="button" class="icon-action icon-action-danger" title="Excluir"
                                                    onclick="confirmDeleteDependent(<?= $depId ?>, '<?= esc(addslashes($dep->name ?? ''), 'js') ?>')">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal confirmação de exclusão -->
<div class="modal fade" id="deleteDependentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar exclusão</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o dependente <strong id="deleteDependentName"></strong>?</p>
                <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Esta ação não pode ser desfeita.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteDependentForm" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" id="deleteDependentBtn">
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
function toast(msg, type) {
    type = type || 'success';
    const el = document.getElementById('sp-toast');
    const msgEl = document.getElementById('sp-toast-msg');
    el.className = 'toast align-items-center text-white border-0 bg-' + type;
    msgEl.textContent = msg;
    bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
}

function confirmDeleteDependent(id, name) {
    document.getElementById('deleteDependentName').textContent = name;
    document.getElementById('deleteDependentForm').action = '<?= site_url('employees/dependents/') ?>' + id + '/delete';

    const btn = document.getElementById('deleteDependentBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-trash-fill me-2"></i>Excluir';

    document.getElementById('deleteDependentForm').onsubmit = async function(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Excluindo...';
        try {
            const fd = new FormData(this);
            const resp = await fetch(this.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await resp.json();
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteDependentModal'));
            modal.hide();
            if (json.success) {
                location.reload();
            } else {
                toast(json.message || 'Erro ao excluir.', 'danger');
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash-fill me-2"></i>Excluir';
            toast('Erro de comunicação com o servidor.', 'danger');
        }
    };

    new bootstrap.Modal(document.getElementById('deleteDependentModal')).show();
}
</script>
<?= $this->endSection() ?>
