<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Personalização<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Personalização',
        'subtitle' => 'Configure tema, cores, tipografia e imagens do sistema. As alterações são aplicadas imediatamente para todos os usuários.',
        'icon'     => 'bi bi-palette-fill',
        'actions'  => [],
    ]) ?>


    <div id="personalizationToast" style="position:fixed;top:1rem;right:1rem;z-index:9999;min-width:300px"></div>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.personalization.update')) ?>"
          method="POST" enctype="multipart/form-data" id="form-personalization">
        <?= csrf_field() ?>

        <!-- ── 1. TEMA ── -->
        <section class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-moon-stars-fill"></i> Tema do sistema</span>
                <span class="text-muted small">Automático respeita a preferência do sistema operacional de cada usuário.</span>
            </div>
            <div class="sp-card-body">
                <?php $themeMode = $currentConfig['custom']['theme_mode'] ?? 'dark'; ?>
                <div class="sp-theme-grid">
                    <?php foreach ([
                        'light' => 'Modo Claro',
                        'dark'  => 'Modo Escuro',
                        'auto'  => 'Automático',
                    ] as $val => $label): ?>
                        <label class="sp-theme-card <?= $themeMode === $val ? 'sp-theme-card--active' : '' ?>">
                            <input type="radio" name="theme_mode" value="<?= esc($val) ?>"
                                   class="sp-theme-radio"
                                   <?= $themeMode === $val ? 'checked' : '' ?>>
                            <span class="sp-theme-card__icon">
                                <?php if ($val === 'light'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                                <?php elseif ($val === 'dark'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                                <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                                <?php endif; ?>
                            </span>
                            <span class="sp-theme-card__label"><?= esc($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ── 2. CORES ── -->
        <section class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-palette-fill"></i> Cores do sistema</span>
                <span class="text-muted small">Personalize a paleta usada em botões, destaques e links.</span>
            </div>
            <div class="sp-card-body">
                <div class="sp-color-grid">
                    <?php
                    $colorFields = [
                        'primary_color'   => ['Cor primária',    'Botões principais, destaques e links ativos.',        '#4fa14f'],
                        'secondary_color' => ['Cor secundária',  'Acentos e elementos terciários.',                     '#3a7a3a'],
                        'success_color'   => ['Sucesso',         'Confirmações e ações de sucesso.',                    '#1abc9c'],
                        'warning_color'   => ['Aviso',           'Alertas e estados de atenção.',                       '#f0c43f'],
                        'danger_color'    => ['Erro / Perigo',   'Ações destrutivas ou de alerta.',                     '#e74c3c'],
                        'info_color'      => ['Informação',      'Mensagens informativas e badges neutros.',            '#5b73e8'],
                    ];
                    foreach ($colorFields as $name => [$label, $help, $default]):
                        $shortKey = str_replace('_color', '', $name);
                        $val = $settings[$name]
                            ?? $currentConfig['colors'][$shortKey]
                            ?? $currentConfig['colors'][$name]
                            ?? $currentConfig['custom'][$name]
                            ?? $default;
                    ?>
                        <div class="sp-field">
                            <label class="form-label fw-semibold small" for="text_<?= $name ?>"><?= esc($label) ?></label>
                            <div class="sp-color-input-wrap">
                                <input type="color" class="sp-color-picker"
                                       value="<?= esc($val) ?>" id="picker_<?= $name ?>"
                                       data-color-target="text_<?= $name ?>" aria-hidden="true" tabindex="-1">
                                <input type="text" class="form-control font-monospace sp-color-text"
                                       name="<?= $name ?>" value="<?= esc($val) ?>" id="text_<?= $name ?>"
                                       maxlength="7" placeholder="#000000"
                                       data-color-picker="picker_<?= $name ?>"
                                       pattern="^#[0-9A-Fa-f]{6}$" required>
                            </div>
                            <span class="sp-help"><?= esc($help) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ── 3. TIPOGRAFIA ── -->
        <section class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-type"></i> Tipografia</span>
                <span class="text-muted small">Escolha a fonte e o tamanho base do sistema.</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="brand-font-family">Fonte principal</label>
                        <select class="form-select" id="brand-font-family" name="font_family">
                            <?php
                            $curFont = $currentConfig['typography']['font_family'] ?? 'Inter';
                            // Aceita valores legados salvos como '"Nome", sans-serif'
                            $curFontName = trim((string) preg_replace('/^"?([^",]+)"?.*$/', '$1', $curFont));
                            foreach ($availableFonts as $fontName => $fontSpec):
                            ?>
                                <option value="<?= esc($fontName) ?>"
                                        <?= $curFontName === $fontName ? 'selected' : '' ?>
                                        style="font-family:'<?= esc($fontName) ?>'">
                                    <?= esc($fontName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sp-help">A fonte é carregada do Google Fonts. Inter é a fonte padrão do sistema.</span>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="brand-font-size">Tamanho base</label>
                        <?php $curSize = (int) preg_replace('/\D/', '', (string) ($currentConfig['typography']['font_size_base'] ?? '15px')) ?: 15; ?>
                        <div class="d-flex align-items-center gap-3">
                            <input type="range" id="brand-font-size" name="font_size_base"
                                   min="12" max="20" step="1" value="<?= $curSize ?>"
                                   class="form-range" style="flex:1">
                            <span id="brand-font-size-val" class="sp-size-preview"><?= $curSize ?>px</span>
                        </div>
                        <span class="sp-help">Padrão: 15px. Afeta o tamanho base de todo o texto do sistema.</span>
                    </div>
                </div>

                <!-- Preview de tipografia -->
                <div class="sp-font-preview" id="sp-font-preview">
                    <div class="sp-font-preview__name" id="sp-font-name"><?= esc($curFontName) ?></div>
                    <div class="sp-font-preview__sample" id="sp-font-sample">
                        SupportPONTO — Sistema de Ponto Eletrônico Brasileiro
                    </div>
                    <div class="sp-font-preview__weights" id="sp-font-weights">
                        <span style="font-weight:300">Light</span>
                        <span style="font-weight:400">Regular</span>
                        <span style="font-weight:500">Medium</span>
                        <span style="font-weight:600">SemiBold</span>
                        <span style="font-weight:700">Bold</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── 4. IMAGENS ── -->
        <section class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-images"></i> Imagens e identidade visual</span>
                <span class="text-muted small">Formatos aceitos: JPG, PNG, WEBP. Cada imagem é redimensionada automaticamente.</span>
            </div>
            <div class="sp-card-body">
                <div class="sp-image-grid">
                    <?php
                    $imageSlots = [
                        'logo_sidebar' => [
                            'label' => 'Logo do menu lateral', 'icon' => 'bi-layout-sidebar',
                            'hint'  => 'Acima do menu de navegação · salva em 512×128px.',
                            'previewUrl' => support_logo_url('sidebar'), 'current' => $settings['logo_sidebar_path'] ?? '',
                            'accept' => 'image/png,image/jpeg,image/webp', 'maxLabel' => 'PNG/JPG/WEBP · Máx 2MB',
                        ],
                        'logo_auth' => [
                            'label' => 'Logo páginas sem login', 'icon' => 'bi-door-open',
                            'hint'  => 'Login, recuperação, 2FA, ponto sem login · salva em 512×512px.',
                            'previewUrl' => support_logo_url('auth'), 'current' => $settings['logo_auth_path'] ?? '',
                            'accept' => 'image/png,image/jpeg,image/webp', 'maxLabel' => 'PNG/JPG/WEBP · Máx 2MB',
                        ],
                        'logo' => [
                            'label' => 'Logo geral (fallback)', 'icon' => 'bi-image-fill',
                            'hint'  => 'Usada quando os slots acima não têm imagem definida · salva em 512×512px.',
                            'previewUrl' => support_logo_url('small'), 'current' => $settings['logo_path'] ?? '',
                            'accept' => 'image/png,image/jpeg,image/webp', 'maxLabel' => 'PNG/JPG/WEBP · Máx 2MB',
                        ],
                        'favicon' => [
                            'label' => 'Favicon (ícone da aba)', 'icon' => 'bi-stars',
                            'hint'  => 'Ícone da aba do navegador · gera 4 tamanhos (16, 32, 48, 180px).',
                            'previewUrl' => support_favicon_url(), 'current' => $settings['favicon_path'] ?? '',
                            'accept' => 'image/png,image/x-icon', 'maxLabel' => 'PNG ou ICO · Máx 1MB',
                        ],
                        'login_background' => [
                            'label' => 'Capa da tela de login', 'icon' => 'bi-card-image',
                            'hint'  => 'Imagem de fundo da tela de login · sem redimensionamento forçado.',
                            'previewUrl' => support_login_background_url(), 'current' => $settings['login_background_path'] ?? '',
                            'accept' => 'image/png,image/jpeg,image/webp', 'maxLabel' => 'PNG/JPG/WEBP · Máx 5MB · Rec. 1920×1080',
                        ],
                    ];
                    foreach ($imageSlots as $field => $slot):
                        $previewId = 'preview_' . $field;
                        $fileId    = 'file_' . $field;
                    ?>
                        <div class="sp-image-slot">
                            <div class="sp-image-slot__label"><i class="bi <?= esc($slot['icon']) ?> me-1"></i><?= esc($slot['label']) ?></div>
                            <div class="sp-image-slot__hint"><?= esc($slot['hint']) ?></div>

                            <div class="sp-image-slot__preview">
                                <?php if ($slot['previewUrl'] !== ''): ?>
                                    <img id="<?= $previewId ?>" src="<?= sp_safe_url($slot['previewUrl']) ?>" alt="<?= esc($slot['label']) ?>">
                                <?php else: ?>
                                    <img id="<?= $previewId ?>" src="" alt="<?= esc($slot['label']) ?>" style="display:none">
                                    <span class="sp-image-slot__empty text-muted small"><i class="bi bi-image me-1"></i>Sem imagem</span>
                                <?php endif; ?>
                            </div>

                            <input type="file" class="form-control form-control-sm mb-2"
                                   id="<?= $fileId ?>" accept="<?= esc($slot['accept']) ?>">
                            <div class="form-text mb-2"><?= esc($slot['maxLabel']) ?></div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 sp-image-upload-btn"
                                        data-field="<?= esc($field) ?>" data-preview="<?= $previewId ?>" data-input="<?= $fileId ?>">
                                    <i class="bi bi-cloud-upload me-1"></i>Enviar
                                </button>
                                <?php if (($slot['current'] ?? '') !== ''): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger sp-image-remove-btn"
                                            data-preview="<?= $previewId ?>"
                                            data-default="<?= esc($slot['previewUrl']) ?>"
                                            data-remove-url="<?= esc(sp_route_url('admin.settings.personalization.images.delete', $field)) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- ── 5. AÇÕES ── -->
        <div class="sp-card">
            <div class="sp-card-body d-flex gap-2 justify-content-between align-items-center flex-wrap">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar personalização
                    </button>
                    <a href="<?= sp_safe_url(sp_route_url('admin.settings.personalization')) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Descartar
                    </a>
                </div>
                <form method="POST" action="<?= sp_safe_url(sp_route_url('admin.settings.personalization.reset')) ?>"
                      onsubmit="return confirm('Redefinir todas as configurações de personalização para o padrão?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Redefinir padrão
                    </button>
                </form>
            </div>
        </div>

    </form>
</div>

<style>
/* Personalização — grade de tema, cores e imagens (visual alinhado ao padrão de referência) */
.sp-theme-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; width: 100%; }
.sp-theme-card {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .5rem;
    padding: 1.25rem .75rem; border: 1.5px solid var(--sp-border, #e4e8ed); border-radius: var(--sp-radius-md, 10px);
    cursor: pointer; transition: border-color .15s, background .15s; background: var(--sp-bg-surface, #fff);
    width: 100%;
}
@media (max-width: 576px) {
    .sp-theme-grid { grid-template-columns: 1fr; }
}
.sp-theme-card:hover { border-color: var(--sp-primary, #1f9d57); }
.sp-theme-card--active { border-color: var(--sp-primary, #1f9d57); background: var(--sp-primary-soft, rgba(31,157,87,.06)); }
.sp-theme-radio { position: absolute; opacity: 0; pointer-events: none; }
.sp-theme-card__icon { color: var(--sp-text-secondary, #5a6472); }
.sp-theme-card--active .sp-theme-card__icon { color: var(--sp-primary, #1f9d57); }
.sp-theme-card__label { font-size: .8125rem; font-weight: 600; }

.sp-color-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
.sp-color-input-wrap { display: flex; gap: .5rem; align-items: center; }
.sp-color-picker { width: 2.5rem; height: 2.25rem; padding: 2px; border: 1px solid var(--sp-border, #e4e8ed); border-radius: var(--sp-radius-sm, 7px); cursor: pointer; flex-shrink: 0; }
.sp-color-text { flex: 1; }
.sp-help { display: block; font-size: .75rem; color: var(--sp-text-secondary, #5a6472); margin-top: .25rem; }

.sp-size-preview { font-variant-numeric: tabular-nums; font-weight: 600; min-width: 3rem; text-align: right; }

.sp-font-preview {
    margin-top: 1.25rem; padding: 1.25rem; border: 1px solid var(--sp-border, #e4e8ed);
    border-radius: var(--sp-radius-md, 10px); background: var(--sp-gray-50, #f6f8f9);
}
.sp-font-preview__name { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--sp-text-secondary, #5a6472); margin-bottom: .5rem; }
.sp-font-preview__sample { font-size: 1.25rem; margin-bottom: .75rem; }
.sp-font-preview__weights { display: flex; gap: 1rem; flex-wrap: wrap; font-size: .9375rem; color: var(--sp-text-secondary, #5a6472); }

.sp-image-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; }
.sp-image-slot__label { font-weight: 600; font-size: .875rem; margin-bottom: .25rem; }
.sp-image-slot__hint { font-size: .75rem; color: var(--sp-text-secondary, #5a6472); margin-bottom: .5rem; min-height: 2.2em; }
.sp-image-slot__preview {
    display: flex; align-items: center; justify-content: center; min-height: 64px;
    background: var(--sp-gray-100, #eef1f4); border-radius: var(--sp-radius-md, 10px); padding: .5rem; margin-bottom: .5rem;
}
.sp-image-slot__preview img { max-width: 100%; max-height: 56px; object-fit: contain; }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    const AVAILABLE_FONTS = <?= json_encode($availableFonts, JSON_UNESCAPED_UNICODE) ?>;

    const EP_UPLOAD = {
        logo_sidebar:      '<?= esc(sp_route_url('admin.settings.personalization.upload-logo-sidebar')) ?>',
        logo_auth:         '<?= esc(sp_route_url('admin.settings.personalization.upload-logo-auth')) ?>',
        logo:              '<?= esc(sp_route_url('admin.settings.personalization.upload-logo')) ?>',
        favicon:           '<?= esc(sp_route_url('admin.settings.personalization.upload-favicon')) ?>',
        login_background:  '<?= esc(sp_route_url('admin.settings.personalization.upload-login-background')) ?>',
    };
    const FIELD_NAME = {
        logo_sidebar: 'logo_sidebar', logo_auth: 'logo_auth', logo: 'logo',
        favicon: 'favicon', login_background: 'login_background',
    };

    function toast(msg, ok) {
        var c = document.getElementById('personalizationToast');
        if (!c) return;
        var el = document.createElement('div');
        el.style.cssText = 'background:' + (ok !== false ? 'var(--sp-success)' : 'var(--sp-danger)') + ';color:#fff;padding:.875rem 1.125rem;border-radius:var(--sp-radius-md);margin-bottom:.5rem;font-size:.875rem;display:flex;gap:.5rem;align-items:center;box-shadow:var(--sp-shadow-md);';
        el.innerHTML = '<i class="bi ' + (ok !== false ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i><span>' + msg + '</span>';
        c.appendChild(el);
        setTimeout(function() { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(function() { el.remove(); }, 350); }, 5000);
    }

    // ── Cores: picker <-> texto ─────────────────────────────
    document.querySelectorAll('.sp-color-picker').forEach(function (picker) {
        var textId = picker.getAttribute('data-color-target');
        var textInput = textId ? document.getElementById(textId) : null;
        if (!textInput) return;

        picker.addEventListener('input', function () { textInput.value = picker.value.toUpperCase(); });
        textInput.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(textInput.value)) picker.value = textInput.value;
        });
    });

    // ── Tipografia: fonte + tamanho ─────────────────────────
    var fontSelect  = document.getElementById('brand-font-family');
    var fontPreview = document.getElementById('sp-font-preview');
    var fontName    = document.getElementById('sp-font-name');
    var fontSample  = document.getElementById('sp-font-sample');
    var fontWeights = document.getElementById('sp-font-weights');

    function applyFont(family) {
        var style = '"' + family + '", sans-serif';
        [fontPreview, fontName, fontSample, fontWeights].forEach(function (el) { if (el) el.style.fontFamily = style; });
        if (fontName) fontName.textContent = family;
    }

    function loadAndPreviewFont(family) {
        if (family === 'Inter') { applyFont(family); return; }
        var spec = AVAILABLE_FONTS[family];
        if (!spec) { applyFont(family); return; }

        var linkId = 'preview-font-' + family.replace(/\s+/g, '-');
        if (!document.getElementById(linkId)) {
            var link = document.createElement('link');
            link.id = linkId;
            link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=' + spec + '&display=swap';
            link.onload = function () { applyFont(family); };
            document.head.appendChild(link);
        } else {
            applyFont(family);
        }
    }

    if (fontSelect) {
        fontSelect.addEventListener('change', function () { loadAndPreviewFont(fontSelect.value); });
        loadAndPreviewFont(fontSelect.value);
    }

    var fontSize = document.getElementById('brand-font-size');
    var fontSizeVal = document.getElementById('brand-font-size-val');
    if (fontSize && fontSizeVal) {
        fontSize.addEventListener('input', function () { fontSizeVal.textContent = fontSize.value + 'px'; });
    }

    // ── Tema: highlight do card selecionado ─────────────────
    document.querySelectorAll('.sp-theme-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.sp-theme-card').forEach(function (card) { card.classList.remove('sp-theme-card--active'); });
            radio.closest('.sp-theme-card').classList.add('sp-theme-card--active');
        });
    });

    // ── Imagens: preview on select, upload e remoção via AJAX ──
    function showPreview(img) {
        if (!img) return;
        img.style.display = '';
        var empty = img.parentElement ? img.parentElement.querySelector('.sp-image-slot__empty') : null;
        if (empty) empty.style.display = 'none';
    }

    document.querySelectorAll('input[type=file][id^="file_"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!this.files[0]) return;
            var field = this.id.replace('file_', '');
            var img = document.getElementById('preview_' + field);
            var reader = new FileReader();
            reader.onload = function (e) { if (img) { img.src = e.target.result; showPreview(img); } };
            reader.readAsDataURL(this.files[0]);
        });
    });

    document.querySelectorAll('.sp-image-upload-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var field = btn.getAttribute('data-field');
            var input = document.getElementById(btn.getAttribute('data-input'));
            var previewId = btn.getAttribute('data-preview');
            var endpoint = EP_UPLOAD[field];

            if (!input || !input.files[0]) { toast('Selecione um arquivo primeiro', false); return; }
            if (!endpoint) { toast('Endpoint de upload não configurado', false); return; }

            var fd = new FormData();
            fd.append(FIELD_NAME[field] || field, input.files[0]);

            try {
                var r = await spFetch(endpoint, { method: 'POST', body: fd });
                var j = await r.json();
                toast(j.success ? (j.message || 'Enviado!') : (j.message || 'Erro'), j.success);
                if (j.success && j.url) {
                    var img = document.getElementById(previewId);
                    if (img) { img.src = j.url + '?t=' + Date.now(); showPreview(img); }
                }
            } catch (e) { toast('Erro de comunicação', false); }
        });
    });

    document.querySelectorAll('.sp-image-remove-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Remover esta imagem?')) return;
            var previewId = btn.getAttribute('data-preview');
            var defaultUrl = btn.getAttribute('data-default');
            var endpoint = btn.getAttribute('data-remove-url');
            if (!endpoint) { toast('Endpoint de remoção não configurado', false); return; }

            try {
                var r = await spFetch(endpoint, { method: 'POST' });
                var j = await r.json();
                toast(j.success ? (j.message || 'Removido!') : (j.message || 'Erro'), j.success);
                if (j.success) {
                    var img = document.getElementById(previewId);
                    if (img) {
                        if (defaultUrl) {
                            img.src = defaultUrl + '?t=' + Date.now();
                        } else {
                            img.style.display = 'none';
                            var empty = img.parentElement ? img.parentElement.querySelector('.sp-image-slot__empty') : null;
                            if (empty) { empty.style.display = ''; } else if (img.parentElement) {
                                var span = document.createElement('span');
                                span.className = 'sp-image-slot__empty text-muted small';
                                span.innerHTML = '<i class="bi bi-image me-1"></i>Sem imagem';
                                img.parentElement.appendChild(span);
                            }
                        }
                    }
                    btn.remove();
                }
            } catch (e) { toast('Erro de comunicação', false); }
        });
    });
})();
</script>
<?= $this->endSection() ?>
