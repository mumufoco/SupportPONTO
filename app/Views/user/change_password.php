<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Alterar senha<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Alterar senha',
        'subtitle' => 'Atualize sua senha de acesso com segurança.',
        'icon' => 'bi bi-shield-lock-fill',
        'actions' => [
            ['label' => 'Voltar ao perfil', 'icon' => 'bi bi-person-vcard-fill', 'url' => sp_profile_url()],
        ],
    ]) ?>

    <div class="sp-surface-card">
        <div class="sp-surface-card__header">
            <h2 class="sp-profile-card__title"><i class="bi bi-key-fill"></i>Credenciais</h2>
        </div>
        <div class="sp-surface-card__body">
            <form action="<?= site_url(route_to('profile.changePassword.update')) ?>" method="post" class="sp-form-layout">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="current_password">Senha atual</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="new_password">Nova senha</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="new_password_confirmation">Confirme a nova senha</label>
                        <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a href="<?= sp_profile_url() ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-lock-fill me-1"></i>Salvar nova senha
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
