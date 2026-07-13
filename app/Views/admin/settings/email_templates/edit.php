<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Template: <?= esc($template['name']) ?><?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => $template['name'],
        'subtitle' => $template['description'] . ' Use as variáveis disponíveis para personalizar a mensagem.',
        'icon'     => 'bi bi-file-earmark-text-fill',
        'actions'  => [
            ['label' => 'Voltar', 'icon' => 'bi bi-arrow-left', 'url' => sp_route_url('admin.settings.email-templates')],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <div class="sp-card">
        <div class="sp-card-body">
            <form method="POST" action="<?= sp_safe_url(sp_route_url('admin.settings.email-templates.update', $key)) ?>" id="template-form">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold" for="tpl-subject">Assunto do e-mail <span class="text-danger">*</span></label>
                    <input type="text" id="tpl-subject" name="subject" class="form-control"
                           value="<?= esc(old('subject', $template['subject'])) ?>" required>
                    <div class="form-text">Texto puro (não aceita HTML). Use as variáveis abaixo para dados dinâmicos.</div>
                </div>

                <div class="mb-2">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <label class="form-label fw-semibold mb-0" for="tpl-content">Conteúdo HTML <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-preview">
                            <i class="bi bi-eye me-1"></i>Pré-visualizar
                        </button>
                    </div>
                    <textarea id="tpl-content" name="content" class="form-control font-monospace"
                              rows="18" style="font-size:13px;line-height:1.5" required><?= esc(old('content', $template['content'])) ?></textarea>

                    <?php if (! empty($template['variables'])): ?>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?php foreach ($template['variables'] as $var): ?>
                                <?php $snippet = '{' . $var . '}'; ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary sp-var-btn"
                                        data-snippet="<?= esc($snippet) ?>" title="Inserir <?= esc($snippet) ?>">
                                    <code><?= esc($snippet) ?></code>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="form-text mt-2">Clique em uma variável para inserir no cursor. O conteúdo aceita HTML simples (links, listas, negrito).</div>
                </div>

                <div class="d-flex gap-2 mt-4 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar template
                    </button>
                    <a href="<?= sp_safe_url(sp_route_url('admin.settings.email-templates')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <?php if ($template['has_override']): ?>
                        <form method="POST" action="<?= sp_safe_url(sp_route_url('admin.settings.email-templates.reset', $key)) ?>"
                              style="display:contents" onsubmit="return confirm('Redefinir este template para o conteúdo padrão do sistema?')">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-danger ms-auto">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Redefinir padrão
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview modal -->
    <div id="preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:8px;width:90%;max-width:700px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:16px 20px;border-bottom:1px solid #eee;display:flex;align-items:center;justify-content:space-between;">
                <strong style="font-size:15px">Pré-visualização</strong>
                <button type="button" id="close-preview" style="background:none;border:none;cursor:pointer;font-size:20px;color:#666">&times;</button>
            </div>
            <iframe id="preview-frame" style="flex:1;border:none;min-height:500px" title="Pré-visualização do template"></iframe>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    var textarea = document.getElementById('tpl-content');

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
