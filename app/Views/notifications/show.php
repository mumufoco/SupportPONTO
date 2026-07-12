<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Notificação<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Detalhes da Notificação',
        'subtitle' => 'Mensagem enviada pelo sistema.',
        'icon'     => 'bi bi-bell-fill',
        'actions'  => [
            ['label' => 'Notificações', 'icon' => 'bi bi-arrow-left-circle', 'url' => route_to('notifications')],
        ],
    ]) ?>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="sp-card">
                <div class="sp-card-header">
                    <span class="sp-card-title">
                        <i class="bi bi-envelope-fill"></i>
                        <?= esc($notification['title'] ?? 'Notificação') ?>
                    </span>
                    <span class="text-muted small">
                        <?= date('d/m/Y H:i', strtotime($notification['created_at'] ?? 'now')) ?>
                    </span>
                </div>
                <div class="sp-card-body">
                    <p class="mb-3"><?= nl2br(esc($notification['message'] ?? '')) ?></p>
                    <?php if (!empty($notification['data'])): ?>
                        <pre class="sp-callout-neutral small"><?= esc(print_r($notification['data'], true)) ?></pre>
                    <?php endif; ?>
                </div>
                <div class="sp-card-footer d-flex justify-content-end">
                    <a href="<?= route_to('notifications') ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
