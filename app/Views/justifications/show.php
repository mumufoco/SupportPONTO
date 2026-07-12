<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Justificativa #<?= esc($justification->id ?? '') ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
/* ── Status hero ────────────────────────────────── */
.sp-just-hero {
    display: flex; align-items: center; gap: 1.25rem;
    padding: 1.25rem 1.5rem;
    border-radius: var(--sp-radius-lg);
    border: 1px solid var(--sp-border);
    margin-bottom: 1.5rem;
    background: var(--sp-bg-surface);
    box-shadow: var(--sp-shadow-sm);
}
.sp-just-hero__icon {
    flex-shrink: 0;
    width: 52px; height: 52px;
    border-radius: var(--sp-radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
}
.sp-just-hero__body { flex: 1; min-width: 0; }
.sp-just-hero__id   { font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--sp-text-muted); margin-bottom: .2rem; }
.sp-just-hero__title { font-size: 1.2rem; font-weight: 700; color: var(--sp-text-primary); margin: 0 0 .35rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sp-just-hero__meta  { display: flex; flex-wrap: wrap; gap: .5rem .75rem; align-items: center; }
.sp-just-hero__actions { display: flex; gap: .5rem; flex-shrink: 0; }
.sp-just-hero--pending  .sp-just-hero__icon { background: var(--sp-warning-light); color: var(--sp-warning); }
.sp-just-hero--approved .sp-just-hero__icon { background: var(--sp-success-light); color: var(--sp-success); }
.sp-just-hero--rejected .sp-just-hero__icon { background: var(--sp-danger-light);  color: var(--sp-danger);  }

/* ── Info grid ──────────────────────────────────── */
.sp-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
    gap: .75rem;
    margin-bottom: 1.25rem;
}
.sp-info-cell {
    padding: .75rem 1rem;
    background: var(--sp-gray-100);
    border-radius: var(--sp-radius-md);
    border: 1px solid var(--sp-border);
}
.sp-info-cell__label {
    font-size: .67rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .07em; color: var(--sp-text-muted); margin-bottom: .3rem;
    display: flex; align-items: center; gap: .3rem;
}
.sp-info-cell__value {
    font-size: .9rem; font-weight: 600; color: var(--sp-text-primary);
    word-break: break-word;
}
.sp-info-cell__value.muted { color: var(--sp-text-muted); font-weight: 400; font-size: .82rem; }

/* ── Reason block ───────────────────────────────── */
.sp-reason-block {
    background: var(--sp-gray-100);
    border-left: 4px solid var(--sp-primary);
    border-radius: 0 var(--sp-radius-md) var(--sp-radius-md) 0;
    padding: 1rem 1.25rem;
    font-size: .9rem;
    color: var(--sp-text-primary);
    line-height: 1.6;
    white-space: pre-line;
}

/* ── Timeline ───────────────────────────────────── */
.sp-tl { display: flex; flex-direction: column; padding-left: .5rem; }
.sp-tl__item {
    position: relative;
    padding: .6rem .75rem .6rem 1.5rem;
    border-left: 2px solid var(--sp-border);
}
.sp-tl__item:last-child { border-left-color: transparent; }
.sp-tl__item::before {
    content: "";
    position: absolute;
    left: -6px; top: .85rem;
    width: 10px; height: 10px;
    border-radius: 50%;
    background: var(--sp-primary);
    border: 2px solid var(--sp-bg-surface);
    box-shadow: 0 0 0 1px var(--sp-primary);
}
.sp-tl__item--muted::before { background: var(--sp-gray-300); box-shadow: 0 0 0 1px var(--sp-gray-300); }
.sp-tl__label { font-size: .85rem; font-weight: 600; color: var(--sp-text-primary); }
.sp-tl__date  { font-size: .77rem; color: var(--sp-text-muted); margin-top: .15rem; }
.sp-tl__note  { font-size: .82rem; color: var(--sp-text-secondary); margin-top: .3rem; font-style: italic; background: var(--sp-gray-100); padding: .4rem .6rem; border-radius: var(--sp-radius-sm); }

