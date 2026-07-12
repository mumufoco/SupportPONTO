<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Biometria do perfil<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Minha biometria',
        'subtitle' => 'Gerencie consentimento, cadastro facial e acompanhamento do status biométrico pessoal.',
        'icon' => 'bi bi-fingerprint',
        'actions' => [
            ['label' => 'Meu perfil', 'icon' => 'bi bi-person-circle', 'url' => sp_profile_url()],
            ['label' => 'Cadastro facial', 'icon' => 'bi bi-camera-video-fill', 'url' => site_url('biometric/enrollment')],
            ['label' => 'Gerenciar biometria', 'icon' => 'bi bi-shield-check', 'url' => site_url('biometric/manage')],
            ['label' => 'Segurança da conta', 'icon' => 'bi bi-shield-lock-fill', 'url' => sp_profile_security_url()],
        ],
    ]) ?>

    <div class="sp-profile-grid">
        <div class="span-6">
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-shield-lock-fill"></i>Consentimento e status</h2>
                </div>
                <div class="sp-profile-card__body">
                    <div class="sp-meta-list">
                        <div class="sp-meta-item">
                            <small>Consentimento LGPD</small>
                            <strong><?= !empty($hasConsent) ? 'Concedido' : 'Pendente' ?></strong>
                        </div>
                        <div class="sp-meta-item">
                            <small>Reconhecimento facial</small>
                            <strong><?= !empty($hasFace) ? 'Cadastrado' : 'Pendente' ?></strong>
                        </div>
                        <div class="sp-meta-item">
                            <small>Biometria digital</small>
                            <strong><?= !empty($hasFingerprint) ? 'Cadastrada' : 'Pendente' ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="span-6">
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-lightning-charge-fill"></i>Ações rápidas</h2>
                </div>
                <div class="sp-profile-card__body">
                    <div class="sp-callout-info mb-3"><strong><i class="bi bi-lock-fill me-2"></i>Ação protegida</strong><div>Revogar biometria agora exige confirmação recente de senha na área Segurança da conta.</div></div>
                    <div class="sp-shortcuts-grid">
                        <a class="sp-shortcut-card" href="<?= site_url('biometric/enrollment') ?>">
                            <div class="icon"><i class="bi bi-camera"></i></div>
                            <strong>Cadastro facial</strong>
                            <span>Registrar ou atualizar reconhecimento facial.</span>
                        </a>
                        <a class="sp-shortcut-card" href="<?= site_url('biometric/manage') ?>">
                            <div class="icon"><i class="bi bi-fingerprint"></i></div>
                            <strong>Status biométrico</strong>
                            <span>Verifique cobertura e disponibilidade dos métodos.</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
