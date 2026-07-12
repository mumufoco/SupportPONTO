<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Gerenciar 2FA<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-xl-8">
      <div class="sp-card p-4">
        <h1 class="h4 mb-3"><i data-lucide="shield-check"></i> Gerenciar autentica&ccedil;&atilde;o em duas etapas</h1>

        <?= view('components/flash_messages') ?>

        <?php if ((bool) ($employee->two_factor_enabled ?? false)): ?>
          <div class="alert alert-success">2FA est&aacute; habilitado para <strong><?= esc($employee->email ?? '') ?></strong>.</div>
          <p class="mb-4">C&oacute;digos de backup restantes: <strong><?= esc((string) $remaining_backup_codes) ?></strong></p>

          <div class="d-flex flex-wrap gap-2 mb-4">
            <form method="post" action="<?= site_url('auth/2fa/regenerate-backup-codes') ?>">
              <?= csrf_field() ?>
              <div class="input-group">
                <input type="password" name="password" class="form-control" placeholder="Confirme sua senha" required>
                <button type="submit" class="btn btn-outline-primary">Gerar novos c&oacute;digos</button>
              </div>
            </form>
          </div>

          <form method="post" action="<?= site_url('auth/2fa/disable') ?>" onsubmit="return confirm('Deseja realmente desabilitar o 2FA?');">
            <?= csrf_field() ?>
            <div class="input-group">
              <input type="password" name="password" class="form-control" placeholder="Confirme sua senha para desabilitar" required>
              <button type="submit" class="btn btn-outline-danger">Desabilitar 2FA</button>
            </div>
          </form>

        <?php else: ?>
          <div class="alert alert-warning">2FA n&atilde;o est&aacute; habilitado na sua conta.</div>
          <p class="mb-4">Habilite a autentica&ccedil;&atilde;o em duas etapas para aumentar a seguran&ccedil;a do seu acesso.</p>
          <a href="<?= site_url('auth/2fa/setup') ?>" class="btn btn-primary">
            <i class="bi bi-shield-plus"></i> Configurar 2FA
          </a>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
