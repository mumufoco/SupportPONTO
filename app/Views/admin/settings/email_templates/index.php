<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Templates de E-mail<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Templates de E-mail',
        'subtitle' => 'Personalize o conteúdo e o assunto dos e-mails enviados automaticamente pelo sistema.',
        'icon'     => 'bi bi-file-earmark-text-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left', 'url' => sp_route_url('admin.settings.email')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <div class="sp-card">
        <div class="sp-card-body p-0">
            <?php if (empty($templates)): ?>
                <div class="sp-callout-neutral m-3">
                    <i class="bi bi-info-circle me-2"></i>Nenhum template encontrado.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Template</th>
                                <th>Assunto atual</th>
                                <th>Variáveis disponíveis</th>
                                <th>Status</th>
                                <th style="width:110px">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $tpl): ?>
                                <tr>
                                    <td class="fw-semibold"><?= esc($tpl['name']) ?></td>
                                    <td>
                                        <span class="small text-muted font-monospace"><?= esc($tpl['subject']) ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($tpl['variables'] as $var): ?>
                                                <code class="small bg-light border rounded px-1"><?= esc('{' . $var . '}') ?></code>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($tpl['has_override']): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis">Personalizado</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis">Padrão</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?= sp_safe_url(sp_route_url('admin.settings.email-templates.edit', $tpl['key'])) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil-fill me-1"></i>Editar
                                        </a>
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
