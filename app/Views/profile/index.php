<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Meu Perfil<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('components/page_header', [
    'title'    => 'Meu Perfil',
    'subtitle' => 'Visualize e atualize suas informações pessoais.',
    'icon'     => 'bi bi-person-circle',
]) ?>

<?= view('components/flash_messages') ?>

<div class="row g-4">

    <!-- Coluna esquerda: avatar + resumo -->
    <div class="col-12 col-lg-4">
        <div class="card text-center">
            <div class="card-body py-4">
                <div class="v2-profile-avatar mx-auto mb-3" aria-hidden="true">
                    <?= esc(mb_strtoupper(mb_substr($employee['name'] ?? '?', 0, 2))) ?>
                </div>
                <h2 class="h5 mb-1"><?= esc($employee['name'] ?? '') ?></h2>
                <p class="text-muted small mb-2"><?= esc($employee['email'] ?? '') ?></p>
                <span class="badge rounded-pill text-bg-primary">
                    <?= esc(ucfirst($employee['role'] ?? 'funcionario')) ?>
                </span>

                <?php if (!empty($employee['department'] ?? null)): ?>
                    <div class="mt-3 text-muted small">
                        <i class="bi bi-diagram-3 me-1" aria-hidden="true"></i>
                        <?= esc($employee['department']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($employee['position'] ?? null)): ?>
                    <div class="text-muted small">
                        <i class="bi bi-briefcase me-1" aria-hidden="true"></i>
                        <?= esc($employee['position']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer d-flex gap-2 justify-content-center">
                <a href="<?= site_url('profile/security') ?>"
                   class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                    Segurança
                </a>
            </div>
        </div>
    </div>

    <!-- Coluna direita: formulário -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-pencil-square text-primary" aria-hidden="true"></i>
                <h2 class="h5 mb-0">Informações pessoais</h2>
            </div>
            <div class="card-body">
                <?= $this->renderSection('profile_form') ?>

                <?php if (empty($this->renderSection('profile_form'))): ?>
                <div class="row g-3">
                    <?php
                    $fields = [
                        ['label'=>'Nome completo', 'value'=>$employee['name'] ?? '', 'icon'=>'bi bi-person'],
                        ['label'=>'E-mail',        'value'=>$employee['email'] ?? '', 'icon'=>'bi bi-envelope'],
                        ['label'=>'Telefone',      'value'=>$employee['phone'] ?? '-', 'icon'=>'bi bi-telephone'],
                        ['label'=>'CPF',           'value'=>$employee['cpf'] ?? '-', 'icon'=>'bi bi-card-text'],
                    ];
                    ?>
                    <?php foreach ($fields as $field): ?>
                        <div class="col-12 col-sm-6">
                            <label class="form-label">
                                <i class="<?= esc($field['icon']) ?> me-1" aria-hidden="true"></i>
                                <?= esc($field['label']) ?>
                            </label>
                            <div class="v2-readonly-field"><?= esc($field['value']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
.v2-profile-avatar {
    width: 80px; height: 80px;
    border-radius: 50%;
    background: var(--sp-primary-dark);
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
    font-weight: 700;
    box-shadow: 0 4px 20px rgba(79,161,79,.4);
    letter-spacing: -.02em;
}

.v2-readonly-field {
    padding: .65rem .875rem;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 8px;
    font-size: .875rem;
    color: var(--sp-text-primary);
    min-height: 44px;
    display: flex;
    align-items: center;
}

[data-theme="light"] .v2-readonly-field {
    background: #f7fafc;
    border-color: #e2e8f0;
    color: #1a1a2e;
}
</style>
<?= $this->endSection() ?>
