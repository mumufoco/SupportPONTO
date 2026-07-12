<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Certificado digital<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-admin-listing-shell">
    <?= view('components/page_header', [
        'title' => 'Certificado digital',
        'subtitle' => 'Gerencie o certificado usado nas integrações críticas, acompanhe validade e mantenha o ambiente pronto para emissão e autenticação.',
        'icon' => 'bi bi-patch-check-fill',
        'actions' => [
                                            ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <?php if (!empty($certificate_info)): ?>
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-patch-check-fill"></i> Certificado Atual</span>
        </div>
        <div class="sp-card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="fw-semibold small text-muted">Tipo / Titular</div>
                    <div><?= esc($certificate_info['subject'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-3">
                    <div class="fw-semibold small text-muted">Emissor</div>
                    <div><?= esc($certificate_info['issuer'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-2">
                    <div class="fw-semibold small text-muted">Válido até</div>
                    <div><?= esc($certificate_info['valid_to'] ?? 'N/A') ?></div>
                </div>
                <div class="col-md-2">
                    <div class="fw-semibold small text-muted">Dias restantes</div>
                    <div><?= esc($certificate_info['days_remaining'] ?? 0) ?></div>
                </div>
                <div class="col-md-1 d-flex align-items-center">
                    <?php if ($certificate_info['is_valid'] ?? false): ?>
                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Válido</span>
                    <?php else: ?>
                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Expirado</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="sp-callout-neutral mb-3">
        <i class="bi bi-info-circle me-2"></i>Nenhum certificado digital configurado ainda.
    </div>
    <?php endif; ?>

    <div class="sp-disciplinary-grid">
        <div class="span-8">
            <div class="sp-surface-card">
                <div class="sp-surface-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-upload"></i>Atualizar certificado</h2>
                </div>
                <div class="sp-surface-card__body">
                    <form action="<?= sp_route_url('admin.settings.certificate.update') ?>" method="post" enctype="multipart/form-data" class="sp-form-layout">
                        <?= csrf_field() ?>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="certificate_file" class="form-label">Arquivo do certificado</label>
                                <input type="file" id="certificate_file" name="certificate_file" class="form-control" accept=".pfx,.p12">
                            </div>
                            <div class="col-md-4">
                                <label for="certificate_password" class="form-label">Senha do certificado</label>
                                <input type="password" id="certificate_password" name="certificate_password" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label for="current_password" class="form-label">Sua senha atual</label>
                                <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password">
                            </div>
                        </div>

                        <div class="sp-callout-info">
                            <strong><i class="bi bi-info-circle-fill me-2"></i>Boas práticas</strong>
                            <div>Use apenas arquivos válidos, mantenha a senha protegida e revise a validade periodicamente. Alterações críticas exigem confirmação da sua senha.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    onclick="if(confirm('Remover o certificado?')) spFetch('<?= sp_route_url('admin.settings.certificate.remove') ?>', {method:'POST', body: new URLSearchParams({<?= csrf_token() ?>:'<?= csrf_hash() ?>',current_password:document.getElementById('current_password').value})}).then(r=>r.json()).then(d=>{ alert(d.message||'Erro'); if(d.success) location.reload(); })">
                                <i class="bi bi-trash3 me-1"></i>Remover
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-floppy-fill me-1"></i>Salvar certificado
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
