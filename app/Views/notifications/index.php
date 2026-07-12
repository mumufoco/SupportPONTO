<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Notificações<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Notificações',
        'subtitle' => 'Alertas, mensagens do sistema e pendências recentes.',
        'icon'     => 'bi bi-bell-fill',
        'actions'  => [
            ['label' => 'Dashboard', 'icon' => 'bi bi-grid-fill', 'url' => site_url('dashboard')],
        ],
    ]) ?>

    <div class="sp-card">
        <div class="sp-card-body p-0">
            <?php if (empty($notifications ?? [])): ?>
                <?= view('components/empty_state', [
                    'icon'        => 'bi bi-bell-slash-fill',
                    'title'       => 'Nenhuma notificação',
                    'description' => 'Quando houver eventos relevantes do sistema, eles aparecerão aqui.',
                ]) ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Mensagem</th>
                                <th>Data</th>
                                <th>Status</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notification): ?>
                                <tr>
                                    <td class="text-muted small"><?= esc($notification['type'] ?? '–') ?></td>
                                    <td><?= esc($notification['message'] ?? '–') ?></td>
                                    <td class="text-muted small"><?= esc($notification['created_at'] ?? '–') ?></td>
                                    <td>
                                        <?php if (!empty($notification['read'])): ?>
                                            <span class="sp-badge sp-badge-neutral">Lida</span>
                                        <?php else: ?>
                                            <span class="sp-badge sp-badge-primary">Nova</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (!empty($notification['id'])): ?>
                                            <a href="<?= site_url('notifications/' . $notification['id']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
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