/* ── Attachment item ────────────────────────────── */
.sp-attach-item {
    display: flex; align-items: center; gap: .75rem;
    padding: .65rem .85rem;
    background: var(--sp-gray-100);
    border: 1px solid var(--sp-border);
    border-radius: var(--sp-radius-md);
    text-decoration: none !important;
    color: var(--sp-text-primary);
    transition: background .15s, border-color .15s;
    margin-bottom: .5rem;
}
.sp-attach-item:hover { background: var(--sp-primary-soft); border-color: var(--sp-primary); }
.sp-attach-item__icon { font-size: 1.5rem; flex-shrink: 0; }
.sp-attach-item__name { font-size: .85rem; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* ── Reviewer chip ──────────────────────────────── */
.sp-reviewer-chip {
    display: flex; align-items: center; gap: .75rem;
    padding: .75rem 1rem;
    background: var(--sp-gray-100);
    border-radius: var(--sp-radius-md);
    border: 1px solid var(--sp-border);
}
.sp-reviewer-chip__avatar {
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--sp-primary-light);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .9rem; color: var(--sp-primary-dark);
    flex-shrink: 0;
}
.sp-reviewer-chip__name { font-size: .9rem; font-weight: 600; color: var(--sp-text-primary); }
.sp-reviewer-chip__role { font-size: .74rem; color: var(--sp-text-muted); margin-top: .05rem; }

/* ── Action panel ───────────────────────────────── */
.sp-action-panel {
    border: 1px solid var(--sp-warning-light);
    border-radius: var(--sp-radius-lg);
    overflow: hidden;
    margin-bottom: 1.25rem;
    background: var(--sp-bg-surface);
    box-shadow: var(--sp-shadow-sm);
}
.sp-action-panel__header {
    padding: .75rem 1.25rem;
    background: var(--sp-warning-light);
    display: flex; align-items: center; gap: .5rem;
    font-weight: 600; font-size: .88rem; color: var(--sp-warning);
    border-bottom: 1px solid rgba(240,196,63,.25);
}
.sp-action-panel__body { padding: 1.25rem; }
.sp-input-sm { height: 36px; font-size: .85rem; }

@media (max-width: 767px) {
    .sp-just-hero { flex-wrap: wrap; }
    .sp-just-hero__actions { width: 100%; }
    .sp-info-grid { grid-template-columns: 1fr 1fr; }
}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$statusRaw = strtolower((string) ($justification->status ?? 'pendente'));
$statusMap = [
    'pendente'  => ['label' => 'Pendente',  'badge' => 'sp-badge-warning', 'hero' => 'pending',  'icon' => 'bi-hourglass-split'],
    'aprovado'  => ['label' => 'Aprovada',  'badge' => 'sp-badge-success', 'hero' => 'approved', 'icon' => 'bi-check-circle-fill'],
    'rejeitado' => ['label' => 'Rejeitada', 'badge' => 'sp-badge-danger',  'hero' => 'rejected', 'icon' => 'bi-x-circle-fill'],
];
$statusInfo = $statusMap[$statusRaw] ?? ['label' => ucfirst($statusRaw), 'badge' => 'sp-badge-neutral', 'hero' => 'pending', 'icon' => 'bi-question-circle'];

$typeRaw  = (string) ($justification->justification_type ?? $justification->type ?? '');
$typeMap  = [
    'falta'            => ['label' => 'Falta',            'icon' => 'bi-calendar-x',      'badge' => 'sp-badge-danger'],
    'atraso'           => ['label' => 'Atraso',           'icon' => 'bi-clock',            'badge' => 'sp-badge-warning'],
    'saida-antecipada' => ['label' => 'Saída Antecipada', 'icon' => 'bi-box-arrow-right',  'badge' => 'sp-badge-neutral'],
];
$typeInfo = $typeMap[$typeRaw] ?? ['label' => ($typeRaw ?: '-'), 'icon' => 'bi-tag', 'badge' => 'sp-badge-neutral'];

$catMap = [
    'doenca'              => 'Doença',
    'compromisso-pessoal' => 'Compromisso Pessoal',
    'emergencia-familiar' => 'Emergência Familiar',
    'outro'               => 'Outro',
];
$categoryLabel = $catMap[$justification->category ?? ''] ?? ($justification->category ?? '-');

$jDate   = $justification->justification_date ?? $justification->date ?? null;
$jDateBr = $jDate ? date('d/m/Y', strtotime((string) $jDate)) : '-';

$createdAt  = $justification->created_at  ?? null;
$reviewedAt = $justification->reviewed_at ?? null;
$updatedAt  = $justification->updated_at  ?? null;

$reviewerName     = $reviewer->name ?? null;
$reviewerInitials = $reviewerName
    ? implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice(explode(' ', $reviewerName), 0, 2)))
    : null;

