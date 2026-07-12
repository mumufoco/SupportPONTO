<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard Gestor<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Dashboard do Gestor',
        'subtitle' => 'Resumo da equipe, justificativas pendentes e acesso rápido ao registro de ponto.',
        'icon'     => 'bi bi-people-fill',
        'actions'  => [
            ['label' => 'Registrar Ponto', 'icon' => 'bi bi-fingerprint',       'url' => site_url('punch')],
            ['label' => 'Funcionários',     'icon' => 'bi bi-person-lines-fill', 'url' => site_url('employees')],
            ['label' => 'Justificativas',   'icon' => 'bi bi-journal-check',     'url' => site_url('justifications')],
        ],
    ]) ?>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10 text-primary sp-circle-icon-3">
                        <i class="bi bi-people-fill"></i>
                    </span>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Membros da Equipe</div>
                        <div class="fs-3 fw-bold lh-1"><?= (int) ($team_count ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10 text-success sp-circle-icon-3">
                        <i class="bi bi-person-check-fill"></i>
                    </span>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Presentes Hoje</div>
                        <div class="fs-3 fw-bold lh-1"><?= (int) ($team_stats['punched_today'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-opacity-10 text-warning sp-circle-icon-3">
                        <i class="bi bi-journal-text"></i>
                    </span>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Pendências</div>
                        <div class="fs-3 fw-bold lh-1"><?= count($pending_justifications ?? []) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10 text-danger sp-circle-icon-3">
                        <i class="bi bi-clock-history"></i>
                    </span>
                    <div>
                        <div class="text-muted small text-uppercase fw-semibold">Sem Registro Hoje</div>
                        <div class="fs-3 fw-bold lh-1"><?= max(0, ($team_count ?? 0) - ($team_stats['punched_today'] ?? 0)) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Justificativas + Ações rápidas -->
    <div class="row g-4 mb-4">

        <!-- Justificativas pendentes -->
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0"><i class="bi bi-journal-check me-2"></i>Justificativas Pendentes</h5>
                    <span class="badge text-bg-warning rounded-pill"><?= count($pending_justifications ?? []) ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (!empty($pending_justifications)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Funcionário</th>
                                        <th>Data</th>
                                        <th>Tipo</th>
                                        <th>Motivo</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_justifications as $just): ?>
                                        <tr>
                                            <td><strong><?= esc($just->employee_name) ?></strong></td>
                                            <td class="text-muted"><?= date('d/m/Y', strtotime($just->date)) ?></td>
                                            <td><span class="badge text-bg-info"><?= esc($just->type) ?></span></td>
                                            <td class="text-muted"><?= esc(mb_substr($just->reason, 0, 60)) ?>…</td>
                                            <td class="text-end">
                                                <form action="/gestor/justifications/<?= (int) $just->id ?>/approve" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="bi bi-check-lg"></i> Aprovar
                                                    </button>
                                                </form>
                                                <form action="/gestor/justifications/<?= (int) $just->id ?>/reject" method="POST" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        <i class="bi bi-x-lg"></i> Rejeitar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-journal-check fs-2 d-block mb-2"></i>
                            Nenhuma justificativa pendente de aprovação.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ações rápidas -->
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge-fill me-2"></i>Ações Rápidas</h5>
                </div>
                <div class="card-body d-flex flex-column gap-2">
                    <a href="<?= site_url('punch') ?>" class="sp-shortcut-card">
                        <div class="icon"><i class="bi bi-fingerprint"></i></div>
                        <strong>Registrar Ponto</strong>
                        <span>Acesse rapidamente o terminal de ponto.</span>
                    </a>
                    <a href="<?= site_url('timesheet') ?>" class="sp-shortcut-card">
                        <div class="icon"><i class="bi bi-calendar-range-fill"></i></div>
                        <strong>Espelho de Ponto</strong>
                        <span>Consulte os registros do período.</span>
                    </a>
                    <a href="<?= site_url('employees') ?>" class="sp-shortcut-card">
                        <div class="icon"><i class="bi bi-person-lines-fill"></i></div>
                        <strong>Funcionários</strong>
                        <span>Gerencie a equipe e cadastros.</span>
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>
<?= $this->endSection() ?>
