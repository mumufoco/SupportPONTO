<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cargos<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Cargos',
        'subtitle' => 'Gerencie os cargos disponíveis para atribuição aos colaboradores.',
        'icon'     => 'bi bi-person-badge-fill',
        'actions'  => [
            ['label' => 'Novo Cargo', 'icon' => 'bi bi-plus-lg', 'url' => route_to('settings.positions.create')],
        ],
    ]) ?>


    <div class="card shadow-sm">
        <div class="card-body">

            <form method="get" class="row g-3 align-items-end mb-3">
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>"
                           class="form-control" placeholder="Buscar por nome ou descrição">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php $currentStatus = $filters['status'] ?? 'all'; ?>
                        <option value="all"      <?= $currentStatus === 'all'      ? 'selected' : '' ?>>Todos</option>
                        <option value="active"   <?= $currentStatus === 'active'   ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= $currentStatus === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                    </select>
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
                <div class="col-md-3 d-flex gap-2">
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
                            <th>Departamento</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($positions ?? [])): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Nenhum cargo cadastrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($positions as $pos):
                                $posId    = (int) ($pos['id'] ?? 0);
                                $isActive = ($pos['active'] === true || $pos['active'] === 't');
                            ?>
                                <tr id="row-pos-<?= $posId ?>">
                                    <td><strong><?= esc($pos['name'] ?? '') ?></strong></td>
                                    <td class="text-muted"><?= esc($pos['department_name'] ?? '-') ?></td>
                                    <td class="text-center">
                                        <span class="badge <?= $isActive ? 'bg-success' : 'bg-secondary' ?> sp-status-badge">
                                            <?= $isActive ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-icon-actions">
                                            <a href="<?= sp_route_url('settings.positions.edit', $posId) ?>"
                                               class="icon-action icon-action-edit" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <button type="button"
                                                    class="icon-action <?= $isActive ? 'icon-action-warning' : 'icon-action-success' ?>"
                                                    title="<?= $isActive ? 'Desativar' : 'Ativar' ?>"
                                                    onclick="catalogToggle(this, '<?= sp_route_url('settings.positions.toggle', $posId) ?>')">
                                                <i class="bi <?= $isActive ? 'bi-toggle-on' : 'bi-toggle-off' ?>"></i>
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
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
async function catalogToggle(btn, url) {
    const nameEl  = document.querySelector('meta[name="csrf-token-name"]');
    const hashEl  = document.querySelector('meta[name="csrf-hash"]');
    if (!nameEl || !hashEl) return;
    btn.disabled = true;
    try {
        const fd = new FormData();
        fd.append(nameEl.content, hashEl.content);
        const r    = await fetch(url, { method: 'POST', body: fd });
        const data = await r.json();
        if (data.success !== false) {
            const row    = btn.closest('tr');
            const badge  = row.querySelector('.sp-status-badge');
            const active = data.active ?? (data.status === 'active');
            badge.textContent = active ? 'Ativo' : 'Inativo';
            badge.className   = 'badge sp-status-badge ' + (active ? 'bg-success' : 'bg-secondary');
            btn.className     = 'icon-action ' + (active ? 'icon-action-warning' : 'icon-action-success');
            btn.title         = active ? 'Desativar' : 'Ativar';
            btn.querySelector('i').className = 'bi ' + (active ? 'bi-toggle-on' : 'bi-toggle-off');
            if (hashEl && data.csrf_hash) { hashEl.content = data.csrf_hash; }
        } else {
            alert(data.message ?? 'Erro ao alterar status.');
        }
    } catch (e) {
        alert('Erro de comunicação. Tente novamente.');
    } finally {
        btn.disabled = false;
    }
}
</script>
<?= $this->endSection() ?>
