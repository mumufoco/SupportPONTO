<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Personalização<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Personalização',
        'subtitle' => 'Identidade visual do sistema: cores, tipografia, logos, favicon e capa de login.',
        'icon'     => 'bi bi-palette-fill',
        'actions'  => [
                                ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <div id="personalizationToast" style="position:fixed;top:1rem;right:1rem;z-index:9999;min-width:300px"></div>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.personalization.update')) ?>"
          method="POST" enctype="multipart/form-data" id="form-personalization">
        <?= csrf_field() ?>

        <!-- Tema -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-moon-stars-fill"></i> Tema</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Modo de tema padrão</label>
                        <select class="form-select" name="theme_mode">
                            <?php $tm = $currentConfig['custom']['theme_mode'] ?? 'dark'; ?>
                            <option value="dark"  <?= $tm === 'dark'  ? 'selected' : '' ?>>Escuro</option>
                            <option value="light" <?= $tm === 'light' ? 'selected' : '' ?>>Claro</option>
                            <option value="auto"  <?= $tm === 'auto'  ? 'selected' : '' ?>>Automático (sistema)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cores -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-palette-fill"></i> Cores do Sistema</span>
                <span class="text-muted small">6 cores · 2 linhas de 3</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <?php
                    $colorFields = [
                        'primary_color'   => ['label' => 'Primária (verde marca)', 'default' => '#4fa14f'],
                        'secondary_color' => ['label' => 'Secundária',             'default' => '#3a7a3a'],
                        'success_color'   => ['label' => 'Sucesso',                'default' => '#1abc9c'],
                        'warning_color'   => ['label' => 'Aviso',                  'default' => '#f0c43f'],
                        'danger_color'    => ['label' => 'Erro / Perigo',          'default' => '#e74c3c'],
                        'info_color'      => ['label' => 'Informação',             'default' => '#5b73e8'],
                    ];
                    foreach ($colorFields as $name => $cfg):
                        $shortKey = str_replace('_color', '', $name);
                        $val = $settings[$name]
                            ?? $currentConfig['colors'][$shortKey]
                            ?? $currentConfig['colors'][$name]
                            ?? $currentConfig['custom'][$name]
                            ?? $cfg['default'];
                    ?>
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label fw-semibold small"><?= $cfg['label'] ?></label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color"
                                   name="<?= $name ?>" value="<?= esc($val) ?>"
                                   id="picker_<?= $name ?>" style="max-width:3rem;min-width:3rem;cursor:pointer">
                            <input type="text" class="form-control font-monospace"
                                   value="<?= esc($val) ?>" id="text_<?= $name ?>"
                                   maxlength="7" placeholder="#000000"
                                   oninput="syncColor('<?= $name ?>', this.value)"
                                   pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sp-callout-neutral mt-3">
                    <i class="bi bi-info-circle me-2"></i>Clique na swatch para abrir o seletor ou digite o código HEX.
                </div>
            </div>
        </div>

        <!-- Tipografia -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-type"></i> Tipografia</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Fonte do sistema</label>
                        <select class="form-select" name="font_family">
                            <?php
                            $fonts = [
                                '"Open Sans", sans-serif' => 'Open Sans (padrão)',
                                '"Inter", sans-serif'     => 'Inter',
                                '"Roboto", sans-serif'    => 'Roboto',
                                '"Nunito", sans-serif'    => 'Nunito',
                                '"Poppins", sans-serif'   => 'Poppins',
                                'system-ui, sans-serif'   => 'Fonte do sistema',
                            ];
                            $cur = $currentConfig['typography']['font_family'] ?? '"Open Sans", sans-serif';
                            foreach ($fonts as $v => $l): ?>
                                <option value="<?= esc($v) ?>" <?= $cur === $v ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7 d-flex align-items-end">
                        <div class="sp-callout-neutral" style="flex:1;font-size:.875rem">
                            <strong>Prévia:</strong>
                            <span id="font-preview" style="margin-left:.5rem">SupportPONTO — Sistema de Ponto Eletrônico</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Imagens -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-images"></i> Imagens e Arquivos</span>
                <span class="text-muted small">5 slots · cada imagem é redimensionada automaticamente</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">

                    <!-- Logo do menu lateral -->
                    <div class="col-md-4 col-lg">
                        <label class="form-label fw-semibold d-block"><i class="bi bi-layout-sidebar me-1"></i>Logo do menu lateral</label>
                        <div class="sp-callout-neutral mb-2" style="font-size:.75rem;padding:.4rem .6rem">
                            Aparece acima do menu de navegação · Proporção livre · Salva em 512×128px
                        </div>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:56px;display:flex;align-items:center;justify-content:center">
                            <img id="logoSidebarPreview" src="<?= sp_safe_url(support_logo_url('sidebar')) ?>" alt="Logo sidebar" style="max-width:160px;max-height:48px;object-fit:contain;">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" id="logoSidebarFile" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text mb-2">PNG/JPG/WEBP · Máx 2MB</div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn-upload-logo-sidebar">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar
                        </button>
                    </div>

                    <!-- Logo para páginas sem login -->
                    <div class="col-md-4 col-lg">
                        <label class="form-label fw-semibold d-block"><i class="bi bi-door-open me-1"></i>Logo páginas sem login</label>
                        <div class="sp-callout-neutral mb-2" style="font-size:.75rem;padding:.4rem .6rem">
                            Login, recuperação, 2FA, ponto sem login · Salva em 512×512px
                        </div>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:56px;display:flex;align-items:center;justify-content:center">
                            <img id="logoAuthPreview" src="<?= sp_safe_url(support_logo_url('auth')) ?>" alt="Logo auth" style="width:48px;height:48px;object-fit:contain;border-radius:8px;">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" id="logoAuthFile" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text mb-2">PNG/JPG/WEBP · Máx 2MB</div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn-upload-logo-auth">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar
                        </button>
                    </div>

                    <!-- Logo geral (fallback) -->
                    <div class="col-md-4 col-lg">
                        <label class="form-label fw-semibold d-block"><i class="bi bi-image-fill me-1"></i>Logo geral (fallback)</label>
                        <div class="sp-callout-neutral mb-2" style="font-size:.75rem;padding:.4rem .6rem">
                            Usado quando os slots acima não têm imagem definida · Salva em 512×512px
                        </div>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:56px;display:flex;align-items:center;justify-content:center">
                            <img id="logoPreview" src="<?= sp_safe_url(support_logo_url('small')) ?>" alt="Logo" class="img-fluid" style="max-height:48px">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" id="logoFile" name="logo" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text mb-2">PNG/JPG/WEBP · Máx 2MB</div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn-upload-logo">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar
                        </button>
                    </div>

                    <!-- Favicon -->
                    <div class="col-md-4 col-lg">
                        <label class="form-label fw-semibold d-block"><i class="bi bi-stars me-1"></i>Favicon (ícone da aba)</label>
                        <div class="sp-callout-neutral mb-2" style="font-size:.75rem;padding:.4rem .6rem">
                            Ícone da aba do navegador · Gera 4 tamanhos (16, 32, 48, 180px)
                        </div>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:56px;display:flex;align-items:center;justify-content:center">
                            <img id="faviconPreview" src="<?= sp_safe_url(support_favicon_url()) ?>" alt="Favicon" style="width:32px;height:32px;image-rendering:crisp-edges">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" id="faviconFile" name="favicon" accept="image/png,image/x-icon">
                        <div class="form-text mb-2">PNG ou ICO · Máx 1MB</div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn-upload-favicon">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar
                        </button>
                    </div>

                    <!-- Capa de login -->
                    <div class="col-md-4 col-lg">
                        <label class="form-label fw-semibold d-block"><i class="bi bi-card-image me-1"></i>Capa de login</label>
                        <div class="sp-callout-neutral mb-2" style="font-size:.75rem;padding:.4rem .6rem">
                            Imagem de fundo da tela de login · Sem redimensionamento forçado
                        </div>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:56px;display:flex;align-items:center;justify-content:center">
                            <span class="text-muted small"><i class="bi bi-image me-1"></i>Fundo do login</span>
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" name="login_background" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">PNG/JPG/WEBP · Máx 5MB · Rec: 1920×1080</div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="sp-card">
            <div class="sp-card-body d-flex gap-2 justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar Personalização
                    </button>
                    <a href="<?= sp_safe_url(sp_route_url('admin.settings.personalization')) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Descartar
                    </a>
                </div>
                <form method="POST" action="<?= sp_safe_url(sp_route_url('admin.settings.personalization.reset')) ?>"
                      onsubmit="return confirm('Resetar personalização para o padrão?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash me-1"></i>Resetar padrão
                    </button>
                </form>
            </div>
        </div>

    </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    const EP_LOGO         = '<?= esc(sp_route_url('admin.settings.personalization.upload-logo')) ?>';
    const EP_FAVICON      = '<?= esc(sp_route_url('admin.settings.personalization.upload-favicon')) ?>';
    const EP_LOGO_AUTH    = '<?= esc(sp_route_url('admin.settings.personalization.upload-logo-auth')) ?>';
    const EP_LOGO_SIDEBAR = '<?= esc(sp_route_url('admin.settings.personalization.upload-logo-sidebar')) ?>';

    function toast(msg, ok) {
        var c = document.getElementById('personalizationToast');
        if (!c) return;
        var el = document.createElement('div');
        el.style.cssText = 'background:' + (ok !== false ? 'var(--sp-success)' : 'var(--sp-danger)') + ';color:#fff;padding:.875rem 1.125rem;border-radius:var(--sp-radius-md);margin-bottom:.5rem;font-size:.875rem;display:flex;gap:.5rem;align-items:center;box-shadow:var(--sp-shadow-md);';
        el.innerHTML = '<i class="bi ' + (ok !== false ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i><span>' + msg + '</span>';
        c.appendChild(el);
        setTimeout(function() { el.style.transition='opacity .3s';el.style.opacity='0';setTimeout(function(){el.remove();},350); }, 5000);
    }

    window.syncColor = function(name, val) {
        var picker = document.getElementById('picker_' + name);
        if (picker && /^#[0-9A-Fa-f]{6}$/.test(val)) picker.value = val;
    };

    document.querySelectorAll('input[type=color]').forEach(function(picker) {
        var name = picker.name;
        picker.addEventListener('input', function() {
            var text = document.getElementById('text_' + name);
            if (text) text.value = picker.value;
        });
    });

    var fontSel = document.querySelector('[name=font_family]');
    var preview = document.getElementById('font-preview');
    if (fontSel && preview) {
        fontSel.addEventListener('change', function() { preview.style.fontFamily = fontSel.value; });
        preview.style.fontFamily = fontSel.value;
    }

    async function uploadFile(inputId, field, endpoint, previewId) {
        var file = document.getElementById(inputId);
        if (!file || !file.files[0]) { toast('Selecione um arquivo primeiro', false); return; }
        var fd = new FormData();
        fd.append(field, file.files[0]);
        try {
            var r = await spFetch(endpoint, { method: 'POST', body: fd });
            var j = await r.json();
            toast(j.success ? (j.message || 'Enviado!') : (j.message || 'Erro'), j.success);
            if (j.success && j.url) {
                var img = document.getElementById(previewId);
                if (img) img.src = j.url + '?t=' + Date.now();
            }
        } catch (e) { toast('Erro de comunicação', false); }
    }

    document.getElementById('btn-upload-logo')?.addEventListener('click', function() {
        uploadFile('logoFile', 'logo', EP_LOGO, 'logoPreview');
    });
    document.getElementById('btn-upload-favicon')?.addEventListener('click', function() {
        uploadFile('faviconFile', 'favicon', EP_FAVICON, 'faviconPreview');
    });
    document.getElementById('btn-upload-logo-auth')?.addEventListener('click', function() {
        uploadFile('logoAuthFile', 'logo_auth', EP_LOGO_AUTH, 'logoAuthPreview');
    });
    document.getElementById('btn-upload-logo-sidebar')?.addEventListener('click', function() {
        uploadFile('logoSidebarFile', 'logo_sidebar', EP_LOGO_SIDEBAR, 'logoSidebarPreview');
    });

    // Preview on file select
    ['logoFile:logoPreview', 'logoAuthFile:logoAuthPreview', 'logoSidebarFile:logoSidebarPreview'].forEach(function(pair) {
        var parts = pair.split(':');
        document.getElementById(parts[0])?.addEventListener('change', function() {
            if (!this.files[0]) return;
            var reader = new FileReader();
            var previewId = parts[1];
            reader.onload = function(e) { var img = document.getElementById(previewId); if (img) img.src = e.target.result; };
            reader.readAsDataURL(this.files[0]);
        });
    });
})();
</script>
<?= $this->endSection() ?>
