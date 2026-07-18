<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Adicionar testemunhas<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Adicionar testemunhas',
        'subtitle' => 'Informe os dados das 2 testemunhas para reforçar o registro e dar continuidade ao fluxo disciplinar.',
        'icon' => 'bi bi-person-plus-fill',
        'actions' => [
            ['label' => 'Voltar para advertência', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_warning_show_url((int) ($warning->id ?? 0))],
            ['label' => 'Lista de advertências', 'icon' => 'bi bi-list-ul', 'url' => sp_warning_index_url()],
        ],
    ]) ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <form action="<?= sp_safe_url(route_to('warnings.witness.store', (int) ($warning->id ?? 0))) ?>" method="post" class="d-flex flex-column gap-3">
                <?= csrf_field() ?>

                <?php foreach ([1, 2] as $n): ?>
                    <div class="sp-data-card">
                        <div class="sp-data-card__header">
                            <h2 class="sp-data-card__title">
                                <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-person-vcard-fill"></i></span>
                                Testemunha <?= $n ?>
                            </h2>
                        </div>
                        <div class="sp-data-card__body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="witness_<?= $n ?>_name">Nome completo *</label>
                                    <input type="text" id="witness_<?= $n ?>_name" name="witness_<?= $n ?>_name" class="form-control" value="<?= sp_attr(old("witness_{$n}_name")) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="witness_<?= $n ?>_cpf">CPF *</label>
                                    <input type="text" id="witness_<?= $n ?>_cpf" name="witness_<?= $n ?>_cpf" class="form-control witness-cpf-field" value="<?= sp_attr(old("witness_{$n}_cpf")) ?>" placeholder="000.000.000-00" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="witness_<?= $n ?>_signature">Declaração/assinatura *</label>
                                    <textarea id="witness_<?= $n ?>_signature" name="witness_<?= $n ?>_signature" rows="4" class="form-control" placeholder="Descreva a ciência da testemunha ou registre a assinatura textual conforme procedimento interno." required><?= esc(old("witness_{$n}_signature")) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= sp_safe_url(sp_warning_show_url((int) ($warning->id ?? 0))) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus-fill me-1"></i>Adicionar testemunhas
                    </button>
                </div>
            </form>
        </div>

        <div class="col-lg-4">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-info-circle-fill"></i></span>
                        Orientações
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0">
                            <i class="bi bi-people-fill text-primary me-2"></i>
                            <strong>Duas testemunhas</strong>
                            <small class="d-block text-muted">Orientação jurídica: a recusa de assinatura exige o depoimento de 2 testemunhas presenciais.</small>
                        </div>
                        <div class="list-group-item px-0">
                            <i class="bi bi-shield-check text-primary me-2"></i>
                            <strong>Identificação correta</strong>
                            <small class="d-block text-muted">Cadastre cada testemunha com nome e documento válidos para garantir rastreabilidade.</small>
                        </div>
                        <div class="list-group-item px-0">
                            <i class="bi bi-file-earmark-lock-fill text-primary me-2"></i>
                            <strong>Fluxo disciplinar</strong>
                            <small class="d-block text-muted">Ao confirmar, a advertência é marcada como recusada e o PDF final é gerado com os dados das testemunhas.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
document.querySelectorAll('.witness-cpf-field').forEach(function (field) {
    SupportPontoValidation.bindCpfField(field);
});
</script>
<?= $this->endSection() ?>
