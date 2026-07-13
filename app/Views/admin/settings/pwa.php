<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>PWA<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Progressive Web App (PWA)',
        'subtitle' => 'Personalize o aplicativo instalável do SupportPONTO.',
        'icon'     => 'bi bi-phone-fill',
    ]) ?>

    <?= view('components/flash_messages') ?>

    <!-- Status do PWA -->
    <?php
    $iconCount = count(array_filter($icons));
    $hasIcons  = $iconCount >= 2;
    ?>
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-layers',
                'iconColor' => $hasIcons ? 'success' : 'warning',
                'value' => (string) $iconCount,
                'label' => 'Ícones',
                'indicator' => $hasIcons ? 'Instalável ✓' : 'Mínimo: 2',
                'indicatorType' => $hasIcons ? 'success' : 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-file-earmark-text',
                'iconColor' => 'primary',
                'value' => esc(mb_strimwidth($settings['pwa_app_name'] ?? '—', 0, 16, '…')),
                'label' => 'Nome',
                'indicator' => 'Título do app',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-box',
                'iconColor' => 'primary',
                'value' => esc($settings['pwa_display'] ?? 'standalone'),
                'label' => 'Modo',
                'indicator' => 'Display',
                'indicatorType' => 'neutral',
            ]) ?>
        </div>
        <div class="col-md-3">
            <?= view('components/kpi', [
                'icon' => 'bi bi-file-earmark-code',
                'iconColor' => 'success',
                'value' => 'JSON',
                'label' => 'Manifest',
                'indicator' => 'Rota dinâmica ativa',
                'indicatorType' => 'success',
            ]) ?>
        </div>
    </div>

    <!-- Configurações gerais -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-gear-fill"></i> Configurações gerais</span>
            <span class="text-muted small">Dados básicos do aplicativo instalável.</span>
        </div>
        <div class="sp-card-body">
            <form action="<?= sp_safe_url(sp_route_url('admin.settings.pwa.update')) ?>" method="POST" id="pwa-form">
                <?= csrf_field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="pwa-name">Nome completo <span class="text-danger">*</span></label>
                        <input type="text" id="pwa-name" class="form-control" name="pwa_app_name"
                               value="<?= esc(old('pwa_app_name', $settings['pwa_app_name'] ?? '')) ?>"
                               maxlength="80" required>
                        <span class="sp-help">Exibido na tela de instalação e no gerenciador de apps.</span>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="pwa-short">Nome curto <span class="text-danger">*</span></label>
                        <input type="text" id="pwa-short" class="form-control" name="pwa_short_name"
                               value="<?= esc(old('pwa_short_name', $settings['pwa_short_name'] ?? '')) ?>"
                               maxlength="30" required>
                        <span class="sp-help">Exibido sob o ícone na tela inicial (máx 30 caracteres).</span>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold" for="pwa-desc">Descrição</label>
                        <input type="text" id="pwa-desc" class="form-control" name="pwa_description"
                               value="<?= esc(old('pwa_description', $settings['pwa_description'] ?? '')) ?>"
                               maxlength="200">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="pwa-theme">Cor do tema <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="pwa-theme-picker"
                                   value="<?= esc($settings['pwa_theme_color'] ?? '#4fa14f') ?>"
                                   data-color-target="pwa-theme" class="sp-color-picker">
                            <input type="text" id="pwa-theme" class="form-control font-monospace" name="pwa_theme_color"
                                   value="<?= esc(old('pwa_theme_color', $settings['pwa_theme_color'] ?? '#4fa14f')) ?>"
                                   pattern="^#[0-9a-fA-F]{6}$" maxlength="7" data-color-picker="pwa-theme-picker" required>
                        </div>
                        <span class="sp-help">Cor da barra de status e elementos do sistema.</span>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="pwa-bg">Cor de fundo <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 align-items-center">
                            <input type="color" id="pwa-bg-picker"
                                   value="<?= esc($settings['pwa_background_color'] ?? '#ffffff') ?>"
                                   data-color-target="pwa-bg" class="sp-color-picker">
                            <input type="text" id="pwa-bg" class="form-control font-monospace" name="pwa_background_color"
                                   value="<?= esc(old('pwa_background_color', $settings['pwa_background_color'] ?? '#ffffff')) ?>"
                                   pattern="^#[0-9a-fA-F]{6}$" maxlength="7" data-color-picker="pwa-bg-picker" required>
                        </div>
                        <span class="sp-help">Cor da splash screen enquanto o app carrega.</span>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="pwa-display">Modo de exibição</label>
                        <?php $curD = old('pwa_display', $settings['pwa_display'] ?? 'standalone'); ?>
                        <select id="pwa-display" class="form-select" name="pwa_display">
                            <?php foreach (['standalone' => 'Standalone (recomendado)', 'fullscreen' => 'Fullscreen', 'minimal-ui' => 'Minimal UI', 'browser' => 'Navegador'] as $val => $label): ?>
                                <option value="<?= esc($val) ?>" <?= $curD === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="sp-help"><strong>Standalone</strong>: app sem barra de navegação do navegador.</span>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold" for="pwa-orientation">Orientação</label>
                        <?php $curO = old('pwa_orientation', $settings['pwa_orientation'] ?? 'any'); ?>
                        <select id="pwa-orientation" class="form-select" name="pwa_orientation">
                            <?php foreach (['any' => 'Qualquer', 'portrait' => 'Portrait (vertical)', 'landscape' => 'Landscape (horizontal)'] as $val => $label): ?>
                                <option value="<?= esc($val) ?>" <?= $curO === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="pwa-start-url">URL de início (start_url)</label>
                        <input type="text" id="pwa-start-url" class="form-control" name="pwa_start_url"
                               value="<?= esc(old('pwa_start_url', $settings['pwa_start_url'] ?? '/')) ?>"
                               placeholder="/">
                        <span class="sp-help">Rota que abre quando o usuário lança o app instalado.</span>
                    </div>
                </div>

                <div class="d-flex mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar configurações
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ícones do aplicativo -->
    <div class="sp-card">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-images"></i> Ícones do aplicativo</span>
            <span class="text-muted small">Necessário pelo menos 192×192 e 512×512 para o PWA ser instalável.</span>
        </div>
        <div class="sp-card-body">
            <div class="sp-image-grid">
                <?php
                $iconSlots = [
                    192 => ['Ícone 192×192', 'Tela inicial e notificações. Recomendado: PNG quadrado 192×192 px.'],
                    512 => ['Ícone 512×512', 'Splash screen e instalação. Recomendado: PNG quadrado 512×512 px.'],
                ];
                foreach ($iconSlots as $size => [$label, $hint]):
                    $existing = $icons[$size] ?? null;
                    $previewId = 'preview_icon_' . $size;
                    $fileId = 'file_icon_' . $size;
                ?>
                    <div class="sp-image-slot">
                        <div class="sp-image-slot__label"><?= esc($label) ?></div>
                        <div class="sp-image-slot__hint"><?= esc($hint) ?></div>

                        <div class="sp-image-slot__preview">
                            <?php if ($existing): ?>
                                <img id="<?= $previewId ?>" src="<?= sp_safe_url($existing['url']) ?>" alt="<?= esc($label) ?>">
                            <?php else: ?>
                                <img id="<?= $previewId ?>" src="" alt="<?= esc($label) ?>" style="display:none">
                                <span class="sp-image-slot__empty text-muted small"><i class="bi bi-image me-1"></i>Sem ícone</span>
                            <?php endif; ?>
                        </div>

                        <input type="file" class="form-control form-control-sm mb-2"
                               id="<?= $fileId ?>" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text mb-2">PNG/JPG/WEBP · Máx 4MB</div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 sp-icon-upload-btn"
                                    data-size="<?= $size ?>" data-preview="<?= $previewId ?>" data-input="<?= $fileId ?>"
                                    data-upload-url="<?= esc(sp_route_url('admin.settings.pwa.icon.upload', $size)) ?>">
                                <i class="bi bi-cloud-upload me-1"></i>Enviar
                            </button>
                            <?php if ($existing): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger sp-icon-remove-btn"
                                        data-preview="<?= $previewId ?>"
                                        data-remove-url="<?= esc(sp_route_url('admin.settings.pwa.icon.delete', $size)) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.sp-color-picker { width: 2.5rem; height: 2.25rem; padding: 2px; border: 1px solid var(--sp-border, #e4e8ed); border-radius: var(--sp-radius-sm, 7px); cursor: pointer; flex-shrink: 0; }
