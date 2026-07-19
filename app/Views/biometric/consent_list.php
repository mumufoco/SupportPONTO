<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Termos e Aceites Biométricos<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Termos e Aceites Biométricos',
        'subtitle' => 'Histórico permanente de consentimentos LGPD. Registros protegidos contra exclusão — válidos como prova jurídica.',
        'icon'     => 'bi bi-file-earmark-check-fill',
    ]) ?>

    <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-3">
        <i class="bi bi-lock-fill mt-1 flex-shrink-0"></i>
        <small>Estes registros são <strong>imutáveis por lei</strong> (LGPD, Art. 37 — dever de documentação). Mesmo após desligamento do colaborador, o histórico é preservado permanentemente como prova jurídica.</small>
    </div>

    <div class="sp-data-card mb-3">
        <div class="sp-data-card__body">
            <form method="GET" action="<?= site_url('biometric/consent-terms/list') ?>" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="f-search">Buscar colaborador</label>
                    <input type="text" name="search" id="f-search" class="form-control" placeholder="Nome do colaborador..." value="<?= esc($search ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="f-status">Status do colaborador</label>
                    <select name="status" id="f-status" class="form-select">
                        <option value="all"      <?= ($status ?? 'all') === 'all'      ? 'selected' : '' ?>>Todos</option>
                        <option value="active"   <?= ($status ?? '') === 'active'   ? 'selected' : '' ?>>Ativos</option>
                        <option value="inactive" <?= ($status ?? '') === 'inactive' ? 'selected' : '' ?>>Inativos / Desligados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="f-type">Tipo de consentimento</label>
                    <select name="type" id="f-type" class="form-select">
                        <option value="all" <?= ($filterType ?? 'all') === 'all' ? 'selected' : '' ?>>Todos os tipos</option>
                        <?php foreach ($consentTypes as $key => $label): ?>
                        <option value="<?= esc($key) ?>" <?= ($filterType ?? 'all') === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Buscar</button>
                    <a href="<?= site_url('biometric/consent-terms/list') ?>" class="btn btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="sp-data-card">
        <div class="sp-data-card__header">
            <h2 class="sp-data-card__title">
                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-list-check"></i></span>
                Registros de consentimento
            </h2>
            <span class="sp-badge sp-badge-neutral"><?= $total ?> registro<?= $total !== 1 ? 's' : '' ?></span>
        </div>

        <?php if (empty($records)): ?>
            <div class="sp-data-card__body">
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-inbox"></i></div>
                    <p class="sp-empty-title">Nenhum registro encontrado</p>
                    <?php if (!empty($search)): ?>
                        <p class="text-muted small mb-0">Nenhum resultado para "<?= esc($search) ?>".</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Colaborador</th>
                            <th>Tipo</th>
                            <th>Situação</th>
                            <th>Versão</th>
                            <th>Status aceite</th>
                            <th>Data do aceite</th>
                            <th>IP</th>
                            <th class="text-end">PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                        <?php
                            $name   = esc($r->employee_name ?? '—');
                            $cpf    = $r->employee_cpf ?? null;
                            $active = $r->employee_active ?? null;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= $name ?></div>
                                <?php if ($cpf): ?>
                                <div class="small text-muted">CPF: ***.***.***-<?= substr(preg_replace('/\D/', '', (string) $cpf), -2) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r->employee_email)): ?>
                                <div class="small text-muted"><?= esc($r->employee_email) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="sp-badge sp-badge-neutral"><?= esc($consentTypes[$r->consent_type] ?? $r->consent_type) ?></span>
                            </td>
                            <td>
                                <?php if ($active === null): ?>
                                    <span class="sp-badge sp-badge-neutral"><i class="bi bi-archive me-1"></i>Sem colaborador</span>
                                <?php elseif ($active): ?>
                                    <span class="sp-badge sp-badge-success"><i class="bi bi-person-check-fill me-1"></i>Ativo</span>
                                <?php else: ?>
                                    <span class="sp-badge sp-badge-warning"><i class="bi bi-person-dash-fill me-1"></i>Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="sp-badge sp-badge-neutral">v<?= esc($r->version) ?></span></td>
                            <td>
                                <?php if ($r->granted && !$r->revoked_at): ?>
                                    <span class="sp-badge sp-badge-success"><i class="bi bi-check-circle-fill me-1"></i>Aceito</span>
                                <?php elseif ($r->revoked_at): ?>
                                    <span class="sp-badge sp-badge-danger"><i class="bi bi-x-circle-fill me-1"></i>Revogado</span>
                                    <div class="small text-muted"><?= esc(format_date_br((string) $r->revoked_at)) ?></div>
                                <?php else: ?>
                                    <span class="sp-badge sp-badge-warning">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= $r->granted_at ? esc(format_datetime_br((string) $r->granted_at, false)) : '—' ?></td>
                            <td class="small text-muted"><?= esc($r->ip_address ?? '—') ?></td>
                            <td class="text-end">
                                <div class="table-icon-actions">
                                    <a href="<?= site_url('biometric/consent-terms/pdf/' . $r->id) ?>" class="icon-action" title="Baixar PDF com validade judicial" target="_blank">
                                        <i class="bi bi-file-earmark-pdf-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($total > $perPage): ?>
        <?php
            $pages       = (int) ceil($total / $perPage);
            $currentPage = (int) ($page ?? 1);
            $qs = http_build_query(array_filter([
                'search' => $search ?? '',
                'status' => $status ?? 'all',
                'type'   => $filterType ?? 'all',
            ]));
        ?>
        <div class="sp-data-card__body border-top">
            <nav>
                <ul class="pagination pagination-sm mb-0 flex-wrap">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $qs ? '&' . $qs : '' ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
