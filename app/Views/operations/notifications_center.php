<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Central de notificações<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">
    <?= view('components/page_header', [
        'title' => 'Central de notificações',
        'subtitle' => 'Visualize alertas recentes e navegue diretamente para a resolução de cada item.',
        'icon' => 'bi bi-bell-fill',
        'actions' => [
            ['label' => 'Pendências', 'icon' => 'bi bi-clipboard-check-fill', 'url' => site_url('operations/pending-center')],
        ],
    ]) ?>

    <div class="sp-notification-center">
        <?php foreach ($notifications as $item): ?>
            <a class="sp-notification-item text-decoration-none text-reset<?= empty($item['read']) ? ' sp-notification-item--unread' : '' ?>" href="<?= sp_safe_url($item['url']) ?>">
                <div>
                    <strong><?= esc($item['title']) ?></strong>
                    <div><?= esc($item['message']) ?></div>
                    <div class="sp-notification-item__meta mt-2"><?= esc($item['when']) ?></div>
                </div>
                <div><?= view('components/status_pill', ['variant' => ($item['variant'] ?? ($item['status'] === 'Crítico' ? 'danger' : ($item['status'] === 'Atenção' ? 'warning' : 'pending'))), 'label' => $item['status']]) ?></div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
