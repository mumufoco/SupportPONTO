<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Advertências — <?= esc($targetEmployee->name ?? '') ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Advertências — ' . esc($targetEmployee->name ?? ''),
        'subtitle' => 'Histórico completo de advertências, resumo por tipo e linha do tempo de ocorrências.',
        'icon'     => 'bi bi-exclamation-triangle-fill',
    ]) ?>

    <?php if ($atLimit): ?>
        <div class="sp-alert sp-alert-danger">
            <i class="bi bi-exclamation-octagon-fill"></i>
            <div>
                <strong>ATENÇÃO — LIMITE ATINGIDO!</strong><br>
                <span style="font-size:.875rem;">
                    Este funcionário já recebeu <strong>3 advertências</strong>.
                    Qualquer nova advertência pode resultar em medidas mais severas, incluindo possível demissão por justa causa.
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="sp-grid-4">
        <div class="sp-card" style="text-align:center;padding:1.25rem;">
            <div style="font-size:2rem;font-weight:700;color:<?= $totalWarnings >= 3 ? 'var(--sp-danger)' : 'var(--sp-warning)' ?>;line-height:1;">
                <?= esc((string) $totalWarnings) ?>/3
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.375rem;">
                Total de Advertências
            </div>
            <?php if ($totalWarnings >= 3): ?>
                <span class="sp-badge sp-badge-danger" style="margin-top:.5rem;">Limite atingido</span>
            <?php endif; ?>
        </div>
        <div class="sp-card" style="text-align:center;padding:1.25rem;">
            <div style="font-size:2rem;font-weight:700;color:var(--sp-warning);line-height:1;">
                <?= (int) ($warningsByType['verbal'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.375rem;">
                Verbais
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1.25rem;">
            <div style="font-size:2rem;font-weight:700;color:var(--sp-danger);line-height:1;">
                <?= (int) ($warningsByType['escrita'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.375rem;">
                Escritas
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1.25rem;">
            <div style="font-size:2rem;font-weight:700;color:var(--sp-text-primary);line-height:1;">
                <?= (int) ($warningsByType['suspensao'] ?? 0) ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.375rem;">
                Suspensões
            </div>
        </div>
    </div>

    <!-- Linha do Tempo -->
    <div class="sp-card">
        <div class="sp-card-header">
            <h5 class="sp-card-title">
                <i class="bi bi-clock-history"></i>Linha do Tempo
            </h5>
        </div>
        <div class="sp-card-body">
            <?php if (empty($timeline)): ?>
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-check-circle-fill" style="color:var(--sp-success);"></i></div>
                    <p class="sp-empty-title">Nenhuma advertência registrada</p>
                    <p class="sp-empty-text">Este colaborador não possui advertências no histórico.</p>
                </div>
            <?php else: ?>
                <!-- Timeline -->
                <div style="position:relative;padding-left:2rem;">
                    <!-- Linha vertical -->
                    <div style="position:absolute;left:9px;top:8px;bottom:8px;width:2px;background:var(--sp-border);"></div>

                    <?php foreach ($timeline as $item): ?>
                        <?php
                        $dotColor = match($item['type'] ?? '') {
                            'verbal'    => 'var(--sp-warning)',
                            'escrita'   => 'var(--sp-danger)',
                            'suspensao' => 'var(--sp-text-primary)',
                            default     => 'var(--sp-gray-400)',
                        };
                        $typeBadges = [
                            'verbal'    => '<span class="sp-badge sp-badge-warning">VERBAL</span>',
                            'escrita'   => '<span class="sp-badge sp-badge-danger">ESCRITA</span>',
                            'suspensao' => '<span class="sp-badge sp-badge-neutral">SUSPENSÃO</span>',
                        ];
                        ?>
                        <div style="position:relative;margin-bottom:1.25rem;">
                            <!-- Marcador -->
                            <div style="position:absolute;left:-2rem;top:6px;width:18px;height:18px;border-radius:50%;background:<?= $dotColor ?>;border:2px solid var(--sp-bg-surface);"></div>

                            <!-- Card da advertência -->
                            <div class="sp-card" style="margin-left:.25rem;">
                                <div class="sp-card-body" style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;">
                                    <div>
                                        <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.35rem;">
                                            <?= $typeBadges[$item['type'] ?? ''] ?? '' ?>
                                            <span style="font-size:.875rem;color:var(--sp-text-secondary);">
                                                <?= esc(date('d/m/Y', strtotime((string) $item['date']))) ?>
                                            </span>
                                        </div>
                                        <p style="margin:0 0 .375rem;font-size:.8125rem;color:var(--sp-text-secondary);">
                                            <?= esc($item['reason_preview'] ?? '') ?>
                                        </p>
                                        <?php if (!empty($item['signed_at'])): ?>
                                            <span style="font-size:.8rem;color:var(--sp-success);">
                                                <i class="bi bi-check-circle-fill"></i>
                                                Assinado em <?= esc(date('d/m/Y', strtotime((string) $item['signed_at']))) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="font-size:.8rem;color:var(--sp-warning);">
                                                <i class="bi bi-clock-fill"></i>
                                                <?= $item['status'] === 'pendente-assinatura' ? 'Aguardando assinatura' : esc(ucfirst((string) ($item['status'] ?? ''))) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex-shrink:0;">
                                        <a href="<?= sp_safe_url(sp_warning_show_url((int) $item['id'])) ?>"
                                           class="sp-btn sp-btn-sm sp-btn-secondary">
                                            <i class="bi bi-eye-fill"></i> Ver
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

