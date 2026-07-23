<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Advertência #<?= esc($warning->id ?? '') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Detalhes da advertência #' . esc((string) ($warning->id ?? '')),
        'subtitle' => 'Consulte dados da ocorrência, status de assinatura, evidências e histórico relacionado.',
        'icon'     => 'bi bi-exclamation-triangle-fill',
        'actions'  => [
            ['label' => 'Voltar para lista', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_warning_index_url()],
        ],
    ]) ?>

    <div class="row g-3">
        <div class="col-lg-8 d-flex flex-column gap-3">

            <?php
                $typeLabels = ['verbal' => 'Verbal', 'escrita' => 'Escrita', 'suspensao' => 'Suspensão'];
                $typeBadges = ['verbal' => 'sp-badge-warning', 'escrita' => 'sp-badge-danger', 'suspensao' => 'sp-badge-neutral'];
                $wType = $warning->warning_type ?? '';

                $statusLabels = ['pendente-assinatura' => 'Pendente', 'assinado' => 'Assinado', 'recusado' => 'Recusado'];
                $statusBadges = ['pendente-assinatura' => 'sp-badge-warning', 'assinado' => 'sp-badge-success', 'recusado' => 'sp-badge-danger'];
                $wStatus = $warning->status ?? '';
            ?>

            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(220,53,69,.12);color:#dc3545;"><i class="bi bi-file-earmark-text-fill"></i></span>
                        Resumo da advertência
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-muted small">Colaborador</div>
                            <div class="fw-semibold"><?= esc($warningEmployee->name ?? '—') ?></div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Tipo</div>
                            <span class="sp-badge <?= esc($typeBadges[$wType] ?? 'sp-badge-neutral') ?>">
                                <?= esc($typeLabels[$wType] ?? (ucfirst($wType) ?: '—')) ?>
                            </span>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted small">Status</div>
                            <span class="sp-badge <?= esc($statusBadges[$wStatus] ?? 'sp-badge-neutral') ?>">
                                <?= esc($statusLabels[$wStatus] ?? (ucfirst($wStatus) ?: '—')) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <div class="text-muted small">Data da ocorrência</div>
                            <div class="fw-semibold"><?= !empty($warning->occurrence_date) ? esc(format_date_br((string) $warning->occurrence_date)) : '—' ?></div>
                        </div>
                        <div class="col-12">
                            <div class="text-muted small">Motivo</div>
                            <div style="white-space:pre-line;"><?= esc($warning->reason ?? '—') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($wStatus === 'pendente-assinatura'): ?>
                <div class="alert alert-warning d-flex align-items-center justify-content-between gap-3 flex-wrap mb-0">
                    <div>
                        <div class="fw-semibold"><i class="bi bi-pen-fill me-1"></i>Aguardando assinatura de <?= esc($warningEmployee->name ?? 'colaborador') ?></div>
                        <div class="small">O colaborador precisa acessar o sistema para assinar a advertência.</div>
                    </div>
                    <div class="d-flex gap-2 flex-shrink-0">
                        <a href="<?= site_url(route_to('warnings.sign.form', $warning->id ?? 0)) ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Abrir formulário
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-copy-sign-link" data-url="<?= esc(site_url(route_to('warnings.sign.form', $warning->id ?? 0))) ?>">
                            <i class="bi bi-clipboard me-1"></i>Copiar link
                        </button>
                    </div>
                </div>
            <?php elseif ($wStatus === 'recusado'): ?>
                <div class="alert alert-danger mb-0">
                    <div class="fw-semibold"><i class="bi bi-x-circle-fill me-1"></i>Assinatura recusada pelo colaborador</div>
                    <div class="small">O colaborador recusou assinar a advertência. Documente o incidente e, se necessário, acione o RH para procedimento alternativo.</div>
                </div>
            <?php endif; ?>

            <?php if (!empty($canAddWitness) && in_array($employee['role'] ?? '', ['admin', 'gestor', 'rh'])): ?>
                <div class="alert alert-info d-flex align-items-center justify-content-between gap-3 flex-wrap mb-0">
                    <div>
                        <div class="fw-semibold"><i class="bi bi-person-plus-fill me-1"></i>Mais de 48 horas sem assinatura</div>
                        <div class="small">É possível formalizar a ocorrência com 2 testemunhas.</div>
                    </div>
                    <a href="<?= sp_warning_witness_form_url((int) $warning->id) ?>" class="btn btn-sm btn-primary flex-shrink-0">
                        <i class="bi bi-person-plus me-1"></i>Adicionar testemunhas
                    </a>
                </div>
            <?php endif; ?>

            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-paperclip"></i></span>
                        Evidências
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <?php if (empty($attachments ?? [])): ?>
                        <div class="sp-empty">
                            <div class="sp-empty-icon"><i class="bi bi-file-earmark-x"></i></div>
                            <p class="sp-empty-title">Nenhuma evidência anexada</p>
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
                            <?php foreach (($attachments ?? []) as $attachment): ?>
                                <div class="col">
                                    <a href="<?= sp_safe_url($attachment['url'] ?? '#') ?>" target="_blank" class="warning-evidence-tile">
                                        <?php if (($attachment['type'] ?? '') === 'image'): ?>
                                            <img src="<?= sp_safe_url($attachment['url'] ?? '#') ?>" alt="<?= sp_attr($attachment['name'] ?? 'Anexo') ?>" class="warning-evidence-tile__thumb">
                                        <?php else: ?>
                                            <?php
                                                $ext = $attachment['ext'] ?? '';
                                                $icon = match (true) {
                                                    $ext === 'pdf' => 'bi-file-earmark-pdf-fill text-danger',
                                                    in_array($ext, ['doc', 'docx'], true) => 'bi-file-earmark-word-fill text-primary',
                                                    default => 'bi-file-earmark-fill text-secondary',
                                                };
                                            ?>
                                            <i class="bi <?= esc($icon) ?> warning-evidence-tile__icon"></i>
                                        <?php endif; ?>
                                        <strong class="warning-evidence-tile__name"><?= esc($attachment['name'] ?? 'Anexo') ?></strong>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="sp-data-card">
                <div class="sp-data-card__header">
                    <h2 class="sp-data-card__title">
                        <span style="width:2.1rem;height:2.1rem;border-radius:.5rem;display:inline-flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;background:rgba(13,110,253,.12);color:#0d6efd;"><i class="bi bi-clock-history"></i></span>
                        Andamento
                    </h2>
                </div>
                <div class="sp-data-card__body">
                    <div class="sp-warning-timeline">
                        <div class="sp-warning-timeline__item">
                            <div class="sp-warning-timeline__dot" style="background:var(--sp-primary-dark);border-color:var(--sp-primary-dark);"></div>
                            <div>
                                <strong class="d-block small">Advertência emitida</strong>
                                <span class="text-muted small"><?= esc(format_datetime_br((string) ($warning->created_at ?? ''), false)) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($warning->employee_signed_at ?? null)): ?>
                        <div class="sp-warning-timeline__item">
                            <div class="sp-warning-timeline__dot" style="background:var(--sp-success);border-color:var(--sp-success);"></div>
                            <div>
                                <strong class="d-block small">Assinado por <?= esc($warningEmployee->name ?? 'colaborador') ?></strong>
                                <span class="text-muted small"><?= esc(format_datetime_br((string) $warning->employee_signed_at, false)) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php foreach (($witnesses ?? []) as $witness): ?>
                        <div class="sp-warning-timeline__item">
                            <div class="sp-warning-timeline__dot" style="background:var(--sp-info);border-color:var(--sp-info);"></div>
                            <div>
                                <strong class="d-block small">Testemunha: <?= esc($witness->witness_name ?? '—') ?></strong>
                                <span class="text-muted small"><?= esc(format_datetime_br((string) ($witness->created_at ?? ''), false)) ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if ($wStatus === 'pendente-assinatura'): ?>
                        <div class="sp-warning-timeline__item sp-warning-timeline__item--last">
                            <div class="sp-warning-timeline__dot" style="background:var(--sp-warning);border-color:var(--sp-warning);"></div>
                            <div>
                                <strong class="d-block small">Aguardando assinatura</strong>
                                <span class="text-muted small"><?= esc($warningEmployee->name ?? 'Colaborador') ?> ainda não assinou</span>
                            </div>
                        </div>
                        <?php elseif ($wStatus === 'recusado'): ?>
                        <div class="sp-warning-timeline__item sp-warning-timeline__item--last">
                            <div class="sp-warning-timeline__dot" style="background:var(--sp-danger);border-color:var(--sp-danger);"></div>
                            <div>
                                <strong class="d-block small text-danger">Recusado pelo colaborador</strong>
                                <span class="text-muted small">Contate o RH para procedimento</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($warning->pdf_path)): ?>
                        <div class="mt-3 pt-3 border-top">
                            <a href="<?= sp_warning_download_url((int) $warning->id) ?>" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-download me-1"></i>Baixar PDF
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.sp-warning-timeline { display: flex; flex-direction: column; position: relative; }
.sp-warning-timeline__item { display: flex; gap: .75rem; align-items: flex-start; position: relative; padding-bottom: 1.25rem; }
.sp-warning-timeline__item--last { padding-bottom: 0; }
.sp-warning-timeline__item:not(:last-child)::before {
    content: ''; position: absolute; left: 9px; top: 20px; bottom: -1.25rem; width: 2px; background: var(--sp-border);
}
.sp-warning-timeline__dot { width: 20px; height: 20px; border-radius: 50%; border: 2px solid; flex-shrink: 0; margin-top: 2px; position: relative; z-index: 1; }
.warning-evidence-tile { display: flex; flex-direction: column; align-items: center; padding: .75rem; border: 1px solid var(--sp-border); border-radius: var(--sp-radius-sm); text-decoration: none; color: var(--sp-text-primary); transition: background .15s ease; height: 100%; }
.warning-evidence-tile:hover { background: var(--sp-gray-100); }
.warning-evidence-tile__thumb { width: 100%; height: 80px; object-fit: cover; border-radius: var(--sp-radius-sm); margin-bottom: .5rem; }
.warning-evidence-tile__icon { font-size: 2.5rem; margin-bottom: .5rem; }
.warning-evidence-tile__name { font-size: .8rem; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    var btn = document.getElementById('btn-copy-sign-link');
    if (!btn) return;

    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-url');
        navigator.clipboard.writeText(url).then(function () {
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-clipboard-check me-1"></i>Copiado!';
            setTimeout(function () { btn.innerHTML = orig; }, 2000);
        }).catch(function () {
            prompt('Copie o link manualmente:', url);
        });
    });
})();
</script>
<?= $this->endSection() ?>
