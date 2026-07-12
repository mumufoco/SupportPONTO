<?= $this->extend('layouts/auth') ?>
<?= $this->section('title') ?>Cadastro enviado<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="text-center" style="max-width:480px;margin:4rem auto;padding:2rem">
    <div style="width:72px;height:72px;background:var(--sp-success-light);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem">
        <i class="bi bi-check-lg" style="font-size:2rem;color:var(--sp-success)"></i>
    </div>
    <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:.5rem">Cadastro enviado com sucesso!</h2>
    <p class="text-muted">
        Olá, <strong><?= esc($name ?? '') ?></strong>!<br>
        Seu cadastro foi recebido e está aguardando aprovação pelo RH ou gestor.<br>
        Você receberá uma notificação quando for aprovado.
    </p>
    <div class="alert alert-info mt-3" style="font-size:.88rem;text-align:left">
        <i class="bi bi-info-circle me-2"></i>
        Após a aprovação, acesse o sistema em
        <strong><?= site_url('login') ?></strong> usando o e-mail e a senha que você cadastrou.
    </div>
</div>
<?= $this->endSection() ?>
