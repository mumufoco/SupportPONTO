<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Cadastros Pendentes<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Cadastros Pendentes',
        'subtitle' => 'Visão completa de todos os convites e cadastros, separados por estado.',
        'icon'     => 'bi bi-person-plus-fill',
        'actions'  => [],
    ]) ?>


    <?php
        $countAprov      = count($employees ?? []);
        $countActive     = count($activeInvites ?? []);
        $countExpired    = count($expiredInvites ?? []);
        $countUsed       = count($usedInvites ?? []);
        $countTerminated = count($terminatedEmployees ?? []);
    ?>

    <!-- ── Resumo de totais ─────────────────────────────────────────────── -->
    <div class="row row-cols-2 row-cols-md-5 g-3 mb-4">
        <div class="col">
            <div class="sp-card text-center py-3">
                <div style="font-size:1.75rem;font-weight:700;color:var(--sp-warning)"><?= $countActive ?></div>
                <div class="small text-muted">Aguardando preenchimento</div>
            </div>
        </div>
        <div class="col">
            <div class="sp-card text-center py-3">
                <div style="font-size:1.75rem;font-weight:700;color:var(--sp-primary)"><?= $countAprov ?></div>
                <div class="small text-muted">Aguardando aprovação</div>
            </div>
        </div>
        <div class="col">
            <div class="sp-card text-center py-3">
                <div style="font-size:1.75rem;font-weight:700;color:var(--sp-danger)"><?= $countExpired ?></div>
                <div class="small text-muted">Expirados</div>
            </div>
        </div>
        <div class="col">
            <div class="sp-card text-center py-3">
                <div style="font-size:1.75rem;font-weight:700;color:var(--sp-text-muted)"><?= $countUsed ?></div>
                <div class="small text-muted">Utilizados</div>
            </div>
        </div>
        <div class="col">
            <div class="sp-card text-center py-3">
                <div style="font-size:1.75rem;font-weight:700;color:var(--sp-secondary,#6c757d)"><?= $countTerminated ?></div>
                <div class="small text-muted">Desligados</div>
            </div>
        </div>
    </div>

    <!-- ── 1. AGUARDANDO APROVAÇÃO ─────────────────────────────────────── -->
    <div class="sp-card mb-4">
        <div class="sp-card-header" style="border-left:4px solid var(--sp-warning);">
            <span class="sp-card-title">
                <i class="bi bi-person-check-fill me-2 text-warning"></i>
                Aguardando aprovação do administrador
            </span>
            <span class="badge bg-warning text-dark ms-2"><?= $countAprov ?></span>
        </div>
        <div class="sp-table-container">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Departamento</th>
                        <th>Cadastrado em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($employees ?? [])): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                <i class="bi bi-check2-all me-1"></i>Nenhum cadastro aguardando aprovação.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><strong><?= esc($employee->name ?? '') ?></strong></td>
                                <td class="text-muted"><?= esc($employee->email ?? '–') ?></td>
                                <td><?= esc($employee->department ?? '–') ?></td>
                                <td class="text-muted small">
                                    <?= $employee->created_at ? date('d/m/Y H:i', strtotime($employee->created_at)) : '–' ?>
                                </td>
                                <td class="text-end">
                                    <form action="<?= site_url('employees/pending/' . ($employee->id ?? '') . '/approve') ?>"
                                          method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-success" title="Aprovar e ativar">
                                            <i class="bi bi-check-lg me-1"></i>Aprovar
                                        </button>
                                    </form>
                                    <form action="<?= site_url('employees/pending/' . ($employee->id ?? '') . '/reject') ?>"
                                          method="post" class="d-inline ms-1">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Rejeitar cadastro"
                                                onclick="return confirm('Deseja rejeitar e excluir este cadastro?')">
                                            <i class="bi bi-x-lg me-1"></i>Rejeitar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── 2. AGUARDANDO PREENCHIMENTO ─────────────────────────────────── -->
    <div class="sp-card mb-4">
        <div class="sp-card-header" style="border-left:4px solid var(--sp-success);">
            <span class="sp-card-title">
                <i class="bi bi-envelope-clock-fill me-2 text-success"></i>
                Convites aguardando preenchimento
            </span>
            <span class="badge bg-success ms-2"><?= $countActive ?></span>
        </div>
        <div class="sp-table-container">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>E-mail</th>
                        <th>Nome</th>
                        <th>Departamento / Cargo</th>
                        <th>Enviado em</th>
                        <th>Expira em</th>
                        <th>Link</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($activeInvites ?? [])): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-envelope-check me-1"></i>Nenhum convite ativo aguardando.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activeInvites as $inv): ?>
                            <?php
                                $diffSec = strtotime($inv->expires_at) - time();
                                $h = max(0, round($diffSec / 3600));
                                $badgeClass = $h < 24 ? 'bg-danger' : ($h < 48 ? 'bg-warning text-dark' : 'bg-success');
                                $badgeText  = $h >= 24 ? ceil($h/24).'d' : $h.'h';
                            ?>
                            <tr>
                                <td><?= esc($inv->email) ?></td>
                                <td><?= esc($inv->name ?: '–') ?></td>
                                <td>
                                    <?= esc($inv->department ?: '–') ?>
                                    <?php if ($inv->position): ?>
                                        <small class="text-muted"> / <?= esc($inv->position) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($inv->created_at)) ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?> restantes</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="navigator.clipboard.writeText('<?= esc(site_url('convite/' . $inv->token)) ?>').then(()=>{this.textContent='Copiado!';setTimeout(()=>this.innerHTML='<i class=\\'bi bi-link-45deg\\'></i> Copiar',2000)})"
                                            title="Copiar link de cadastro">
                                        <i class="bi bi-link-45deg"></i> Copiar
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── 3. EXPIRADOS SEM PREENCHIMENTO ──────────────────────────────── -->
    <div class="sp-card mb-4">
        <div class="sp-card-header" style="border-left:4px solid var(--sp-danger);">
            <span class="sp-card-title">
                <i class="bi bi-envelope-x-fill me-2 text-danger"></i>
                Convites expirados sem preenchimento
            </span>
            <span class="badge bg-danger ms-2"><?= $countExpired ?></span>
        </div>
        <div class="sp-table-container">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>E-mail</th>
                        <th>Nome</th>
                        <th>Departamento / Cargo</th>
                        <th>Enviado em</th>
                        <th>Expirou em</th>
                        <th>Reenviar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expiredInvites ?? [])): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="bi bi-envelope-check me-1"></i>Nenhum convite expirado.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expiredInvites as $inv): ?>
                            <?php
                                $diasAtras = round((time() - strtotime($inv->expires_at)) / 86400);
                            ?>
                            <tr class="opacity-75">
                                <td><?= esc($inv->email) ?></td>
                                <td><?= esc($inv->name ?: '–') ?></td>
                                <td>
                                    <?= esc($inv->department ?: '–') ?>
                                    <?php if ($inv->position): ?>
                                        <small class="text-muted"> / <?= esc($inv->position) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($inv->created_at)) ?></td>
                                <td>
                                    <span class="badge bg-danger">
                                        <?= date('d/m/Y H:i', strtotime($inv->expires_at)) ?>
                                        (<?= $diasAtras ?>d atrás)
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= site_url('employees') ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       title="Enviar novo convite para este e-mail">
                                        <i class="bi bi-arrow-clockwise"></i> Reenviar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── 4. UTILIZADOS (histórico) ───────────────────────────────────── -->
    <?php if (!empty($usedInvites ?? [])): ?>
    <div class="sp-card mb-4">
        <div class="sp-card-header" style="border-left:4px solid var(--sp-text-muted);">
            <span class="sp-card-title">
                <i class="bi bi-envelope-check-fill me-2 text-muted"></i>
                Histórico — convites utilizados
            </span>
            <span class="badge bg-secondary ms-2"><?= $countUsed ?></span>
        </div>
        <div class="sp-table-container">
            <table class="sp-table" style="font-size:.85rem;">
                <thead>
                    <tr>
                        <th>E-mail</th>
                        <th>Nome</th>
                        <th>Departamento / Cargo</th>
                        <th>Enviado em</th>
                        <th>Preenchido em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usedInvites as $inv): ?>
                        <tr class="opacity-75">
                            <td><?= esc($inv->email) ?></td>
                            <td><?= esc($inv->name ?: '–') ?></td>
                            <td>
                                <?= esc($inv->department ?: '–') ?>
                                <?php if ($inv->position): ?>
                                    <small class="text-muted"> / <?= esc($inv->position) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= date('d/m/Y H:i', strtotime($inv->created_at)) ?></td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?= $inv->used_at ? date('d/m/Y H:i', strtotime($inv->used_at)) : '–' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── DESLIGADOS ─────────────────────────────────────────────────── -->
    <?php if (!empty($terminatedEmployees ?? [])): ?>
    <div class="sp-card mb-4">
        <div class="sp-card-header" style="border-left:4px solid var(--sp-secondary,#6c757d);">
            <span class="sp-card-title">
                <i class="bi bi-person-dash-fill me-2 text-secondary"></i>
                Colaboradores desligados / inativos
            </span>
            <span class="badge bg-secondary ms-2"><?= $countTerminated ?></span>
        </div>
        <div class="sp-table-container">
            <table class="sp-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Departamento</th>
                        <th>Cargo</th>
                        <th>Último acesso</th>
                        <th>Desligado em</th>
                        <th class="text-end">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($terminatedEmployees as $emp): ?>
                        <tr class="opacity-75">
                            <td><strong><?= esc($emp->name ?? '') ?></strong></td>
                            <td class="text-muted"><?= esc($emp->email ?? '–') ?></td>
                            <td><?= esc($emp->department ?? '–') ?></td>
                            <td><?= esc($emp->position ?? '–') ?></td>
                            <td class="text-muted small">
                                <?= $emp->last_login ? date('d/m/Y H:i', strtotime($emp->last_login)) : '–' ?>
                            </td>
                            <td class="text-muted small">
                                <?= $emp->demission_date ? date('d/m/Y', strtotime($emp->demission_date)) : '–' ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= site_url('employees/' . ($emp->id ?? '')) ?>"
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-eye me-1"></i>Ver cadastro
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
<?= $this->endSection() ?>
