<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Aparência<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Aparência e Identidade Visual',
        'subtitle' => 'Nome da empresa, logo, favicon, cores e tipografia do sistema.',
        'icon'     => 'bi bi-palette-fill',
        'actions'  => [
            ['label' => 'Operacional', 'icon' => 'bi bi-sliders',  'url' => route_to('admin.settings')],
            ['label' => 'Sistema',     'icon' => 'bi bi-cpu-fill', 'url' => route_to('admin.settings.system')],
        ],
    ]) ?>


    <div id="appearanceToast" style="position:fixed;top:1rem;right:1rem;z-index:9999;min-width:300px"></div>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.appearance.update')) ?>"
          method="POST" enctype="multipart/form-data" id="form-appearance">
        <?= csrf_field() ?>

        <!-- 1. Identidade da empresa (full width) -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-building-fill"></i> Identidade da Empresa</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Nome da Empresa <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="company_name"
                               value="<?= esc($currentConfig['custom']['company_name'] ?? '') ?>"
                               required placeholder="Nome que aparecerá no sistema e relatórios">
                    </div>
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

        <!-- 2. Cores do sistema (full width, col-md-4 = 3 por linha × 2 linhas = 6 visíveis) -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-palette-fill"></i> Cores do Sistema</span>
                <span class="text-muted small">6 cores · 2 linhas de 3</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <?php
                    $colorFields = [
                        'primary_color'   => ['label' => 'Primária (verde marca)',   'default' => '#4fa14f'],
                        'secondary_color' => ['label' => 'Secundária',               'default' => '#3a7a3a'],
                        'success_color'   => ['label' => 'Sucesso',                  'default' => '#1abc9c'],
                        'warning_color'   => ['label' => 'Aviso',                    'default' => '#f0c43f'],
                        'danger_color'    => ['label' => 'Erro / Perigo',            'default' => '#e74c3c'],
                        'info_color'      => ['label' => 'Informação',               'default' => '#5b73e8'],
                    ];
                    foreach ($colorFields as $name => $cfg):
                        $val = $currentConfig['colors'][$name]
                            ?? $currentConfig['custom'][$name]
                            ?? $cfg['default'];
                    ?>
                    <!-- col-md-4 = 3 por linha, 6 campos = 2 linhas completas -->
                    <div class="col-md-4 col-sm-6">
                        <label class="form-label fw-semibold small"><?= $cfg['label'] ?></label>
                        <div class="input-group input-group-sm">
                            <input type="color"
                                   class="form-control form-control-color"
                                   name="<?= $name ?>"
                                   value="<?= esc($val) ?>"
                                   id="picker_<?= $name ?>"
                                   style="max-width:3rem;min-width:3rem;cursor:pointer">
                            <input type="text"
                                   class="form-control font-monospace"
                                   value="<?= esc($val) ?>"
                                   id="text_<?= $name ?>"
                                   maxlength="7"
                                   placeholder="#000000"
                                   oninput="syncColor('<?= $name ?>', this.value)"
                                   pattern="^#[0-9A-Fa-f]{6}$">
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="sp-callout-neutral mt-3">
                    <i class="bi bi-info-circle me-2"></i>
                    Clique na swatch colorida para abrir o seletor, ou digite o código HEX diretamente.
                </div>
            </div>
        </div>

        <!-- 3. Tipografia (full width) -->
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
                                '"Open Sans", sans-serif' => 'Open Sans (padrão SupportERP)',
                                '"Inter", sans-serif'     => 'Inter',
                                '"Roboto", sans-serif'    => 'Roboto',
                                '"Nunito", sans-serif'    => 'Nunito',
                                '"Poppins", sans-serif'   => 'Poppins',
                                'system-ui, sans-serif'   => 'Fonte do sistema',
                            ];
                            $cur = $currentConfig['typography']['font_family'] ?? '"Open Sans", sans-serif';
                            foreach ($fonts as $v => $l):
                            ?>
                                <option value="<?= esc($v) ?>" <?= $cur === $v ? 'selected' : '' ?>>
                                    <?= esc($l) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Aplica-se a todo o sistema após salvar</div>
                    </div>
                    <div class="col-md-7 d-flex align-items-end">
                        <div class="sp-callout-neutral" style="font-size:.875rem;flex:1">
                            <strong>Prévia:</strong>
                            <span id="font-preview" style="margin-left:.5rem">
                                SupportPONTO — Sistema de Ponto Eletrônico
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Logo e imagens (3 colunas) -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-images"></i> Imagens e Arquivos</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">

                    <!-- Logo -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-block">
                            <i class="bi bi-image-fill me-1"></i>Logo da empresa
                        </label>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md)">
                            <img id="logoPreview"
                                 src="<?= sp_safe_url(support_logo_url('small')) ?>"
                                 alt="Logo" class="img-fluid" style="max-height:60px">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" name="logo"
                               id="logoFile" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text mb-2">PNG/JPG/WEBP · Máx 2MB · Rec: 512×512</div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn-upload-logo">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar logo
                        </button>
                    </div>

                    <!-- Favicon -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-block">
                            <i class="bi bi-stars me-1"></i>Favicon (ícone da aba)
                        </label>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:72px;display:flex;align-items:center;justify-content:center">
                            <img id="faviconPreview"
                                 src="<?= sp_safe_url(asset_url('assets/img/favicon.png')) ?>"
                                 alt="Favicon" style="width:32px;height:32px;image-rendering:crisp-edges">
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" name="favicon"
                               id="faviconFile" accept="image/png,image/x-icon,image/vnd.microsoft.icon">
                        <div class="form-text mb-2">PNG ou ICO · Máx 1MB · Rec: 32×32</div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100" id="btn-upload-favicon">
                            <i class="bi bi-cloud-upload me-1"></i>Enviar favicon
                        </button>
                    </div>

                    <!-- Capa de login -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-block">
                            <i class="bi bi-card-image me-1"></i>Capa da tela de login
                        </label>
                        <div class="text-center mb-2 p-2" style="background:var(--sp-gray-100);border-radius:var(--sp-radius-md);min-height:72px;display:flex;align-items:center;justify-content:center">
                            <span class="text-muted small">Imagem de fundo do login</span>
                        </div>
                        <input type="file" class="form-control form-control-sm mb-2" name="login_background"
                               accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">PNG/JPG/WEBP · Máx 5MB · Rec: 1920×1080</div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Botões de ação -->
        <div class="sp-card">
            <div class="sp-card-body d-flex gap-2 justify-content-between align-items-center">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar Aparência
                    </button>
                    <a href="<?= sp_safe_url(sp_route_url('admin.settings.appearance')) ?>"
                       class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-counterclockwise me-2"></i>Descartar
                    </a>
                </div>
                <form method="POST"
                      action="<?= sp_safe_url(sp_route_url('admin.settings.appearance.reset')) ?>"
                      onsubmit="return confirm('Resetar todas as configurações de aparência para o padrão?')">
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

    const EP_LOGO    = '<?= esc(sp_route_url('admin.settings.appearance.upload-logo')) ?>';
    const EP_FAVICON = '<?= esc(sp_route_url('admin.settings.appearance.upload-favicon')) ?>';

    function toast(msg, ok) {
        var c = document.getElementById('appearanceToast');
        if (!c) return;
        var el = document.createElement('div');
        var bg = ok !== false ? 'var(--sp-success)' : 'var(--sp-danger)';
        el.style.cssText = 'background:' + bg + ';color:#fff;padding:.875rem 1.125rem;border-radius:var(--sp-radius-md);margin-bottom:.5rem;font-size:.875rem;display:flex;gap:.5rem;align-items:center;box-shadow:var(--sp-shadow-md);';
        el.innerHTML = '<i class="bi ' + (ok !== false ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i><span>' + msg + '</span>';
        c.appendChild(el);
        setTimeout(function() { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(function(){ el.remove(); }, 350); }, 5000);
    }

    // Sincronizar picker ↔ text global (usada pelo oninput inline)
    window.syncColor = function(name, val) {
        var picker = document.getElementById('picker_' + name);
        var text   = document.getElementById('text_' + name);
        if (picker && /^#[0-9A-Fa-f]{6}$/.test(val)) picker.value = val;
    };

    // Picker → text sync
    document.querySelectorAll('input[type=color]').forEach(function(picker) {
        var name = picker.name;
        picker.addEventListener('input', function() {
            var text = document.getElementById('text_' + name);
            if (text) text.value = picker.value;
        });
    });

    // Font preview
    var fontSel = document.querySelector('[name=font_family]');
    var preview = document.getElementById('font-preview');
    if (fontSel && preview) {
        function updatePreview() { preview.style.fontFamily = fontSel.value; }
        fontSel.addEventListener('change', updatePreview);
        updatePreview();
    }

    // Upload logo
    async function uploadFile(inputId, field, endpoint, previewId) {
        var file = document.getElementById(inputId);
        if (!file || !file.files[0]) { toast('Selecione um arquivo primeiro', false); return; }
        var fd = new FormData();
        fd.append(field, file.files[0]);
        try {
            var r = await spFetch(endpoint, { method: 'POST', body: fd });
            var j = await r.json();
            toast(j.success ? (j.message || 'Arquivo enviado!') : (j.message || 'Erro'), j.success);
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

    // Preview logo ao selecionar
    document.getElementById('logoFile')?.addEventListener('change', function() {
        if (!this.files[0]) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('logoPreview');
            if (img) img.src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
    });

})();
</script>
<?= $this->endSection() ?>
