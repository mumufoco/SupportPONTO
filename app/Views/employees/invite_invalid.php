<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Link inválido<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="auth-card text-center" style="max-width:420px;margin:4rem auto;padding:2rem">
    <i class="bi bi-link-45deg" style="font-size:3rem;color:var(--sp-danger);display:block;margin-bottom:1rem"></i>
    <h2 style="font-size:1.2rem;font-weight:700;margin-bottom:.5rem">Link inválido ou expirado</h2>
    <p class="text-muted"><?= esc($message ?? 'Este link de convite não é mais válido.') ?></p>
    <a href="<?= site_url('login') ?>" class="btn btn-outline-primary mt-3">Ir para o login</a>
</div>
<?= $this->endSection() ?>
