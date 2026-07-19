<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Templates de Termos de Consentimento<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Templates de Termos de Consentimento',
        'subtitle' => 'Cada versão publicada vira registro legal — os colaboradores voltam a ser solicitados a aceitar.',
        'icon'     => 'bi bi-shield-lock-fill',
    ]) ?>

    <div class="sp-card">
        <div class="sp-card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Termo</th>
                            <th>Versão ativa</th>
                            <th>Base legal</th>
                            <th>Status</th>
                            <th style="width:110px">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php $active = $row['active']; ?>
                            <tr>
                                <td class="fw-semibold"><?= esc($row['label']) ?></td>
                                <td>
                                    <?php if ($active): ?>
                                        <span class="small text-muted">v<?= esc($active->version) ?> — <?= esc($active->title) ?></span>
                                    <?php else: ?>
                                        <span class="small text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="small text-muted"><?= $active && $active->legal_basis ? esc($active->legal_basis) : '—' ?></span>
                                </td>
                                <td>
                                    <?php if ($active): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis">Publicado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">Sem termo publicado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= sp_safe_url(sp_route_url('settings.consent-terms.edit', $row['type'])) ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil-fill me-1"></i>Editar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
