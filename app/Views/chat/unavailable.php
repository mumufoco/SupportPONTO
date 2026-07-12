<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Chat<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
    <?= view('components/page_header', ['title' => 'Chat', 'subtitle' => 'Mensagens internas', 'icon' => 'bi bi-chat-dots-fill', 'actions' => []]) ?>
    <div class="sp-card">
        <div class="sp-card-body text-center py-5">
            <i class="bi bi-chat-dots text-muted" style="font-size:3rem;display:block;margin-bottom:1rem"></i>
            <h5>Chat temporariamente indisponível</h5>
            <p class="text-muted">O serviço de mensagens está em manutenção. Tente novamente mais tarde.</p>
            <a href="<?= site_url('dashboard') ?>" class="btn btn-outline-primary">
                <i class="bi bi-house me-2"></i>Voltar ao Dashboard
            </a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
