<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Autenticação de Dois Fatores (2FA)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Autenticação de Dois Fatores (2FA)',
        'subtitle' => 'Controle como o 2FA é exigido no login e acompanhe a adesão dos colaboradores.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [
            ['label' => 'Controles', 'icon' => 'bi bi-key-fill', 'url' => sp_route_url('admin.settings.controls')],
        ],
    ]) ?>

    <!-- KPIs -->
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-shield-check-fill',
                'iconColor' => 'success',
                'value' => (string) $stats['enabled'],
                'label' => 'Com 2FA ativo',
                'indicator' => 'usuários configurados',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-check-circle-fill',
                'iconColor' => 'primary',
                'value' => (string) $stats['confirmed'],
                'label' => 'Confirmados',
                'indicator' => 'com código validado',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-people-fill',
                'iconColor' => 'info',
                'value' => (string) $stats['total_users'],
                'label' => 'Total de usuários',
                'indicator' => 'colaboradores ativos',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-graph-up-arrow',
                'iconColor' => $stats['coverage'] >= 80 ? 'success' : ($stats['coverage'] >= 40 ? 'warning' : 'danger'),
                'value' => $stats['coverage'] . '%',
                'label' => 'Cobertura',
                'indicator' => 'com 2FA ativado',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
    </div>

    <!-- Meu 2FA -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-person-fill-lock"></i> Meu 2FA</span>
            <span class="text-muted small">Gerencie a autenticação em duas etapas da sua própria conta.</span>
        </div>
        <div class="sp-card-body">
            <?php if ($myEmployee !== null && (bool) ($myEmployee->two_factor_enabled ?? false)): ?>
                <div class="alert alert-success d-flex gap-2 align-items-start py-2 mb-3">
                    <i class="bi bi-shield-check-fill mt-1 flex-shrink-0"></i>
                    <small>2FA habilitado para <strong><?= esc($myEmployee->email ?? '') ?></strong>. Códigos de backup restantes: <strong><?= (int) $myRemainingBackupCodes ?></strong>.</small>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <form method="post" action="<?= site_url('auth/2fa/regenerate-backup-codes') ?>">
                            <?= csrf_field() ?>
                            <label class="form-label small fw-semibold">Gerar novos códigos de backup</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" placeholder="Confirme sua senha" required>
                                <button type="submit" class="btn btn-outline-primary">Gerar</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <form method="post" action="<?= site_url('auth/2fa/disable') ?>" onsubmit="return confirm('Deseja realmente desabilitar o 2FA da sua conta?');">
                            <?= csrf_field() ?>
                            <label class="form-label small fw-semibold">Desabilitar 2FA</label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control" placeholder="Confirme sua senha" required>
                                <button type="submit" class="btn btn-outline-danger">Desabilitar</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning d-flex gap-2 align-items-start py-2 mb-3">
                    <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
                    <small>O 2FA não está habilitado na sua conta.</small>
                </div>
                <a href="<?= site_url('auth/2fa/setup') ?>" class="btn btn-primary">
                    <i class="bi bi-shield-plus me-1"></i>Configurar 2FA
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Política de 2FA -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-toggles"></i> Política de 2FA</span>
            <span class="text-muted small">Define quando o 2FA é exigido no login.</span>
        </div>
        <div class="sp-card-body">
            <form action="<?= sp_safe_url(sp_route_url('admin.settings.two-factor.update')) ?>" method="POST">
                <?= csrf_field() ?>

                <div class="d-flex flex-column gap-2" style="max-width:640px">
                    <?php foreach ($modes as $value => $info): ?>
                        <label class="d-flex align-items-center gap-3 p-3 rounded-3"
                               style="border:2px solid <?= $currentMode === $value ? 'var(--sp-primary)' : 'var(--sp-border)' ?>; cursor:pointer;">
                            <input type="radio" name="mode" value="<?= esc($value) ?>" <?= $currentMode === $value ? 'checked' : '' ?>>
                            <i class="<?= esc($info['icon']) ?> fs-5"></i>
                            <span>
                                <span class="d-block fw-semibold small"><?= esc($info['label']) ?></span>
                                <span class="d-block text-muted" style="font-size:.78rem"><?= esc($info['desc']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-1"></i>Salvar política
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Usuários com 2FA -->
    <?php if (! empty($usersWithTwoFactor)): ?>
    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-people-fill"></i> Usuários com 2FA</span>
            <span class="text-muted small">Situação do 2FA por usuário. Admins podem forçar reset.</span>
        </div>
        <div class="sp-card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Usuário</th>
                            <th>Status</th>
                            <th>Confirmado em</th>
                            <th>Códigos restantes</th>
                            <th class="text-end pe-3">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usersWithTwoFactor as $employee): ?>
                            <?php $codesLeft = $recoveryCodesLeft[$employee->id] ?? 0; ?>
                            <tr>
                                <td class="ps-3">
                                    <strong><?= esc($employee->name ?? '—') ?></strong>
                                    <div class="small text-muted"><?= esc($employee->email ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($employee->two_factor_verified_at)): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">Pendente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted">
                                    <?= !empty($employee->two_factor_verified_at) ? date('d/m/Y H:i', strtotime((string) $employee->two_factor_verified_at)) : '—' ?>
                                </td>
                                <td class="small <?= $codesLeft <= 2 ? 'text-warning fw-semibold' : 'text-muted' ?>">
                                    <?= (int) $codesLeft ?>/10
                                </td>
                                <td class="text-end pe-3">
                                    <form method="POST"
                                          action="<?= sp_safe_url(sp_route_url('admin.settings.two-factor.user.reset', $employee->id)) ?>"
                                          class="d-inline"
                                          onsubmit="return confirm('Forçar reset do 2FA deste colaborador? O usuário precisará configurar novamente.');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Forçar reset</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="sp-card">
        <div class="sp-card-body">
            <div class="sp-callout-neutral mb-0">
                <i class="bi bi-info-circle me-2"></i>Nenhum colaborador com 2FA ativo até o momento.
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>
<?= $this->endSection() ?>
