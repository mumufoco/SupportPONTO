<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Advertencia #<?= esc($warning->id ?? '') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Detalhes da advertencia #' . esc((string) ($warning->id ?? '')),
        'subtitle' => 'Consulte dados da ocorrencia, status de assinatura, evidencias e historico relacionado.',
        'icon'     => 'bi bi-exclamation-triangle-fill',
        'actions'  => [
            ['label' => 'Voltar para lista', 'icon' => 'bi bi-arrow-left-circle', 'url' => sp_warning_index_url()],
            ['label' => 'Nova advertencia',  'icon' => 'bi bi-plus-circle',       'url' => sp_warning_create_url()],
        ],
    ]) ?>

    <div class="sp-warning-show-grid"
         style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;">

        <!-- Coluna principal -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">

            <!-- Resumo da advertencia -->
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5 class="sp-card-title">
                        <i class="bi bi-file-earmark-text-fill"></i>Resumo da advertencia
                    </h5>
                </div>
                <div class="sp-card-body">
                    <div class="sp-info-list">
                        <div class="sp-info-item">
                            <span class="sp-info-label">Funcionario</span>
                            <span class="sp-info-value"><?= esc($warningEmployee->name ?? '-') ?></span>
                        </div>
                        <div class="sp-info-item">
                            <span class="sp-info-label">Tipo</span>
                            <span class="sp-info-value">
                                <?php
                                $typeLabels = ['verbal' => 'Verbal', 'escrita' => 'Escrita', 'suspensao' => 'Suspensao'];
                                $typeBadges = ['verbal' => 'sp-badge-warning', 'escrita' => 'sp-badge-danger', 'suspensao' => 'sp-badge-neutral'];
                                $wType = $warning->warning_type ?? '';
                                ?>
                                <span class="sp-badge <?= esc($typeBadges[$wType] ?? 'sp-badge-neutral') ?>">
                                    <?= esc($typeLabels[$wType] ?? ucfirst($wType) ?: '-') ?>
                                </span>
                            </span>
                        </div>
                        <div class="sp-info-item">
                            <span class="sp-info-label">Data da ocorrencia</span>
                            <span class="sp-info-value">
                                <?= !empty($warning->occurrence_date) ? date('d/m/Y', strtotime($warning->occurrence_date)) : '-' ?>
                            </span>
                        </div>
                        <div class="sp-info-item">
                            <span class="sp-info-label">Status</span>
                            <span class="sp-info-value">
                                <?php
                                $statusBadges = [
                                    'pendente-assinatura' => ['class' => 'sp-badge-warning', 'label' => 'Pendente'],
                                    'assinado'            => ['class' => 'sp-badge-success', 'label' => 'Assinado'],
                                    'recusado'            => ['class' => 'sp-badge-danger',  'label' => 'Recusado'],
                                ];
                                $wStatus = $warning->status ?? '';
                                $sData   = $statusBadges[$wStatus] ?? ['class' => 'sp-badge-neutral', 'label' => ucfirst($wStatus) ?: '-'];
                                ?>
                                <span class="sp-badge <?= esc($sData['class']) ?>"><?= esc($sData['label']) ?></span>
                            </span>
                        </div>
                        <div class="sp-info-item">
                            <span class="sp-info-label">Motivo</span>
                            <span class="sp-info-value" style="white-space:pre-line;">
                                <?= esc($warning->reason ?? '-') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerta de assinatura pendente -->
            <?php if (($warning->status ?? '') === 'pendente-assinatura'): ?>
            <div class="sp-card" style="border-left:4px solid var(--sp-warning);">
                <div class="sp-card-body" style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                    <div>
                        <div style="font-weight:600;color:var(--sp-text-primary);">
                            <i class="bi bi-pen-fill me-1 text-warning"></i>
                            Aguardando assinatura de <strong><?= esc($warningEmployee->name ?? 'colaborador') ?></strong>
                        </div>
                        <div style="font-size:.8rem;color:var(--sp-text-muted);margin-top:.25rem;">
                            O colaborador precisa acessar o sistema para assinar a advertencia.
                        </div>
                    </div>
                    <div style="display:flex;gap:.5rem;flex-shrink:0;">
                        <a href="<?= site_url(route_to('warnings.sign.form', $warning->id ?? 0)) ?>"
                           class="sp-btn sp-btn-sm sp-btn-outline"
                           target="_blank">
                            <i class="bi bi-box-arrow-up-right"></i> Abrir formulario
                        </a>
                        <button type="button"
                                class="sp-btn sp-btn-sm sp-btn-outline"
                                id="btn-copy-sign-link"
                                onclick="copySignLink(this)"
                                data-url="<?= esc(site_url(route_to('warnings.sign.form', $warning->id ?? 0))) ?>">
                            <i class="bi bi-clipboard"></i> Copiar link
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Alerta de recusa -->
            <?php if (($warning->status ?? '') === 'recusado'): ?>
            <div class="sp-card" style="border-left:4px solid var(--sp-danger);">
                <div class="sp-card-body">
                    <div style="font-weight:600;color:var(--sp-danger);">
                        <i class="bi bi-x-circle-fill me-1"></i>
                        Assinatura recusada pelo colaborador
                    </div>
                    <div style="font-size:.85rem;color:var(--sp-text-muted);margin-top:.35rem;">
                        O colaborador recusou assinar a advertencia. Documente o incidente e, se necessario, acione o RH para procedimento alternativo.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Evidencias -->
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5 class="sp-card-title">
                        <i class="bi bi-paperclip"></i>Evidencias
                    </h5>
                </div>
                <div class="sp-card-body">
                    <?php if (empty($attachments ?? [])): ?>
                        <div class="sp-empty">
                            <div class="sp-empty-icon"><i class="bi bi-file-earmark-x"></i></div>
                            <p class="sp-empty-title">Nenhuma evidencia anexada</p>
                        </div>
                    <?php else: ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;">
                            <?php foreach (($attachments ?? []) as $attachment): ?>
                                <a href="<?= sp_safe_url($attachment['url'] ?? '#') ?>" target="_blank"
                                   style="display:flex;flex-direction:column;align-items:center;padding:.75rem;border:1px solid var(--sp-border);border-radius:var(--sp-radius-sm);text-decoration:none;color:var(--sp-text-primary);transition:background .2s;"
                                   onmouseover="this.style.background='var(--sp-gray-100)'"
                                   onmouseout="this.style.background=''">
                                    <?php if (($attachment['type'] ?? '') === 'image'): ?>
                                        <img src="<?= sp_safe_url($attachment['url'] ?? '#') ?>"
                                             alt="<?= sp_attr($attachment['name'] ?? 'Anexo') ?>"
                                             style="width:100%;height:80px;object-fit:cover;border-radius:var(--sp-radius-sm);margin-bottom:.5rem;">
                                    <?php else: ?>
                                        <i class="bi bi-file-earmark-pdf-fill"
                                           style="font-size:2.5rem;color:var(--sp-danger);margin-bottom:.5rem;"></i>
                                    <?php endif; ?>
                                    <strong style="font-size:.8rem;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">
                                        <?= esc($attachment['name'] ?? 'Anexo') ?>
                                    </strong>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Coluna lateral: andamento -->
        <div>
            <div class="sp-card">
                <div class="sp-card-header">
                    <h5 class="sp-card-title">
                        <i class="bi bi-clock-history"></i>Andamento
                    </h5>
                </div>
                <div class="sp-card-body">
                    <div style="display:flex;flex-direction:column;gap:0;position:relative;">
                        <div style="position:absolute;left:10px;top:8px;bottom:8px;width:2px;background:var(--sp-border);z-index:0;"></div>

                        <div style="display:flex;gap:.75rem;align-items:flex-start;position:relative;z-index:1;padding-bottom:1.25rem;">
                            <div style="width:20px;height:20px;border-radius:50%;background:var(--sp-primary-dark);border:2px solid var(--sp-primary-dark);flex-shrink:0;margin-top:2px;"></div>
                            <div>
                                <strong style="font-size:.875rem;color:var(--sp-text-primary);">Advertencia emitida</strong>
                                <div style="font-size:.8rem;color:var(--sp-text-muted);margin-top:.125rem;">
                                    <?= esc($warning->created_at ?? '-') ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($warning->employee_signed_at ?? null)): ?>
                        <div style="display:flex;gap:.75rem;align-items:flex-start;position:relative;z-index:1;padding-bottom:1.25rem;">
                            <div style="width:20px;height:20px;border-radius:50%;background:var(--sp-success);border:2px solid var(--sp-success);flex-shrink:0;margin-top:2px;"></div>
                            <div>
                                <strong style="font-size:.875rem;color:var(--sp-text-primary);">Assinado por <?= esc($warningEmployee->name ?? 'colaborador') ?></strong>
                                <div style="font-size:.8rem;color:var(--sp-text-muted);margin-top:.125rem;">
                                    <?= esc($warning->employee_signed_at) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($warning->witness_signed_at ?? null)): ?>
                        <div style="display:flex;gap:.75rem;align-items:flex-start;position:relative;z-index:1;">
                            <div style="width:20px;height:20px;border-radius:50%;background:var(--sp-info);border:2px solid var(--sp-info);flex-shrink:0;margin-top:2px;"></div>
                            <div>
                                <strong style="font-size:.875rem;color:var(--sp-text-primary);">Testemunha: <?= esc($warning->witness_name ?? '-') ?></strong>
                                <div style="font-size:.8rem;color:var(--sp-text-muted);margin-top:.125rem;">
                                    <?= esc($warning->witness_signed_at) ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (($warning->status ?? '') === 'pendente-assinatura'): ?>
                        <div style="display:flex;gap:.75rem;align-items:flex-start;position:relative;z-index:1;">
                            <div style="width:20px;height:20px;border-radius:50%;background:var(--sp-warning);border:2px solid var(--sp-warning);flex-shrink:0;margin-top:2px;"></div>
                            <div>
                                <strong style="font-size:.875rem;color:var(--sp-text-primary);">Aguardando assinatura</strong>
                                <div style="font-size:.8rem;color:var(--sp-text-muted);margin-top:.125rem;">
                                    <?= esc($warningEmployee->name ?? 'Colaborador') ?> ainda nao assinou
                                </div>
                            </div>
                        </div>
                        <?php elseif (($warning->status ?? '') === 'recusado'): ?>
                        <div style="display:flex;gap:.75rem;align-items:flex-start;position:relative;z-index:1;">
                            <div style="width:20px;height:20px;border-radius:50%;background:var(--sp-danger);border:2px solid var(--sp-danger);flex-shrink:0;margin-top:2px;"></div>
                            <div>
                                <strong style="font-size:.875rem;color:var(--sp-danger);">Recusado pelo colaborador</strong>
                                <div style="font-size:.8rem;color:var(--sp-text-muted);margin-top:.125rem;">Contate o RH para procedimento</div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($warning->pdf_path)): ?>
                        <div style="margin-top:1.25rem;border-top:1px solid var(--sp-border);padding-top:1rem;">
                            <a href="<?= sp_warning_download_url((int) $warning->id) ?>"
                               class="sp-btn sp-btn-outline sp-btn-full">
                                <i class="bi bi-download"></i> Baixar PDF
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
    const grid = document.querySelector('.sp-warning-show-grid');
    function adjustGrid() {
        if (grid) grid.style.gridTemplateColumns = window.innerWidth < 768 ? '1fr' : '1fr 300px';
    }
    adjustGrid();
    window.addEventListener('resize', adjustGrid);

    function copySignLink(btn) {
        const url = btn.getAttribute('data-url');
        navigator.clipboard.writeText(url).then(function() {
            const orig = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copiado!';
            btn.style.color = 'var(--sp-success)';
            setTimeout(function() { btn.innerHTML = orig; btn.style.color = ''; }, 2000);
        }).catch(function() {
            prompt('Copie o link manualmente:', url);
        });
    }
</script>
<?= $this->endSection() ?>
