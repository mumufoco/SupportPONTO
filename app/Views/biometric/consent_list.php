<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Termos e Aceites Biométricos<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="container-fluid">
    <?= view('components/page_header', [
        'title'    => 'Termos e Aceites Biométricos',
        'subtitle' => 'Histórico permanente de consentimentos LGPD. Registros protegidos contra exclusão — válidos como prova jurídica.',
        'icon'     => 'bi bi-file-earmark-check-fill',
        'actions'  => [
            ['label' => 'Templates de Termos', 'icon' => 'bi bi-pencil-square', 'url' => site_url('settings/consent-terms')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="GET" action="<?= site_url('biometric/consent-terms/list') ?>" class="row g-2 align-items-end">
                <div class="col-sm-6 col-md-4">
                    <label class="form-label small fw-semibold mb-1">Buscar colaborador</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control"
                               placeholder="Nome do colaborador..."
                               value="<?= esc($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-sm-4 col-md-2">
                    <label class="form-label small fw-semibold mb-1">Status do colaborador</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"      <?= ($status ?? 'all') === 'all'      ? 'selected' : '' ?>>Todos</option>
                        <option value="active"   <?= ($status ?? '') === 'active'   ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inativos / Desligados</option>
                    </select>
                </div>
                <div class="col-sm-4 col-md-3">
                    <label class="form-label small fw-semibold mb-1">Tipo de consentimento</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="all" <?= ($filterType ?? 'all') === 'all' ? 'selected' : '' ?>>Todos os tipos</option>
                        <?php foreach ($consentTypes as $key => $label): ?>
                        <option value="<?= esc($key) ?>" <?= ($filterType ?? 'all') === $key ? 'selected' : '' ?>>
                            <?= esc($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel-fill me-1"></i>Filtrar
                    </button>
                    <a href="<?= site_url('biometric/consent-terms/list') ?>" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x-lg me-1"></i>Limpar
                    </a>
                </div>
                <div class="col-auto ms-auto">
                    <span class="badge bg-secondary fs-6"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
                </div>
            </form>
        </div>
    </div>

    <!-- Aviso de imutabilidade -->
    <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-3">
        <i class="bi bi-lock-fill mt-1 flex-shrink-0"></i>
        <small>Estes registros são <strong>imutáveis por lei</strong> (LGPD, Art. 37 — dever de documentação). Mesmo após desligamento do colaborador, o histórico é preservado permanentemente como prova jurídica.</small>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Colaborador</th>
                            <th>Tipo</th>
                            <th>Situação</th>
                            <th>Versão</th>
                            <th>Status Aceite</th>
                            <th>Data do Aceite</th>
                            <th>IP</th>
                            <th class="text-end pe-3">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                Nenhum registro encontrado<?= !empty($search) ? ' para "' . esc($search) . '"' : '' ?>.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($records as $r): ?>
                        <?php
                            $name   = esc($r->employee_name ?? '-');
                            $cpf    = $r->employee_cpf ?? null;
                            $active = $r->employee_active ?? null;
                        ?>
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold"><?= $name ?></div>
                                <?php if ($cpf): ?>
                                <div class="small text-muted">CPF: ***.***.***-<?= substr(preg_replace('/\D/', '', (string)$cpf), -2) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r->employee_email)): ?>
                                <div class="small text-muted"><?= esc($r->employee_email) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary" style="font-size:.72rem;white-space:normal;">
                                    <?= esc($consentTypes[$r->consent_type] ?? $r->consent_type) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($active === null): ?>
                                    <span class="badge bg-secondary"><i class="bi bi-archive me-1"></i>Desligado</span>
                                <?php elseif ($active): ?>
                                    <span class="badge bg-success"><i class="bi bi-person-check me-1"></i>Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-person-dash me-1"></i>Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary">v<?= esc($r->version) ?></span>
                            </td>
                            <td>
                                <?php if ($r->granted && !$r->revoked_at): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aceito</span>
                                <?php elseif ($r->revoked_at): ?>
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Revogado</span>
                                    <div class="small text-muted"><?= date('d/m/Y', strtotime($r->revoked_at)) ?></div>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= $r->granted_at ? date('d/m/Y H:i', strtotime($r->granted_at)) : '-' ?>
                            </td>
                            <td class="small text-muted"><?= esc($r->ip_address ?? '-') ?></td>
                            <td class="text-end pe-3">
                                <a href="<?= site_url('biometric/consent-terms/pdf/' . $r->id) ?>"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Baixar PDF com validade judicial"
                                   target="_blank">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginação -->
        <?php if ($total > $perPage): ?>
        <div class="card-footer bg-transparent d-flex gap-1 flex-wrap">
            <?php
            $pages      = (int) ceil($total / $perPage);
            $currentPage = (int) ($page ?? 1);
            $qs = http_build_query(array_filter(['search' => $search ?? '', 'status' => $status ?? 'all']));
            for ($i = 1; $i <= $pages; $i++):
            ?>
            <a href="?page=<?= $i ?><?= $qs ? '&' . $qs : '' ?>"
               class="btn btn-sm <?= $i === $currentPage ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
