<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Termo de Consentimento — <?= esc($meta['label']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card" style="max-width:600px;">
    <div class="sp-auth-card-header">
        <div class="d-flex align-items-center gap-2 mb-3">
            <a href="<?= site_url('consent-gate') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="flex-grow-1 text-center">
                <span class="badge bg-primary" style="font-size:.75rem;">
                    <?= $done + 1 ?> de <?= $total ?>
                </span>
            </div>
        </div>

        <div class="text-center mb-2">
            <i class="<?= esc($meta['icon']) ?>" style="font-size:2rem;color:var(--sp-primary,#0d6efd);"></i>
        </div>
        <h1 class="h5 mb-1"><?= esc($meta['label']) ?></h1>
        <p class="text-muted mb-0 small"><?= esc($meta['description']) ?></p>

        <div class="mt-2">
            <span class="badge <?= $meta['required'] ? 'bg-danger' : 'bg-secondary' ?>">
                <?= $meta['required'] ? 'Obrigatório' : 'Opcional' ?>
            </span>
            <span class="badge bg-light text-dark ms-1 border" style="font-size:.7rem;">
                <?= esc($meta['legal_basis']) ?>
            </span>
        </div>

        <?php
            // Barra de progresso
            $progress = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        ?>
        <div class="progress mt-3" style="height:6px;">
            <div class="progress-bar" role="progressbar"
                 style="width:<?= $progress ?>%"
                 aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
    </div>

    <div class="sp-auth-card-body">

        <p class="text-muted small mb-2">
            <i class="bi bi-file-text me-1"></i>
            Leia o termo completo abaixo antes de aceitar:
        </p>

        <div class="border rounded p-3 mb-4"
             style="max-height:260px;overflow-y:auto;font-size:.82rem;
                    background:var(--sp-surface-alt,#f8f9fa);line-height:1.6;">
            <?php if ($term !== null): ?>
                <?= sp_render_consent_body($term->body) ?>
            <?php else: ?>
                <p><?= esc($meta['description']) ?></p>
                <p class="text-muted">
                    Ao aceitar este termo, você declara estar ciente e concordar com o tratamento dos seus
                    dados pessoais conforme descrito acima, nos termos da Lei Geral de Proteção de Dados
                    (LGPD — Lei nº 13.709/2018), com base legal: <em><?= esc($meta['legal_basis']) ?></em>.
                </p>
                <p class="text-muted">
                    Você pode revogar este consentimento a qualquer momento acessando
                    <strong>Configurações → Privacidade e LGPD</strong>.
                </p>
            <?php endif; ?>
        </div>

        <form action="<?= site_url('consent-gate/' . esc($type) . '/accept') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="agreed" name="agreed"
                       value="1" required>
                <label class="form-check-label small" for="agreed">
                    Li e concordo com o <strong><?= esc($meta['label']) ?></strong>.
                </label>
            </div>

            <button type="submit" class="sp-btn sp-btn-primary sp-btn-full">
                <i class="bi bi-check-lg"></i>
                <?= $pendingCount > 1 ? 'Aceitar e continuar' : 'Aceitar e acessar o sistema' ?>
            </button>
        </form>

        <a href="<?= site_url('consent-gate') ?>" class="d-block text-center text-muted small mt-3">
            <i class="bi bi-arrow-left me-1"></i> Voltar à lista
        </a>

    </div>
</div>

<div class="auth-footer">
    &copy; <?= date('Y') ?> Support Solo Sondagens &middot; SupportPONTO
</div>
<?= $this->endSection() ?>
