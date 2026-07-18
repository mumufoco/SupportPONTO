<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Adicionar testemunha<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Adicionar testemunha',
        'subtitle' => 'Informe os dados da testemunha para reforçar o registro e dar continuidade ao fluxo disciplinar.',
        'icon' => 'bi bi-person-plus-fill',
        'actions' => [
            ['label' => 'Voltar para advertência', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_warning_show_url((int) ($warning->id ?? 0))],
            ['label' => 'Lista de advertências', 'icon' => 'bi bi-list-ul', 'url' => sp_warning_index_url()],
        ],
    ]) ?>

    <div class="sp-disciplinary-grid">
        <div class="span-8">
            <div class="sp-witness-card">
                <h2 class="sp-witness-card__title"><i class="bi bi-person-vcard-fill"></i>Dados da testemunha</h2>
                <form action="<?= sp_safe_url(route_to('warnings.witness.store', (int) ($warning->id ?? 0))) ?>" method="post" class="sp-form-layout">
                    <?= csrf_field() ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="witness_name">Nome completo da testemunha *</label>
                            <input type="text" id="witness_name" name="witness_name" class="form-control" value="<?= sp_attr(old('witness_name')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="witness_cpf">CPF da testemunha *</label>
                            <input type="text" id="witness_cpf" name="witness_cpf" class="form-control" value="<?= sp_attr(old('witness_cpf')) ?>" placeholder="000.000.000-00" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="witness_signature">Declaração/assinatura da testemunha *</label>
                            <textarea id="witness_signature" name="witness_signature" rows="5" class="form-control" placeholder="Descreva a ciência da testemunha ou registre a assinatura textual conforme procedimento interno." required><?= esc(old('witness_signature')) ?></textarea>
                            <div class="form-text">Este campo será usado como registro da manifestação da testemunha no fluxo de recusa da assinatura.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= sp_safe_url(sp_warning_show_url((int) ($warning->id ?? 0))) ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus-fill me-1"></i>Adicionar testemunha
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="span-4">
            <div class="sp-witness-card">
                <h2 class="sp-witness-card__title"><i class="bi bi-info-circle-fill"></i>Orientações</h2>
                <div class="sp-approval-flow">
                    <div class="sp-approval-flow__item">
                        <strong>Identificação correta</strong>
                        <div class="text-muted">Cadastre a testemunha com nome e documento válidos para garantir rastreabilidade.</div>
                    </div>
                    <div class="sp-approval-flow__item">
                        <strong>Fluxo disciplinar</strong>
                        <div class="text-muted">Após o cadastro, a testemunha poderá participar do processo de assinatura e validação do registro.</div>
                    </div>
                    <div class="sp-approval-flow__item">
                        <strong>Boas práticas</strong>
                        <div class="text-muted">Use observações apenas quando agregarem contexto real ao histórico da advertência.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
SupportPontoValidation.bindCpfField(document.getElementById('witness_cpf'));
</script>
<?= $this->endSection() ?>
