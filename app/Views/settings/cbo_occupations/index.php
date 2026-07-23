<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>CBO<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'CBO — Classificação Brasileira de Ocupações',
        'subtitle' => 'Tabela oficial de referência (Ministério do Trabalho) usada para indicar o CBO de cada cargo. Consulta apenas — os códigos não são editáveis aqui.',
        'icon'     => 'bi bi-journal-bookmark-fill',
        'actions'  => [
            ['label' => 'Voltar para Cargos', 'icon' => 'bi bi-arrow-left', 'url' => route_to('settings.positions')],
        ],
    ]) ?>

    <div class="card shadow-sm">
        <div class="card-body">

            <form method="get" class="row g-3 align-items-end mb-3">
                <div class="col-md-6">
                    <label class="form-label">Buscar</label>
                    <input type="search" name="search" value="<?= esc($filters['search'] ?? '') ?>"
                           class="form-control" placeholder="Buscar por código ou ocupação">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Por página</label>
                    <select name="per_page" class="form-select">
                        <?php $currentPerPage = (int) ($filters['per_page'] ?? 25); ?>
                        <?php foreach ([25, 50, 100] as $option): ?>
                            <option value="<?= (int) $option ?>" <?= $currentPerPage === $option ? 'selected' : '' ?>>
                                <?= (int) $option ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                    <a href="<?= current_url() ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>

            <p class="text-muted small mb-3">
                Exibindo <strong><?= esc((string) ($meta['from'] ?? 0)) ?></strong>
                a <strong><?= esc((string) ($meta['to'] ?? 0)) ?></strong>
                de <strong><?= esc((string) ($meta['total'] ?? 0)) ?></strong> códigos.
            </p>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width:140px;">Código</th>
                            <th>Ocupação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cboOccupations ?? [])): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Nenhum código encontrado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cboOccupations as $occupation): ?>
                                <tr>
                                    <td><code><?= esc($occupation->code ?? '') ?></code></td>
                                    <td><?= esc($occupation->title ?? '') ?></td>
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
                        <?php
                            $windowStart = max(1, $currentPage - 3);
                            $windowEnd   = min($pageCount, $currentPage + 3);
                        ?>
                        <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
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
