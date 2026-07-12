<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Minhas Solicitações de Alteração<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$emp   = $employee;
$empId = (int)($emp->id ?? 0);
$reqs  = $requests ?? [];
?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Solicitações de Alteração Cadastral',
        'subtitle' => 'Acompanhe o status das suas solicitações',
        'icon'     => 'bi bi-clock-history',
        'actions'  => [
            ['label' => 'Nova solicitação', 'icon' => 'bi bi-plus-circle', 'url' => site_url('employees/change-request/create/' . $empId)],
            ['label' => 'Voltar ao perfil',  'icon' => 'bi bi-arrow-left',   'url' => site_url('employees/' . $empId)],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <div class="sp-data-card">
        <div class="sp-data-card__body p-0">
            <?php if (empty($reqs)): ?>
                <?= view('components/empty_state', [
                    'icon'        => 'bi bi-pencil-square',
                    'title'       => 'Nenhuma solicitação enviada',
                    'description' => 'Quando você enviar uma solicitação de alteração, ela aparecerá aqui.',
                ]) ?>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Campo</th>
                            <th>Valor solicitado</th>
                            <th>Justificativa</th>
                            <th>Enviado em</th>
                            <th>Status</th>
                            <th>Nota do revisor</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reqs as $r): ?>
                    <tr>
                        <td class="ps-3 fw-semibold small"><?= esc($r->field_label ?? $r->field_key ?? '') ?></td>
                        <td class="small">
                            <?php if (!empty($r->current_value)): ?>
                                <span class="text-muted text-decoration-line-through me-1"><?= esc($r->current_value) ?></span>
                                <i class="bi bi-arrow-right text-muted small"></i>
                            <?php endif; ?>
                            <span class="fw-medium"><?= esc($r->requested_value ?? '') ?></span>
                        </td>
                        <td class="small text-muted" style="max-width:220px">
                            <span title="<?= esc($r->justification ?? '') ?>">
                                <?= esc(mb_strimwidth($r->justification ?? '', 0, 80, '…')) ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= !empty($r->created_at) ? date('d/m/Y H:i', strtotime($r->created_at)) : '—' ?></td>
                        <td>
                            <?php if ($r->status === 'pending'): ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i>Pendente</span>
                            <?php elseif ($r->status === 'approved'): ?>
                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Aprovada</span>
                            <?php else: ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Recusada</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?= esc($r->review_note ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
