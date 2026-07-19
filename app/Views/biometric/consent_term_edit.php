<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Termo: <?= esc($typeLabel) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => $typeLabel,
        'subtitle' => 'Use as variáveis disponíveis para inserir dados da empresa automaticamente no texto do termo.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left', 'url' => sp_route_url('settings.consent-terms')],
        ],
    ]) ?>

    <div class="sp-card">
        <div class="sp-card-body">
            <form method="POST" action="<?= sp_safe_url(sp_route_url('settings.consent-terms.save')) ?>" id="term-form">
                <?= csrf_field() ?>
                <input type="hidden" name="term_type" value="<?= esc($type) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold" for="term-title">Título do termo <span class="text-danger">*</span></label>
                        <input type="text" id="term-title" name="title" class="form-control"
                               value="<?= esc(old('title', $activeTerm->title ?? '')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" for="term-legal-basis">Base legal</label>
                        <input type="text" id="term-legal-basis" name="legal_basis" class="form-control"
                               value="<?= esc(old('legal_basis', $activeTerm->legal_basis ?? '')) ?>"
                               placeholder="Ex.: LGPD Art. 7º, I">
                    </div>
                </div>

                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fw-semibold mb-0" for="term-body">Texto do termo (código-fonte HTML) <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-preview">
                            <i class="bi bi-eye me-1"></i>Pré-visualizar
                        </button>
                    </div>
                    <textarea id="term-body" name="body" class="form-control font-monospace"
                              rows="18" style="font-size:13px;line-height:1.5" required><?= esc(old('body', $activeTerm->body ?? '')) ?></textarea>

                    <?php if (!empty($variables)): ?>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?php foreach ($variables as $var): ?>
                                <?php $snippet = '{' . $var . '}'; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary sp-var-btn"
                                        data-snippet="<?= esc($snippet) ?>" title="Inserir <?= esc($snippet) ?>">
                                    <code><?= esc($snippet) ?></code>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-text mt-2">
                        Clique em uma variável para inserir no cursor. Os valores vêm de
                        <a href="<?= site_url('admin/settings/information') ?>" target="_blank">Configurações → Informações da Empresa</a>
                        e são substituídos automaticamente ao exibir o termo e ao gravar o aceite do colaborador.
                        Aceita HTML simples (parágrafos, listas, negrito, links, tabelas).
                    </div>
                </div>

                <div class="d-flex gap-2 mt-4 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-cloud-upload-fill me-2"></i>Publicar nova versão
                    </button>
                    <a href="<?= sp_safe_url(sp_route_url('settings.consent-terms')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                </div>
                <div class="form-text mt-2">
                    <i class="bi bi-info-circle me-1"></i>Ao publicar, a versão atual é desativada e uma nova é criada. Colaboradores sem aceite na nova versão serão solicitados novamente.
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($allVersions)): ?>
    <div class="sp-card mt-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-clock-history"></i> Histórico de versões</span>
        </div>
        <div class="sp-card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Versão</th>
                            <th>Título</th>
                            <th>Publicado em</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allVersions as $v): ?>
                            <tr>
                                <td>v<?= esc($v->version) ?></td>
                                <td class="small"><?= esc($v->title) ?></td>
                                <td class="small text-muted"><?= $v->created_at ? esc(format_datetime_br((string) $v->created_at, false)) : '—' ?></td>
                                <td>
                                    <?php if ($v->active): ?>
                                        <span class="badge bg-success-subtle text-success-emphasis">Ativa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">Substituída</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Preview modal -->
    <div id="preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;width:90%;max-width:700px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
                <strong style="font-size:15px">Pré-visualização</strong>
                <button type="button" id="close-preview" style="background:none;border:none;cursor:pointer;font-size:20px;color:#666">&times;</button>
            </div>
            <iframe id="preview-frame" style="flex:1;border:none;min-height:500px" title="Pré-visualização do termo"></iframe>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    var textarea = document.getElementById('term-body');

    function insertAtCursor(text) {
        var start = textarea.selectionStart ?? textarea.value.length;
        var end = textarea.selectionEnd ?? textarea.value.length;
        textarea.value = textarea.value.slice(0, start) + text + textarea.value.slice(end);
        var pos = start + text.length;
        textarea.focus();
        textarea.setSelectionRange(pos, pos);
    }

    document.querySelectorAll('.sp-var-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            insertAtCursor(btn.getAttribute('data-snippet'));
        });
    });

    var modal = document.getElementById('preview-modal');
    var frame = document.getElementById('preview-frame');

    document.getElementById('btn-preview').addEventListener('click', function () {
        modal.style.display = 'flex';
        frame.srcdoc = textarea.value;
    });

    document.getElementById('close-preview').addEventListener('click', function () {
        modal.style.display = 'none';
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });
})();
</script>
<?= $this->endSection() ?>
