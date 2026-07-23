<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Histórico de ponto<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$exportQuery = http_build_query(array_filter([
    'start_date'  => $filters['start_date'] ?? null,
    'end_date'    => $filters['end_date'] ?? null,
    'type'        => $filters['type'] ?? null,
    'method'      => $filters['method'] ?? null,
    'employee_id' => ($targetEmployeeId ?? 0) > 0 ? (int) $targetEmployeeId : null,
]));
?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title'    => 'Histórico de ponto',
        'subtitle' => 'Filtre registros, acompanhe o resumo do período e acesse rapidamente cada lançamento.',
        'icon'     => 'bi bi-clock-history',
        'actions'  => [
            ['label' => 'Exportar PDF',   'icon' => 'bi bi-file-earmark-pdf-fill',   'url' => base_url('timesheet/history/export/pdf?' . $exportQuery),   'class' => 'sp-page-chip--primary'],
            ['label' => 'Exportar Excel', 'icon' => 'bi bi-file-earmark-excel-fill', 'url' => base_url('timesheet/history/export/excel?' . $exportQuery), 'class' => 'sp-page-chip--primary'],
            ['label' => 'Exportar CSV',   'icon' => 'bi bi-filetype-csv',            'url' => base_url('timesheet/history/export/csv?' . $exportQuery),   'class' => 'sp-page-chip--primary'],
        ],
    ]) ?>

    <!-- Filtros -->
    <div class="sp-card">
        <div class="sp-card-body" style="padding:1rem;">
            <form method="GET" action="<?= base_url('timesheet/history') ?>"
                  style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">

                <?php if (!empty($isManager) && !empty($employeesList)): ?>
                <div class="sp-form-group" style="margin:0;min-width:200px;flex:2;">
                    <label class="sp-label" for="employee_id">
                        <i class="bi bi-person-fill me-1"></i>Colaborador
                    </label>
                    <select class="sp-select" id="employee_id" name="employee_id">
                        <option value="">— Todos —</option>
                        <?php foreach ($employeesList as $emp): ?>
                            <option value="<?= (int)($emp->id ?? $emp['id'] ?? 0) ?>"
                                <?= ((int)($targetEmployeeId ?? 0) === (int)($emp->id ?? $emp['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= esc($emp->name ?? $emp['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="sp-form-group" style="margin:0;min-width:140px;flex:1;">
                    <label class="sp-label" for="start_date">Data inicial</label>
                    <input type="date" class="sp-input" id="start_date" name="start_date"
                           value="<?= esc($filters['start_date'] ?? '') ?>">
                </div>
                <div class="sp-form-group" style="margin:0;min-width:140px;flex:1;">
                    <label class="sp-label" for="end_date">Data final</label>
                    <input type="date" class="sp-input" id="end_date" name="end_date"
                           value="<?= esc($filters['end_date'] ?? '') ?>">
                </div>
                <div class="sp-form-group" style="margin:0;min-width:160px;flex:1;">
                    <label class="sp-label" for="type">Tipo de marcação</label>
                    <select class="sp-select" id="type" name="type">
                        <option value="">Todos</option>
                        <option value="entrada"          <?= (($filters['type'] ?? '') === 'entrada')          ? 'selected' : '' ?>>Entrada</option>
                        <option value="saida"            <?= (($filters['type'] ?? '') === 'saida')            ? 'selected' : '' ?>>Saída</option>
                        <option value="intervalo_inicio" <?= in_array(($filters['type'] ?? ''), ['intervalo_inicio','inicio_intervalo'], true) ? 'selected' : '' ?>>Início do intervalo</option>
                        <option value="intervalo_fim"    <?= (($filters['type'] ?? '') === 'intervalo_fim')    ? 'selected' : '' ?>>Fim do intervalo</option>
                    </select>
                </div>
                <div class="sp-form-group" style="margin:0;min-width:140px;flex:1;">
                    <label class="sp-label" for="method">Método</label>
                    <select class="sp-select" id="method" name="method">
                        <option value="">Todos</option>
                        <option value="codigo"    <?= (($filters['method'] ?? '') === 'codigo')    ? 'selected' : '' ?>>Código</option>
                        <option value="cpf"       <?= (($filters['method'] ?? '') === 'cpf')       ? 'selected' : '' ?>>CPF</option>
                        <option value="facial"    <?= (($filters['method'] ?? '') === 'facial')    ? 'selected' : '' ?>>Facial</option>
                        <option value="biometria" <?= (($filters['method'] ?? '') === 'biometria') ? 'selected' : '' ?>>Biometria</option>
                        <option value="qrcode"    <?= (($filters['method'] ?? '') === 'qrcode')    ? 'selected' : '' ?>>QR Code</option>
                    </select>
                </div>
                <div style="display:flex;gap:.5rem;align-items:flex-end;padding-bottom:1px;">
                    <button type="submit" class="sp-btn sp-btn-primary">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="<?= base_url('timesheet/history') ?>" class="sp-btn sp-btn-outline">
                        <i class="bi bi-arrow-counterclockwise"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumo -->
    <?php
    $totalPunches   = count($punches ?? []);
    $workedDays     = (int) ($summary['total_days']     ?? 0);
    $totalHours     = $summary['total_hours']            ?? '0.00';
    $inconsistencies= (int) ($summary['missing_punches'] ?? 0);
    ?>
    <div class="sp-grid-4">
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-primary-dark);line-height:1;">
                <?= $totalPunches ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Registros encontrados
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-primary-dark);line-height:1;">
                <?= $workedDays ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Dias com marcação
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;color:var(--sp-primary-dark);line-height:1;">
                <?= esc($totalHours) ?>h
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Horas no período
            </div>
        </div>
        <div class="sp-card" style="text-align:center;padding:1rem;">
            <div style="font-size:1.75rem;font-weight:700;
                 color:<?= $inconsistencies > 0 ? 'var(--sp-danger)' : 'var(--sp-primary-dark)' ?>;
                 line-height:1;">
                <?= $inconsistencies ?>
            </div>
            <div style="font-size:.75rem;color:var(--sp-text-secondary);text-transform:uppercase;letter-spacing:.04em;margin-top:.25rem;">
                Inconsistências
            </div>
        </div>
    </div>

    <!-- Tabela -->
    <div class="sp-card">
        <?php if (empty($punches ?? [])): ?>
            <div class="sp-card-body">
                <div class="sp-empty">
                    <div class="sp-empty-icon"><i class="bi bi-clock-history"></i></div>
                    <p class="sp-empty-title">Nenhum registro encontrado</p>
                    <p class="sp-empty-text">Ajuste os filtros ou amplie o período de busca.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="sp-table-container">
                <table class="sp-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th>Método</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (($punches ?? []) as $punch): ?>
                            <?php
                            $punchTime  = (string) ($punch->punch_time ?? '');
                            $dateStr    = $punchTime ? date('Y-m-d', strtotime($punchTime)) : '';
                            $dateFmt    = $punchTime ? date('d/m/Y', strtotime($punchTime)) : '-';
                            $timeFmt    = $punchTime ? date('H:i',   strtotime($punchTime)) : '-';
                            $typeLabel  = match($punch->punch_type ?? '') {
                                'entrada'          => 'Entrada',
                                'saida'            => 'Saída',
                                'intervalo_inicio' => 'Início intervalo',
                                'intervalo_fim'    => 'Fim intervalo',
                                default            => ucfirst(str_replace('_', ' ', $punch->punch_type ?? '-')),
                            };
                            $methodLabel = ucfirst($punch->method ?? '-');
                            $isValid     = $punch->is_valid ?? true;
                            ?>
                            <tr>
                                <td><?= esc($dateFmt) ?></td>
                                <td><strong><?= esc($timeFmt) ?></strong></td>
                                <td><?= esc($typeLabel) ?></td>
                                <td><?= esc($methodLabel) ?></td>
                                <td>
                                    <?php if ($isValid): ?>
                                        <span class="sp-badge sp-badge-success">Válido</span>
                                    <?php else: ?>
                                        <span class="sp-badge sp-badge-warning">Revisar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-icon-actions">
                                        <?php
                                            $dayUrl = $dateStr
                                                ? sp_timesheet_day_url($dateStr) . (($targetEmployeeId ?? 0) > 0 ? '?employee_id=' . (int) $targetEmployeeId : '')
                                                : '#';
                                        ?>
                                        <a href="<?= $dayUrl ?>"
                                           class="icon-action" title="Ver dia">
                                            <i class="bi bi-eye-fill"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
