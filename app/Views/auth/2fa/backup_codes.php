<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Códigos de backup 2FA<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-xl-8">
      <div class="sp-card p-4">
        <h1 class="h4 mb-3"><i data-lucide="key-round"></i> Guarde seus códigos de backup</h1>
        <p class="text-muted">Cada código pode ser usado uma única vez para entrar caso você perca acesso ao aplicativo autenticador.</p>
        <div class="row g-3 mb-4">
          <?php foreach ($backup_codes as $backupCode): ?>
            <div class="col-sm-6 col-lg-4"><div class="border rounded p-3 text-center fw-semibold"><code><?= esc($backupCode) ?></code></div></div>
          <?php endforeach; ?>
        </div>
        <div class="alert alert-warning">Armazene estes códigos em local seguro. Eles não serão exibidos novamente.</div>
        <a href="<?= site_url('auth/2fa/manage') ?>" class="btn btn-primary">Continuar</a>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