.sp-help { display: block; font-size: .75rem; color: var(--sp-text-secondary, #5a6472); margin-top: .25rem; }
.sp-image-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; }
.sp-image-slot__label { font-weight: 600; font-size: .875rem; margin-bottom: .25rem; }
.sp-image-slot__hint { font-size: .75rem; color: var(--sp-text-secondary, #5a6472); margin-bottom: .5rem; min-height: 2.2em; }
.sp-image-slot__preview {
    display: flex; align-items: center; justify-content: center; min-height: 96px;
    background: var(--sp-gray-100, #eef1f4); border-radius: var(--sp-radius-md, 10px); padding: .5rem; margin-bottom: .5rem;
}
.sp-image-slot__preview img { max-width: 100%; max-height: 88px; object-fit: contain; }
</style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    // Cores: picker <-> texto
    document.querySelectorAll('.sp-color-picker').forEach(function (picker) {
        var textId = picker.getAttribute('data-color-target');
        var textInput = textId ? document.getElementById(textId) : null;
        if (!textInput) return;

        picker.addEventListener('input', function () { textInput.value = picker.value.toUpperCase(); });
        textInput.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(textInput.value)) picker.value = textInput.value;
        });
    });

    function toast(msg, ok) {
        var el = document.createElement('div');
        el.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:9999;min-width:280px;background:' + (ok !== false ? 'var(--sp-success)' : 'var(--sp-danger)') + ';color:#fff;padding:.875rem 1.125rem;border-radius:var(--sp-radius-md);margin-bottom:.5rem;font-size:.875rem;display:flex;gap:.5rem;align-items:center;box-shadow:var(--sp-shadow-md);';
        el.innerHTML = '<i class="bi ' + (ok !== false ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill') + '"></i><span>' + msg + '</span>';
        document.body.appendChild(el);
        setTimeout(function () { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 350); }, 5000);
    }

    function showPreview(img) {
        if (!img) return;
        img.style.display = '';
        var empty = img.parentElement ? img.parentElement.querySelector('.sp-image-slot__empty') : null;
        if (empty) empty.style.display = 'none';
    }

    document.querySelectorAll('input[type=file][id^="file_icon_"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (!this.files[0]) return;
            var size = this.id.replace('file_icon_', '');
            var img = document.getElementById('preview_icon_' + size);
            var reader = new FileReader();
            reader.onload = function (e) { if (img) { img.src = e.target.result; showPreview(img); } };
            reader.readAsDataURL(this.files[0]);
        });
    });

    document.querySelectorAll('.sp-icon-upload-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var input = document.getElementById(btn.getAttribute('data-input'));
            var previewId = btn.getAttribute('data-preview');
            var endpoint = btn.getAttribute('data-upload-url');

            if (!input || !input.files[0]) { toast('Selecione um arquivo primeiro', false); return; }

            var fd = new FormData();
            fd.append('icon', input.files[0]);

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

    document.querySelectorAll('.sp-icon-remove-btn').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (!confirm('Remover este ícone?')) return;
            var previewId = btn.getAttribute('data-preview');
            var endpoint = btn.getAttribute('data-remove-url');

            try {
                var r = await spFetch(endpoint, { method: 'POST' });
                var j = await r.json();
                toast(j.success ? (j.message || 'Removido!') : (j.message || 'Erro'), j.success);
                if (j.success) {
                    var img = document.getElementById(previewId);
                    if (img) {
                        img.style.display = 'none';
                        img.src = '';
                        var empty = img.parentElement ? img.parentElement.querySelector('.sp-image-slot__empty') : null;
                        if (empty) {
                            empty.style.display = '';
                        } else if (img.parentElement) {
                            var span = document.createElement('span');
                            span.className = 'sp-image-slot__empty text-muted small';
                            span.innerHTML = '<i class="bi bi-image me-1"></i>Sem ícone';
                            img.parentElement.appendChild(span);
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
