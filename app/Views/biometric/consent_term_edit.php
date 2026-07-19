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
                        Clique em uma variável para inserir no cursor. As variáveis <code>empresa_*</code> e
                        <code>responsavel_*</code> vêm de
                        <a href="<?= site_url('admin/settings/information') ?>" target="_blank">Configurações → Informações da Empresa</a>;
                        as variáveis <code>colaborador_*</code> vêm do cadastro de cada colaborador e só são
                        resolvidas quando o termo é exibido/assinado por ele especificamente — na pré-visualização
                        acima, como não há um colaborador em contexto, todas as variáveis aparecem como texto
                        literal (ex.: <code>{colaborador_nome}</code>).
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
    <div id="preview-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
        <div style="background:#e9ecef;border-radius:8px;width:94%;max-width:900px;height:92vh;display:flex;flex-direction:column;overflow:hidden;">
            <div style="padding:12px 20px;border-bottom:1px solid #dee2e6;background:#fff;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
                <div>
                    <strong style="font-size:15px">Pré-visualização — folha A4</strong>
                    <span id="preview-page-count" class="badge bg-secondary ms-2"></span>
                </div>
                <button type="button" id="close-preview" style="background:none;border:none;cursor:pointer;font-size:22px;color:#666;line-height:1">&times;</button>
            </div>
            <div style="padding:6px 20px;background:#fff;border-bottom:1px solid #dee2e6;flex-shrink:0;">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>Simula folha A4 (210×297mm, margens de 25mm) como referência de como o termo tende a ficar paginado no SupportCHECK. As linhas tracejadas marcam onde uma nova página começaria.
                </small>
            </div>
            <iframe id="preview-frame" style="flex:1;border:none;width:100%" title="Pré-visualização do termo em folha A4"></iframe>
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
    var pageCountBadge = document.getElementById('preview-page-count');

    // Documento A4 (210x297mm, margens de 25mm) usado dentro do iframe. As
    // linhas de quebra de página são calculadas via JS depois de renderizar,
    // comparando a altura real do conteúdo com a altura útil de uma folha —
    // não é paginação de impressão de verdade, só uma referência visual clara
    // de "isso vai ocupar mais de uma página" (o objetivo pedido).
    function buildA4Html(bodyHtml) {
        return '<!doctype html><html><head><meta charset="utf-8">'
            + '<style>'
            + '  * { box-sizing: border-box; }'
            + '  html, body { margin:0; padding:0; background:#e9ecef; }'
            + '  body { padding: 24px 0 60px; font-family: Arial, Helvetica, sans-serif; }'
            + '  #mm-probe { width: 1mm; height: 0; }'
            + '  .a4-wrap { position: relative; width: 210mm; margin: 0 auto; }'
            + '  .a4-sheet { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 25mm 20mm; '
            + '    background: #fff; box-shadow: 0 1px 6px rgba(0,0,0,.3); position: relative; '
            + '    font-size: 12pt; line-height: 1.6; color: #1a1a1a; }'
            + '  .a4-sheet h1, .a4-sheet h2, .a4-sheet h3 { margin-top:0; }'
            + '  .a4-sheet p { margin: 0 0 .8em; }'
            + '  .a4-sheet table { width:100%; border-collapse:collapse; }'
            + '  .page-break-line { position:absolute; left:0; right:0; border-top:2px dashed #dc3545; z-index:5; }'
            + '  .page-break-label { position:absolute; right: 20mm; top:-11px; background:#dc3545; color:#fff; '
            + '    font-size:9px; font-weight:700; letter-spacing:.03em; padding:2px 8px; border-radius:3px; z-index:6; }'
            + '</style></head><body>'
            + '<div id="mm-probe"></div>'
            + '<div class="a4-wrap"><div class="a4-sheet" id="a4-sheet">' + bodyHtml + '</div></div>'
            + '</body></html>';
    }

    function renderPreview() {
        frame.srcdoc = buildA4Html(textarea.value);

        frame.onload = function () {
            try {
                var doc = frame.contentDocument;
                var sheet = doc.getElementById('a4-sheet');
                var probe = doc.getElementById('mm-probe');
                if (!sheet || !probe) { return; }

                var mmToPx = probe.getBoundingClientRect().width || 3.7795;
                var pagePaddingPx = 25 * mmToPx; // topo e rodapé (25mm cada)
                var pageHeightPx = 297 * mmToPx;
                var usablePerPagePx = pageHeightPx - (pagePaddingPx * 2);

                var contentHeightPx = sheet.scrollHeight - (pagePaddingPx * 2);
                var pageCount = Math.max(1, Math.ceil(contentHeightPx / usablePerPagePx));

                pageCountBadge.textContent = pageCount === 1
                    ? '1 página A4'
                    : pageCount + ' páginas A4';
                pageCountBadge.className = 'badge ms-2 ' + (pageCount > 1 ? 'bg-warning text-dark' : 'bg-success');

                // Remove marcadores de uma renderização anterior antes de recalcular.
                doc.querySelectorAll('.page-break-line').forEach(function (el) { el.remove(); });

                for (var i = 1; i < pageCount; i++) {
                    var marker = doc.createElement('div');
                    marker.className = 'page-break-line';
                    marker.style.top = (pagePaddingPx + (i * usablePerPagePx)) + 'px';

                    var label = doc.createElement('span');
                    label.className = 'page-break-label';
                    label.textContent = 'Página ' + (i + 1);
                    marker.appendChild(label);

                    sheet.appendChild(marker);
                }
            } catch (err) {
                // Pré-visualização segue funcional sem os marcadores de página
                // caso o cálculo falhe por qualquer motivo (não deve impedir o uso).
            }
        };
    }

    document.getElementById('btn-preview').addEventListener('click', function () {
        modal.style.display = 'flex';
        pageCountBadge.textContent = '';
        renderPreview();
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
