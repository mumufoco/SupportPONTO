<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Assinar advertência<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Assinatura da advertência',
        'subtitle' => 'Leia o conteúdo, valide os dados e registre a ciência eletrônica para concluir o fluxo disciplinar.',
        'icon' => 'bi bi-pen-fill',
        'actions' => [
            ['label' => 'Voltar para advertência', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_warning_show_url((int) ($warning->id ?? 0))],
            ['label' => 'Lista de advertências', 'icon' => 'bi bi-list-ul', 'url' => sp_warning_index_url()],
            ['label' => 'Baixar PDF', 'icon' => 'bi bi-download', 'url' => sp_warning_download_url((int) ($warning->id ?? 0))],
        ],
    ]) ?>

    <div class="sp-disciplinary-grid">
        <div class="span-8">
            <div class="sp-signature-box">
                <div class="sp-signature-box__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-file-earmark-text-fill"></i>Resumo da advertência</h2>
                </div>
                <div class="sp-signature-box__body">
                    <div class="sp-meta-list">
                        <div class="sp-meta-item"><small>Número</small><strong>#<?= esc($warning->id ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Colaborador</small><strong><?= esc($warning->employee_name ?? ($warningEmployee->name ?? '-')) ?></strong></div>
                        <div class="sp-meta-item"><small>Tipo</small><strong><?= esc($warning->warning_type ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Data da ocorrência</small><strong><?= esc($warning->occurrence_date ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Emitida por</small><strong><?= esc($issuer->name ?? ($warning->issuer_name ?? '-')) ?></strong></div>
                        <div class="sp-meta-item"><small>Status atual</small><span class="sp-disciplinary-status"><i class="bi bi-clock-history"></i><span><?= esc($warning->status ?? 'Pendente') ?></span></span></div>
                        <div class="sp-meta-item"><small>Motivo</small><span><?= esc($warning->reason ?? '-') ?></span></div>
                    </div>
                </div>
            </div>

            <div class="sp-signature-box mt-3">
                <div class="sp-signature-box__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-pencil-square"></i>Ciência e assinatura</h2>
                </div>
                <div class="sp-signature-box__body">
                    <form action="<?= sp_safe_url(sp_warning_sign_submit_url((int) ($warning->id ?? 0))) ?>" method="post" enctype="multipart/form-data" class="sp-signature-shell">
                        <?= csrf_field() ?>

                        <div>
                            <label class="form-label" for="signer_name">Nome do signatário *</label>
                            <input type="text" id="signer_name" name="signer_name" class="form-control" value="<?= sp_attr(old('signer_name', $signerName ?? ($warningEmployee->name ?? ''))) ?>" required>
                        </div>

                        <div>
                            <label class="form-label d-block">Método de assinatura *</label>
                            <div class="d-flex flex-column gap-2">
                                <label class="form-check">
                                    <input class="form-check-input" type="radio" name="signature_method" value="sms" <?= old('signature_method', 'sms') === 'sms' ? 'checked' : '' ?>>
                                    <span class="form-check-label">Assinatura eletrônica com código SMS</span>
                                </label>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="sms_code">Código SMS</label>
                                <input type="text" id="sms_code" name="sms_code" class="form-control" value="<?= sp_attr(old('sms_code')) ?>" placeholder="Informe o código recebido por SMS">
                                <div class="form-text">Utilize este campo quando optar pela assinatura via SMS.</div>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-secondary w-100" id="send-sms-code" data-url="<?= sp_safe_url(sp_warning_sign_sms_url((int) ($warning->id ?? 0))) ?>">
                                    <i class="bi bi-phone me-1"></i>Enviar código SMS
                                </button>
                            </div>
                        </div>

                        <div class="sp-callout-info">
                            <strong><i class="bi bi-info-circle-fill me-2"></i>Declaração de ciência</strong>
                            <div>Ao concluir esta etapa, você registra ciência formal do documento. A assinatura eletrônica não impede manifestação posterior pelos canais internos da empresa.</div>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms_accepted" name="terms_accepted" value="1" <?= old('terms_accepted') ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="terms_accepted">
                                Declaro que li o conteúdo desta advertência e registro minha ciência eletrônica de forma livre e informada. *
                            </label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= sp_safe_url(sp_warning_show_url((int) ($warning->id ?? 0))) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-pen-fill me-1"></i>Confirmar assinatura
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="span-4">
            <div class="sp-signature-box">
                <div class="sp-signature-box__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-list-check"></i>Etapas do fluxo</h2>
                </div>
                <div class="sp-signature-box__body">
                    <div class="sp-approval-flow">
                        <div class="sp-approval-flow__item">
                            <strong>1. Revisão</strong>
                            <div class="text-muted">Confira o número do registro, tipo, data da ocorrência e motivo.</div>
                        </div>
                        <div class="sp-approval-flow__item">
                            <strong>2. Identificação</strong>
                            <div class="text-muted">Selecione o método de assinatura aplicável ao seu caso e preencha os dados solicitados.</div>
                        </div>
                        <div class="sp-approval-flow__item">
                            <strong>3. Conclusão</strong>
                            <div class="text-muted">Após a confirmação, o sistema atualizará o processo disciplinar e registrará a trilha de auditoria.</div>
                        </div>
                    </div>
                    <div class="alert alert-light border mt-3 mb-0">
                        Em caso de divergência, procure o gestor responsável ou o RH antes de concluir a ciência.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script <?= csp_script_nonce_attr() ?>>
document.addEventListener('DOMContentLoaded', function () {
    const button = document.getElementById('send-sms-code');
    if (!button) return;

    button.addEventListener('click', async function () {
        const url = button.getAttribute('data-url');
        const tokenField = document.querySelector('input[name="<?= sp_js(csrf_token()) ?>"]');
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Enviando...';

        try {
            const payload = {};
            if (tokenField) payload[tokenField.name] = tokenField.value;

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: new URLSearchParams(payload).toString()
            });

            const result = await response.json();
            alert(result.message || (result.success ? 'Código SMS enviado com sucesso.' : 'Não foi possível enviar o código SMS.'));
        } catch (error) {
            alert('Não foi possível enviar o código SMS no momento.');
        } finally {
            button.disabled = false;
            button.innerHTML = original;
        }
    });
});
</script>
<?= $this->endSection() ?>
