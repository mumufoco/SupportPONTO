<?= $this->extend('layouts/auth') ?>

<?= $this->section('title') ?>Termos de Consentimento<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="sp-auth-card" style="max-width:560px;">
    <div class="sp-auth-card-header">
        <div class="sp-brand-lockup" style="flex-direction:column;align-items:center;gap:.75rem;">
            <img src="<?= support_logo_url('crop') ?>" alt="Support Solo Sondagens"
                 style="width:56px;height:56px;border-radius:12px;">
            <div class="sp-brand-text" style="text-align:center;">
                <strong>SupportPONTO</strong>
                <span>Support Solo Sondagens</span>
            </div>
        </div>
        <h1 class="h5 mb-1 mt-3">Termos de Consentimento</h1>
        <p class="text-muted mb-0 small">
            Antes de acessar o sistema, você precisa revisar e aceitar os termos abaixo.
            Leia cada um com atenção antes de prosseguir.
        </p>
    </div>

    <div class="sp-auth-card-body">

        <?php if (!empty($accepted)): ?>
            <div class="sp-alert sp-alert-info mb-3" style="font-size:.85rem;">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= count($accepted) ?> de <?= $total ?> termos já aceitos.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($pending)): ?>
            <p class="text-muted small mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Clique em <strong>Analisar</strong> para ler e aceitar cada termo individualmente.
                Você só poderá acessar o sistema após aceitar todos.
            </p>

            <div class="d-flex flex-column gap-2 mb-4">
                <?php foreach ($pending as $item): ?>
                    <div class="d-flex align-items-center gap-3 p-3 rounded border"
                         style="background:var(--sp-surface,#fff);">
                        <div class="flex-shrink-0 text-warning" style="font-size:1.4rem;">
                            <i class="<?= esc($item['icon']) ?>"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold" style="font-size:.9rem;">
                                <?= esc($item['label']) ?>
                                <?php if ($item['required']): ?>
                                    <span class="badge bg-danger ms-1" style="font-size:.65rem;">Obrigatório</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary ms-1" style="font-size:.65rem;">Opcional</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.78rem;"><?= esc($item['description']) ?></div>
                        </div>
                        <a href="<?= site_url('consent-gate/' . esc($item['type'])) ?>"
                           class="btn btn-sm btn-outline-primary flex-shrink-0">
                            Analisar
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($accepted)): ?>
            <hr class="my-3">
            <p class="text-muted small mb-2">
                <i class="bi bi-check-circle-fill text-success me-1"></i> Termos já aceitos:
            </p>
            <div class="d-flex flex-column gap-2">
                <?php foreach ($accepted as $item): ?>
                    <div class="d-flex align-items-center gap-3 p-3 rounded border"
                         style="opacity:.6;background:var(--sp-surface,#fff);">
                        <div class="flex-shrink-0 text-success" style="font-size:1.4rem;">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold" style="font-size:.9rem;"><?= esc($item['label']) ?></div>
                            <div class="text-muted" style="font-size:.78rem;">Aceito</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pending)): ?>
            <div class="sp-alert sp-alert-info">
                <i class="bi bi-check-all"></i>
                <span>Todos os termos foram aceitos.</span>
            </div>
            <a href="<?= site_url('dashboard') ?>" class="sp-btn sp-btn-primary sp-btn-full mt-3">
                <i class="bi bi-house-fill"></i> Acessar o sistema
            </a>
        <?php endif; ?>

    </div>
</div>
<?= $this->endSection() ?>
