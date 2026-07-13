<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Níveis de Acesso<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-shield-fill-check me-2 text-primary"></i>Níveis de Acesso</h1>
            <p class="text-muted mb-0">Gerencie perfis de acesso e suas permissões no sistema.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= sp_route_url('settings.roles.create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-2"></i>Novo Nível
            </a>
        </div>
    </div>


    <div class="card shadow-sm">
        <div class="card-body">

            <form method="get" class="row g-3 align-items-end mb-3">
                <div class="col-md-5">
                    <label class="form-label">Buscar</label>
                    <input type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>"
                           class="form-control" placeholder="Buscar por nome ou descrição">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Por página</label>
                    <select name="per_page" class="form-select">
                        <?php $currentPerPage = (int) ($filters['per_page'] ?? 15); ?>
                        <?php foreach ([10, 15, 25, 50] as $option): ?>
                            <option value="<?= (int) $option ?>" <?= $currentPerPage === $option ? 'selected' : '' ?>>
                                <?= (int) $option ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="<?= current_url() ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>

            <p class="text-muted small mb-3">
                Exibindo <strong><?= esc((string) ($meta['from'] ?? 0)) ?></strong>
                a <strong><?= esc((string) ($meta['to'] ?? 0)) ?></strong>
                de <strong><?= esc((string) ($meta['total'] ?? 0)) ?></strong> registros.
            </p>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Permissões</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($roles ?? [])): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Nenhum nível cadastrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $systemRoles = ['admin', 'gestor', 'funcionario', 'rh', 'dpo', 'auditor'];
                            foreach ($roles as $role):
                                $roleId   = $role->id ?? $role['id'] ?? 0;
                                $roleName = $role->name ?? $role['name'] ?? '';
                                $roleDesc = $role->description ?? $role['description'] ?? '-';
                                $roleActive = filter_var($role->active ?? true, FILTER_VALIDATE_BOOLEAN);
                                $isSystem = in_array(strtolower($roleName), $systemRoles, true);
                                $permsRaw = $role->permissions ?? $role['permissions'] ?? '[]';
                                $perms = is_string($permsRaw) ? (json_decode($permsRaw, true) ?: []) : (array) $permsRaw;
                                $permCount = count($perms);
                            ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($roleName) ?></strong>
                                        <?php if ($isSystem): ?>
                                            <span class="badge bg-light text-muted border ms-1" title="Role nativo do sistema">nativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted"><?= esc($roleDesc) ?></td>
                                    <td>
                                        <?php if (in_array('*', $perms, true)): ?>
                                            <span class="badge bg-danger">Acesso total</span>
                                        <?php elseif ($permCount > 0): ?>
                                            <span class="badge bg-primary"><?= $permCount ?> permiss<?= $permCount === 1 ? 'ão' : 'ões' ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Nenhuma</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $roleActive ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= $roleActive ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-1 justify-content-end">
                                            <a href="<?= sp_route_url('settings.roles.edit', $roleId) ?>"
                                               class="btn btn-sm btn-outline-secondary" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Excluir nível de acesso"
                                                    onclick="confirmDeleteRole(<?= (int) $roleId ?>, '<?= esc(addslashes($roleName), 'js') ?>')">
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

            <?php if (($meta['page_count'] ?? 1) > 1): ?>
                <?php
                    $currentPage = (int) ($meta['page'] ?? 1);
                    $pageCount   = (int) ($meta['page_count'] ?? 1);
                    $baseQuery   = $filters ?? [];
                ?>
                <nav aria-label="Paginação" class="mt-3">
                    <ul class="pagination justify-content-end mb-0">
                        <?php $prevQuery = array_merge($baseQuery, ['page' => max(1, $currentPage - 1)]); ?>
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentPage <= 1 ? '#' : current_url() . '?' . http_build_query($prevQuery) ?>">Anterior</a>
                        </li>
                        <?php for ($p = 1; $p <= $pageCount; $p++): ?>
                            <?php $pq = array_merge($baseQuery, ['page' => $p]); ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= current_url() . '?' . http_build_query($pq) ?>"><?= (int) $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php $nextQuery = array_merge($baseQuery, ['page' => min($pageCount, $currentPage + 1)]); ?>
                        <li class="page-item <?= $currentPage >= $pageCount ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $currentPage >= $pageCount ? '#' : current_url() . '?' . http_build_query($nextQuery) ?>">Próxima</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal confirmação de exclusão -->
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-labelledby="deleteRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger" id="deleteRoleModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar exclusão
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir o nível de acesso <strong id="deleteRoleName"></strong>?</p>
                <p class="text-muted small mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Esta ação não pode ser desfeita. Colaboradores vinculados a este nível precisarão ser reatribuídos antes da exclusão.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form id="deleteRoleForm" method="post" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger" id="deleteRoleBtn">
                        <i class="bi bi-trash-fill me-2"></i>Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
function confirmDeleteRole(id, name) {
    document.getElementById('deleteRoleName').textContent = name;
    document.getElementById('deleteRoleForm').action = '<?= site_url('settings/roles/') ?>' + id + '/delete';

    const btn = document.getElementById('deleteRoleBtn');
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-trash-fill me-2"></i>Excluir';

    document.getElementById('deleteRoleForm').onsubmit = async function(e) {
        e.preventDefault();
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Excluindo...';
        try {
            const fd = new FormData(this);
            const resp = await fetch(this.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const json = await resp.json();
            const modal = bootstrap.Modal.getInstance(document.getElementById('deleteRoleModal'));
            modal.hide();
            if (json.success) {
                location.reload();
            } else {
                setTimeout(() => alert(json.message || 'Erro ao excluir.'), 400);
            }
        } catch (err) {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash-fill me-2"></i>Excluir';
            alert('Erro de comunicação com o servidor.');
        }
    };

    new bootstrap.Modal(document.getElementById('deleteRoleModal')).show();
}
</script>
<?= $this->endSection() ?>
