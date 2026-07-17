<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Termo de Consentimento Biometrico<?= $this->endSection() ?>
<?= $this->section('content') ?>

<div class="container py-4" style="max-width:820px;">

    <div class="card border-0 shadow">
        <div class="card-header bg-primary text-white py-3">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-shield-lock-fill fs-3"></i>
                <div>
                    <h5 class="mb-0 fw-bold">Termo de Consentimento &mdash; <?= ($consentType ?? '') === 'biometric_fingerprint' ? 'Dados Biometricos Digitais' : 'Dados Biometricos Faciais' ?></h5>
                    <small class="opacity-75">Versao <?= esc($term->version) ?> &bull; Obrigatorio antes do cadastro biometrico</small>
                </div>
            </div>
        </div>

        <div class="card-body p-4">
            <!-- Info colaborador -->
            <div class="alert alert-info d-flex gap-3 align-items-start mb-4">
                <i class="bi bi-person-circle fs-4 mt-1"></i>
                <div>
                    <strong>Colaborador:</strong> <?= esc($employee->name) ?><br>
                    <small class="text-muted">Para prosseguir com o cadastro biometrico <?= ($consentType ?? "") === "biometric_fingerprint" ? "de digital" : "facial" ?> deste colaborador, e necessario que o termo abaixo seja lido e aceito formalmente.</small>
                </div>
            </div>

            <!-- Texto do termo -->
            <div id="termText" class="border rounded p-4 bg-light mb-4" style="max-height:420px;overflow-y:auto;font-size:.9rem;line-height:1.7;">
                <h6 class="fw-bold text-center mb-3"><?= esc($term->title) ?></h6>
                <?= sp_render_consent_body($term->body) ?>
            </div>

            <!-- Base legal badge -->
            <?php if (!empty($term->legal_basis)): ?>
            <div class="d-flex align-items-start gap-2 mb-4 text-muted small">
                <i class="bi bi-book mt-1"></i>
                <span><strong>Base legal:</strong> <?= esc($term->legal_basis) ?></span>
            </div>
            <?php endif; ?>

            <!-- Formulario de aceite -->
            <form action="<?= site_url('biometric/consent-term/' . ($consentType ?? 'biometric_face') . '/' . $employee->id . '/accept') ?>" method="POST">
                <?= csrf_field() ?>

                <div class="card border-warning mb-4">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="agreed" value="1" id="chkAgreed" required>
                            <label class="form-check-label fw-semibold" for="chkAgreed">
                                Confirmo que o colaborador <strong><?= esc($employee->name) ?></strong> leu, compreendeu e consente
                                de forma livre, informada e inequivoca com o tratamento dos seus dados biometricos <?= ($consentType ?? "") === "biometric_fingerprint" ? "digitais (digitais/impressoes digitais)" : "faciais" ?>
                                conforme descrito acima (LGPD, Art. 11, II, "a").
                            </label>
                        </div>
                        <div class="form-text mt-2 text-muted">
                            <i class="bi bi-info-circle"></i>
                            Este aceite sera registrado com data/hora, IP, versao do termo e identificacao do responsavel,
                            formando evidencia juridicamente valida.
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-3 justify-content-between">
                    <a href="<?= site_url('biometric/manage') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-success px-4" id="btnAcept">
                        <i class="bi bi-check-circle-fill me-1"></i> Aceitar e Continuar para Cadastro Biometrico
                    </button>
                </div>
            </form>
        </div>

        <div class="card-footer text-muted small py-2 px-4">
            <i class="bi bi-shield-check"></i>
            Registro de aceite protegido por hash SHA-256. Auditavel em <a href="<?= site_url('biometric/consent-terms/list') ?>">Auditoria &rsaquo; Termos e Aceites</a>.
        </div>
    </div>
</div>



<?= $this->endSection() ?>