$isPending  = $statusRaw === 'pendente';
$isApproved = $statusRaw === 'aprovado';
$isRejected = $statusRaw === 'rejeitado';

$empName     = $justificationEmployee->name ?? null;
$empInitials = $empName
    ? implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice(explode(' ', $empName), 0, 2)))
    : '?';
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Justificativa #' . esc($justification->id ?? ''),
        'subtitle' => 'Detalhes completos, histórico de revisão e ações disponíveis.',
        'icon'     => 'bi bi-file-earmark-text-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left-circle', 'url' => site_url('justifications')],
            ['label' => 'Nova', 'icon' => 'bi bi-plus-circle', 'url' => site_url('justifications/create')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- Hero de status -->
    <div class="sp-just-hero sp-just-hero--<?= esc($statusInfo['hero']) ?>">
        <div class="sp-just-hero__icon">
            <i class="bi <?= esc($statusInfo['icon']) ?>"></i>
        </div>
        <div class="sp-just-hero__body">
            <div class="sp-just-hero__id">Justificativa #<?= esc($justification->id ?? '') ?></div>
            <div class="sp-just-hero__title"><?= esc($empName ?? 'Colaborador') ?></div>
            <div class="sp-just-hero__meta">
                <span class="sp-badge <?= esc($statusInfo['badge']) ?>">
                    <i class="bi <?= esc($statusInfo['icon']) ?>"></i>
                    <?= esc($statusInfo['label']) ?>
                </span>
                <?php if ($typeRaw): ?>
                <span class="sp-badge <?= esc($typeInfo['badge']) ?>">
                    <i class="bi <?= esc($typeInfo['icon']) ?>"></i>
                    <?= esc($typeInfo['label']) ?>
                </span>
                <?php endif; ?>
                <?php if ($jDate): ?>
                <span style="font-size:.79rem;color:var(--sp-text-muted);">
                    <i class="bi bi-calendar3"></i> <?= esc($jDateBr) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="sp-just-hero__actions">
            <a href="<?= site_url('justifications') ?>" class="sp-btn sp-btn-secondary sp-btn-sm">
                <i class="bi bi-list-ul"></i> Ver todas
            </a>
        </div>
    </div>

    <div class="sp-profile-grid">

        <!-- Coluna principal -->
        <div class="span-8">

            <!-- Informações da solicitação -->
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-card-checklist"></i>Informações da solicitação</h2>
                </div>
                <div class="sp-profile-card__body">
                    <div class="sp-info-grid">
                        <div class="sp-info-cell">
                            <div class="sp-info-cell__label"><i class="bi bi-tag"></i>Tipo</div>
                            <div class="sp-info-cell__value">
                                <span class="sp-badge <?= esc($typeInfo['badge']) ?>">
                                    <i class="bi <?= esc($typeInfo['icon']) ?>"></i>
                                    <?= esc($typeInfo['label']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="sp-info-cell">
                            <div class="sp-info-cell__label"><i class="bi bi-folder2"></i>Categoria</div>
                            <div class="sp-info-cell__value"><?= esc($categoryLabel) ?></div>
                        </div>
                        <div class="sp-info-cell">
                            <div class="sp-info-cell__label"><i class="bi bi-calendar3"></i>Data</div>
                            <div class="sp-info-cell__value"><?= esc($jDateBr) ?></div>
                        </div>
                        <div class="sp-info-cell">
                            <div class="sp-info-cell__label"><i class="bi bi-flag"></i>Status</div>
                            <div class="sp-info-cell__value">
                                <span class="sp-badge <?= esc($statusInfo['badge']) ?>">
                                    <i class="bi <?= esc($statusInfo['icon']) ?>"></i>
                                    <?= esc($statusInfo['label']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="sp-info-cell">
                            <div class="sp-info-cell__label"><i class="bi bi-person"></i>Solicitante</div>
                            <div class="sp-info-cell__value"><?= esc($empName ?? '-') ?></div>
                        </div>
                        <div class="sp-info-cell">
                            <div class="sp-info-cell__label"><i class="bi bi-clock-history"></i>Enviada em</div>
                            <div class="sp-info-cell__value muted">
                                <?= $createdAt ? esc(date('d/m/Y H:i', strtotime((string) $createdAt))) : '-' ?>
                            </div>
                        </div>
                    </div>

                    <!-- Motivo detalhado -->
                    <div>
                        <div class="sp-info-cell__label" style="margin-bottom:.5rem;">
                            <i class="bi bi-chat-left-text"></i>Motivo detalhado
                        </div>
                        <div class="sp-reason-block"><?= esc($justification->reason ?? '-') ?></div>
                    </div>
                </div>
            </div>

            <!-- Histórico / Linha do tempo -->
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-clock-history"></i>Histórico</h2>
                </div>
                <div class="sp-profile-card__body" style="padding-top:.5rem;">
                    <div class="sp-tl">
                        <div class="sp-tl__item">
                            <div class="sp-tl__label">
                                <i class="bi bi-send" style="font-size:.75rem;margin-right:.3rem;"></i>Solicitação enviada
                            </div>
                            <div class="sp-tl__date">
                                <?= $createdAt ? esc(date('d/m/Y \à\s H:i', strtotime((string) $createdAt))) : '-' ?>
                            </div>
                        </div>

                        <?php if ($reviewedAt): ?>
                        <div class="sp-tl__item <?= $isRejected ? 'sp-tl__item--muted' : '' ?>">
                            <div class="sp-tl__label">
                                <?php if ($isApproved): ?>
                                    <i class="bi bi-check-circle" style="font-size:.75rem;margin-right:.3rem;color:var(--sp-success);"></i>Aprovada por <?= esc($reviewerName ?? 'revisor') ?>
                                <?php elseif ($isRejected): ?>
                                    <i class="bi bi-x-circle" style="font-size:.75rem;margin-right:.3rem;color:var(--sp-danger);"></i>Rejeitada por <?= esc($reviewerName ?? 'revisor') ?>
                                <?php else: ?>
                                    <i class="bi bi-eye" style="font-size:.75rem;margin-right:.3rem;"></i>Analisada por <?= esc($reviewerName ?? 'revisor') ?>
                                <?php endif; ?>
                            </div>
                            <div class="sp-tl__date"><?= esc(date('d/m/Y \à\s H:i', strtotime((string) $reviewedAt))) ?></div>
                            <?php if (!empty($justification->review_notes)): ?>
                            <div class="sp-tl__note"><?= esc($justification->review_notes) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($updatedAt && $updatedAt !== $createdAt): ?>
                        <div class="sp-tl__item sp-tl__item--muted">
                            <div class="sp-tl__label">
                                <i class="bi bi-pencil" style="font-size:.75rem;margin-right:.3rem;"></i>Última atualização
                            </div>
                            <div class="sp-tl__date"><?= esc(date('d/m/Y \à\s H:i', strtotime((string) $updatedAt))) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div><!-- /span-8 -->

        <!-- Coluna lateral -->
        <div class="span-4">

            <?php if ($isPending): ?>
            <!-- Painel de ação (só pendente) -->
            <div class="sp-action-panel">
                <div class="sp-action-panel__header">
                    <i class="bi bi-hourglass-split"></i> Aguardando revisão
                </div>
                <div class="sp-action-panel__body">
                    <p style="font-size:.83rem;color:var(--sp-text-secondary);margin-bottom:1rem;">
                        Esta solicitação ainda não foi analisada. Aprove ou rejeite abaixo.
                    </p>
                    <form method="POST" action="<?= site_url('justifications/' . (int)($justification->id ?? 0) . '/approve') ?>" style="margin-bottom:.75rem;">
                        <?= csrf_field() ?>
                        <input type="text" name="notes" class="sp-input sp-input-sm" placeholder="Observação (opcional)" style="margin-bottom:.5rem;width:100%;">
                        <button type="submit" class="sp-btn sp-btn-success sp-btn-full">
                            <i class="bi bi-check-circle"></i> Aprovar
                        </button>
                    </form>
                    <form method="POST" action="<?= site_url('justifications/' . (int)($justification->id ?? 0) . '/reject') ?>">
                        <?= csrf_field() ?>
                        <input type="text" name="notes" class="sp-input sp-input-sm" placeholder="Motivo da rejeição (obrigatório)" required style="margin-bottom:.5rem;width:100%;">
                        <button type="submit" class="sp-btn sp-btn-danger sp-btn-full">
                            <i class="bi bi-x-circle"></i> Rejeitar
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isApproved || $isRejected): ?>
            <!-- Card revisor -->
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title">
                        <i class="bi <?= $isApproved ? 'bi-patch-check-fill' : 'bi-patch-exclamation-fill' ?>"
                           style="color:var(--sp-<?= $isApproved ? 'success' : 'danger' ?>);"></i>
                        <?= $isApproved ? 'Aprovada por' : 'Rejeitada por' ?>
                    </h2>
                </div>
                <div class="sp-profile-card__body">
                    <?php if ($reviewerName): ?>
                    <div class="sp-reviewer-chip">
                        <div class="sp-reviewer-chip__avatar"><?= esc($reviewerInitials) ?></div>
                        <div>
                            <div class="sp-reviewer-chip__name"><?= esc($reviewerName) ?></div>
                            <div class="sp-reviewer-chip__role">
                                <?= $reviewedAt ? esc(date('d/m/Y H:i', strtotime((string) $reviewedAt))) : '' ?>
                            </div>
                        </div>
                    </div>
                    <?php if (!empty($justification->review_notes)): ?>
                    <div style="margin-top:.85rem;">
                        <div class="sp-info-cell__label" style="margin-bottom:.35rem;">
                            <i class="bi bi-chat-quote"></i> Observação do revisor
                        </div>
                        <blockquote style="margin:0;padding:.75rem 1rem;background:var(--sp-gray-100);border-left:3px solid var(--sp-border);border-radius:0 var(--sp-radius-sm) var(--sp-radius-sm) 0;font-size:.85rem;color:var(--sp-text-secondary);font-style:italic;">
                            "<?= esc($justification->review_notes) ?>"
                        </blockquote>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="sp-empty-state">Sem informações do revisor.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Anexos -->
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title">
                        <i class="bi bi-paperclip"></i> Anexos
                        <?php if (!empty($attachments)): ?>
                        <span class="sp-badge sp-badge-primary" style="margin-left:auto;"><?= count($attachments) ?></span>
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="sp-profile-card__body">
                    <?php if (empty($attachments)): ?>
                    <div class="sp-empty-state">Nenhum anexo enviado.</div>
                    <?php else: ?>
                        <?php foreach ($attachments as $att): ?>
                        <?php
                        $ext = strtolower(pathinfo($att['name'] ?? '', PATHINFO_EXTENSION));
                        $attIcon  = $ext === 'pdf' ? 'bi-file-pdf-fill' : ($ext === 'png' || $ext === 'jpg' || $ext === 'jpeg' ? 'bi-file-image-fill' : 'bi-file-earmark-fill');
                        $attColor = $ext === 'pdf' ? 'var(--sp-danger)' : ($ext === 'png' || $ext === 'jpg' || $ext === 'jpeg' ? 'var(--sp-primary)' : 'var(--sp-text-muted)');
                        ?>
                        <a href="<?= sp_safe_url($att['url'] ?? '#') ?>" target="_blank" class="sp-attach-item">
                            <i class="bi <?= esc($attIcon) ?> sp-attach-item__icon" style="color:<?= esc($attColor) ?>;"></i>
                            <div style="min-width:0;flex:1;">
                                <div class="sp-attach-item__name"><?= esc($att['name'] ?? 'Anexo') ?></div>
                                <?php if (!empty($att['size'])): ?>
                                <div style="font-size:.7rem;color:var(--sp-text-muted);"><?= esc($att['size']) ?></div>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-box-arrow-up-right" style="font-size:.75rem;color:var(--sp-text-muted);flex-shrink:0;"></i>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Solicitante -->
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-person-circle"></i>Solicitante</h2>
                </div>
                <div class="sp-profile-card__body">
                    <div class="sp-reviewer-chip" style="margin-bottom:.85rem;">
                        <div class="sp-reviewer-chip__avatar"><?= esc($empInitials) ?></div>
                        <div>
                            <div class="sp-reviewer-chip__name"><?= esc($empName ?? '-') ?></div>
                            <div class="sp-reviewer-chip__role"><?= esc($justificationEmployee->email ?? '') ?></div>
                        </div>
                    </div>
                    <?php if (!empty($justificationEmployee->department)): ?>
                    <div class="sp-info-cell" style="margin-bottom:.5rem;">
                        <div class="sp-info-cell__label"><i class="bi bi-building"></i>Departamento</div>
                        <div class="sp-info-cell__value"><?= esc($justificationEmployee->department) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($justificationEmployee->role)): ?>
                    <div class="sp-info-cell">
                        <div class="sp-info-cell__label"><i class="bi bi-person-badge"></i>Função</div>
                        <div class="sp-info-cell__value"><?= esc(ucfirst($justificationEmployee->role)) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /span-4 -->
    </div><!-- /sp-profile-grid -->
</div>
<?= $this->endSection() ?>
