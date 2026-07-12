<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Modelos de E-mail<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Modelos de E-mail',
        'subtitle' => 'Configure os textos enviados pelo sistema em cada situação.',
        'icon'     => 'bi bi-envelope-paper-fill',
        'actions'  => [
                                ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.email-templates.update')) ?>" method="POST" id="form-templates">
        <?= csrf_field() ?>

        <!-- Legenda de variáveis -->
        <div class="sp-card mb-3">
            <div class="sp-card-body">
                <div class="sp-callout-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <strong>Variáveis disponíveis</strong> por modelo estão indicadas em cada card.
                    Use a sintaxe <code>{variavel}</code> — o sistema substituirá automaticamente.
                    Se o modelo estiver vazio, o sistema usa um texto padrão embutido.
                </div>
            </div>
        </div>

        <?php foreach ($templates as $id => $tmpl): ?>
        <div class="sp-card mb-3" id="tmpl-<?= esc($id) ?>">
            <div class="sp-card-header d-flex justify-content-between align-items-center" style="cursor:pointer"
                 data-bs-toggle="collapse" data-bs-target="#collapse-<?= esc($id) ?>" aria-expanded="<?= $tmpl['current_body'] ? 'true' : 'false' ?>">
                <span class="sp-card-title">
                    <i class="bi bi-envelope-fill me-2 text-primary"></i>
                    <?= esc($tmpl['label']) ?>
                    <?php if ($tmpl['current_body']): ?>
                        <span class="badge bg-success ms-2" style="font-size:.7rem">Configurado</span>
                    <?php else: ?>
                        <span class="badge bg-secondary ms-2" style="font-size:.7rem">Padrão</span>
                    <?php endif; ?>
                </span>
                <i class="bi bi-chevron-down text-muted"></i>
            </div>
            <div id="collapse-<?= esc($id) ?>" class="collapse <?= $tmpl['current_body'] ? 'show' : '' ?>">
                <div class="sp-card-body">
                    <p class="text-muted small mb-3"><?= esc($tmpl['description']) ?></p>

                    <!-- Assunto -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-card-heading me-1"></i>Assunto do e-mail
                        </label>
                        <input type="text" class="form-control form-control-sm"
                               name="<?= esc($tmpl['subject_key']) ?>"
                               value="<?= esc($tmpl['current_subject']) ?>"
                               placeholder="<?= esc($tmpl['default_subject']) ?>">
                        <div class="form-text">Deixe vazio para usar o padrão: <em><?= esc($tmpl['default_subject']) ?></em></div>
                    </div>

                    <!-- Corpo -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">
                            <i class="bi bi-body-text me-1"></i>Corpo do e-mail
                        </label>
                        <div class="d-flex gap-2 mb-1">
                            <span class="text-muted small">Variáveis:</span>
                            <?php foreach (explode(', ', $tmpl['variables']) as $var): ?>
                                <code class="small" style="cursor:pointer;color:var(--sp-primary)"
                                      onclick="insertVar('<?= esc($tmpl['key']) ?>', '<?= esc(trim($var)) ?>')"
                                      title="Clique para inserir"><?= esc(trim($var)) ?></code>
                            <?php endforeach; ?>
                        </div>
                        <textarea class="form-control font-monospace"
                                  name="<?= esc($tmpl['key']) ?>"
                                  id="ta-<?= esc($tmpl['key']) ?>"
                                  rows="5"
                                  placeholder="Deixe vazio para usar o modelo padrão do sistema..."><?= esc($tmpl['current_body']) ?></textarea>
                    </div>

                    <!-- Preview button -->
                    <div class="d-flex gap-2 justify-content-end mt-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="previewTemplate('<?= esc($tmpl['key']) ?>')">
                            <i class="bi bi-eye me-1"></i>Pré-visualizar
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Botões -->
        <div class="sp-card mt-2">
            <div class="sp-card-body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(route_to('admin.settings.email')) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Voltar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar todos os modelos
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Preview modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>Pré-visualização do E-mail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <iframe id="previewFrame" style="width:100%;height:500px;border:none"></iframe>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    const EP_PREVIEW = '<?= esc(sp_route_url('admin.settings.email-templates.preview')) ?>';

    // Insert variable tag at cursor position
    window.insertVar = function(fieldKey, variable) {
        const ta = document.getElementById('ta-' + fieldKey);
        if (!ta) return;
        const start = ta.selectionStart;
        const end   = ta.selectionEnd;
        ta.value = ta.value.substring(0, start) + variable + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + variable.length;
        ta.focus();
    };

    // Preview template
    window.previewTemplate = async function(fieldKey) {
        const ta   = document.getElementById('ta-' + fieldKey);
        const body = ta ? ta.value : '';

        try {
            const fd = new FormData();
            fd.append('body', body);

            const r = await spFetch(EP_PREVIEW, { method: 'POST', body: fd });
            const j = await r.json();

            if (j.success) {
                const frame = document.getElementById('previewFrame');
                frame.srcdoc = j.html;
                const modal  = new bootstrap.Modal(document.getElementById('previewModal'));
                modal.show();
            }
        } catch (e) {
            console.error('Preview failed', e);
        }
    };

    // Accordion chevron toggle
    document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(function(btn) {
        const target = document.querySelector(btn.getAttribute('data-bs-target'));
        if (!target) return;
        target.addEventListener('show.bs.collapse', function() {
            btn.querySelector('.bi-chevron-down')?.classList.replace('bi-chevron-down', 'bi-chevron-up');
        });
        target.addEventListener('hide.bs.collapse', function() {
            btn.querySelector('.bi-chevron-up')?.classList.replace('bi-chevron-up', 'bi-chevron-down');
        });
    });
})();
</script>
<?= $this->endSection() ?>
