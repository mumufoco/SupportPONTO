<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Detalhes do dia<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Detalhes do dia',
        'subtitle' => 'Analise todos os registros, métodos utilizados e ocorrências do dia selecionado.',
        'icon' => 'bi bi-calendar-day-fill',
        'actions' => [
            ['label' => 'Espelho de ponto', 'icon' => 'bi bi-calendar3', 'url' => sp_timesheet_index_url()],
            ['label' => 'Histórico', 'icon' => 'bi bi-clock-history', 'url' => sp_timesheet_history_url()],
        ],
    ]) ?>

    <div class="sp-profile-grid">
        <div class="span-8">
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-fingerprint"></i>Registros de ponto</h2>
                </div>
                <div class="sp-profile-card__body">
                    <?php if (empty($punches)): ?>
                        <div class="sp-empty-state">Nenhum registro de ponto neste dia.</div>
                    <?php else: ?>
                        <div class="sp-module-stack">
                            <?php foreach ($punches as $punch): ?>
                                <?php
                                $typeColors = [
                                    'entrada' => ['color' => '#5A6B5A', 'icon' => 'bi-box-arrow-in-right'],
                                    'saida' => ['color' => '#E74C3C', 'icon' => 'bi-box-arrow-right'],
                                    'intervalo_inicio' => ['color' => '#F4C542', 'icon' => 'bi-cup-hot-fill'],
                                    'intervalo_fim' => ['color' => '#9DB89D', 'icon' => 'bi-play-circle-fill']
                                ];
                                $config = $typeColors[$punch['type']] ?? ['color' => '#737373', 'icon' => 'bi-dot'];
                                ?>
                                <div class="sp-day-punch">
                                    <div class="row align-items-center g-3">
                                        <div class="col-md-2 text-center">
                                            <div class="sp-type-icon" style="background-color: <?= sp_style_color($config['color']) ?>;">
                                                <i class="bi <?= sp_attr($config['icon']) ?>"></i>
                                            </div>
                                        </div>
                                        <div class="col-md-10">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <small class="text-muted d-block">Tipo</small>
                                                    <strong><?= esc(ucfirst(str_replace('_', ' ', $punch['type']))) ?></strong>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted d-block">Horário</small>
                                                    <strong><?= esc($punch['time']) ?></strong>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted d-block">Método</small>
                                                    <strong><?= esc(ucfirst($punch['method'])) ?></strong>
                                                </div>
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

        <div class="span-4">
            <div class="sp-profile-card">
                <div class="sp-profile-card__header">
                    <h2 class="sp-profile-card__title"><i class="bi bi-info-circle-fill"></i>Resumo do dia</h2>
                </div>
                <div class="sp-profile-card__body">
                    <div class="sp-meta-list">
                        <?php if (!empty($viewingEmployee->name)): ?>
                        <div class="sp-meta-item"><small>Colaborador</small><strong><?= esc($viewingEmployee->name) ?></strong></div>
                        <?php endif; ?>
                        <div class="sp-meta-item"><small>Data</small><strong><?= esc($date_formatted ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Total de registros</small><strong><?= esc(count($punches ?? [])) ?></strong></div>
                        <div class="sp-meta-item"><small>Horas apuradas</small><strong><?= esc($day_summary['hours_worked'] ?? '-') ?></strong></div>
                        <div class="sp-meta-item"><small>Status</small><strong><?= esc($day_summary['status'] ?? 'Sem apuração') ?></strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
