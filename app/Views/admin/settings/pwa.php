<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>PWA<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'PWA — Progressive Web App',
        'subtitle' => 'Configurações para instalação do sistema como app no dispositivo do usuário.',
        'icon'     => 'bi bi-phone-fill',
        'actions'  => [
            ['label' => 'Configurações', 'icon' => 'bi bi-grid-fill', 'url' => sp_admin_settings_index_url()],
        ],
    ]) ?>

    <?= view('components/flash_messages') ?>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.pwa.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-gear-fill"></i> Configurações do PWA</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">PWA habilitado</label>
                        <select class="form-select" name="pwa_enabled">
                            <option value="1" <?= ($settings['pwa_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>Sim</option>
                            <option value="0" <?= ($settings['pwa_enabled'] ?? '1') !== '1' ? 'selected' : '' ?>>Não</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Nome do app</label>
                        <input type="text" class="form-control" name="pwa_app_name"
                               value="<?= esc($settings['pwa_app_name'] ?? 'SupportPONTO') ?>"
                               placeholder="SupportPONTO" maxlength="80">
                        <div class="form-text">Nome completo exibido ao instalar</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nome curto</label>
                        <input type="text" class="form-control" name="pwa_short_name"
                               value="<?= esc($settings['pwa_short_name'] ?? 'PONTO') ?>"
                               placeholder="PONTO" maxlength="12">
                        <div class="form-text">Exibido na tela inicial (máx 12 chars)</div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Descrição</label>
                        <input type="text" class="form-control" name="pwa_description"
                               value="<?= esc($settings['pwa_description'] ?? 'Sistema de Ponto Eletrônico') ?>"
                               placeholder="Descrição do app" maxlength="200">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cor do tema</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color"
                                   name="pwa_theme_color" id="picker_pwa_theme"
                                   value="<?= esc($settings['pwa_theme_color'] ?? '#4fa14f') ?>"
                                   style="max-width:3rem;cursor:pointer">
                            <input type="text" class="form-control font-monospace"
                                   value="<?= esc($settings['pwa_theme_color'] ?? '#4fa14f') ?>"
                                   id="text_pwa_theme" maxlength="7"
                                   oninput="syncPwaColor('pwa_theme_color','theme',this.value)">
                        </div>
                        <div class="form-text">Cor da barra do navegador/status bar</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Cor de fundo (splash)</label>
                        <div class="input-group input-group-sm">
                            <input type="color" class="form-control form-control-color"
                                   name="pwa_background_color" id="picker_pwa_bg"
                                   value="<?= esc($settings['pwa_background_color'] ?? '#ffffff') ?>"
                                   style="max-width:3rem;cursor:pointer">
                            <input type="text" class="form-control font-monospace"
                                   value="<?= esc($settings['pwa_background_color'] ?? '#ffffff') ?>"
                                   id="text_pwa_bg" maxlength="7"
                                   oninput="syncPwaColor('pwa_background_color','bg',this.value)">
                        </div>
                        <div class="form-text">Cor exibida na tela splash</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Modo de exibição</label>
                        <select class="form-select" name="pwa_display">
                            <?php
                            $displays = [
                                'standalone'        => 'Standalone (app nativo)',
                                'fullscreen'        => 'Tela cheia',
                                'minimal-ui'        => 'Minimal UI',
                                'browser'           => 'Navegador',
                            ];
                            $curD = $settings['pwa_display'] ?? 'standalone';
                            foreach ($displays as $k => $l): ?>
                                <option value="<?= esc($k) ?>" <?= $curD === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Orientação</label>
                        <select class="form-select" name="pwa_orientation">
                            <?php
                            $orients = ['any' => 'Qualquer', 'portrait' => 'Retrato', 'landscape' => 'Paisagem'];
                            $curO = $settings['pwa_orientation'] ?? 'any';
                            foreach ($orients as $k => $l): ?>
                                <option value="<?= esc($k) ?>" <?= $curO === $k ? 'selected' : '' ?>><?= esc($l) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">URL de início (start_url)</label>
                        <input type="text" class="form-control" name="pwa_start_url"
                               value="<?= esc($settings['pwa_start_url'] ?? '/') ?>"
                               placeholder="/">
                        <div class="form-text">Rota que abre quando o usuário lança o app instalado</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sp-card mt-4 mb-3">
            <div class="sp-card-body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(sp_admin_settings_index_url()) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar PWA
                </button>
            </div>
        </div>
    </form>

    <!-- Imagens do PWA -->
    <div class="sp-card mb-3">
        <div class="sp-card-header">
            <span class="sp-card-title"><i class="bi bi-images"></i> Imagens do PWA</span>
        </div>
        <div class="sp-card-body">
            <div class="sp-callout-neutral mb-3">
                <i class="bi bi-folder me-2"></i>Imagens armazenadas em: <code>public/assets/uploads/pwa/</code>
            </div>
            <div class="row g-3">
                <!-- PWA Icon -->
                <div class="col-md-4">
                    <div class="sp-card h-100">
                        <div class="sp-card-header"><span class="sp-card-title">Ícone do PWA</span></div>
                        <div class="sp-card-body">
                            <p class="small text-muted">Tamanho: 512×512px. Exibido na tela inicial e splash.</p>
                            <?php if (!empty($settings['pwa_icon'])): ?>
                                <img src="<?= sp_safe_url(base_url(esc($settings['pwa_icon']))) ?>" alt="PWA Icon" class="img-thumbnail mb-2" style="max-width:80px">
                            <?php endif; ?>
                            <div>
                                <input type="file" class="form-control form-control-sm mb-2" id="upload_pwa_icon" accept="image/*">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="uploadPwaImage('pwa_icon', 'upload_pwa_icon')">
                                    <i class="bi bi-upload me-1"></i>Enviar ícone
                                </button>
                                <div id="status_pwa_icon" class="mt-1 small"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Splash Screen -->
                <div class="col-md-4">
                    <div class="sp-card h-100">
                        <div class="sp-card-header"><span class="sp-card-title">Splash Screen</span></div>
                        <div class="sp-card-body">
                            <p class="small text-muted">Tamanho: 2048×2732px. Exibida ao abrir o app em iOS.</p>
                            <?php if (!empty($settings['pwa_splash'])): ?>
                                <img src="<?= sp_safe_url(base_url(esc($settings['pwa_splash']))) ?>" alt="PWA Splash" class="img-thumbnail mb-2" style="max-width:80px">
                            <?php endif; ?>
                            <div>
                                <input type="file" class="form-control form-control-sm mb-2" id="upload_pwa_splash" accept="image/*">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="uploadPwaImage('pwa_splash', 'upload_pwa_splash')">
                                    <i class="bi bi-upload me-1"></i>Enviar splash
                                </button>
                                <div id="status_pwa_splash" class="mt-1 small"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Shortcut Icon -->
                <div class="col-md-4">
                    <div class="sp-card h-100">
                        <div class="sp-card-header"><span class="sp-card-title">Ícone de Atalho</span></div>
                        <div class="sp-card-body">
                            <p class="small text-muted">Tamanho: 96×96px. Usado nos atalhos do manifesto PWA.</p>
                            <?php if (!empty($settings['pwa_shortcut_icon'])): ?>
                                <img src="<?= sp_safe_url(base_url(esc($settings['pwa_shortcut_icon']))) ?>" alt="Shortcut Icon" class="img-thumbnail mb-2" style="max-width:80px">
                            <?php endif; ?>
                            <div>
                                <input type="file" class="form-control form-control-sm mb-2" id="upload_pwa_shortcut_icon" accept="image/*">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="uploadPwaImage('pwa_shortcut_icon', 'upload_pwa_shortcut_icon')">
                                    <i class="bi bi-upload me-1"></i>Enviar ícone atalho
                                </button>
                                <div id="status_pwa_shortcut_icon" class="mt-1 small"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
function syncPwaColor(name, suffix, val) {
    var picker = document.getElementById('picker_pwa_' + suffix);
    if (picker && /^#[0-9A-Fa-f]{6}$/.test(val)) picker.value = val;
}
['pwa_theme', 'pwa_bg'].forEach(function(suffix) {
    var picker = document.getElementById('picker_' + suffix);
    if (!picker) return;
    picker.addEventListener('input', function() {
        var text = document.getElementById('text_' + suffix);
        if (text) text.value = picker.value;
    });
});

function uploadPwaImage(type, inputId) {
    var input = document.getElementById(inputId);
    var statusEl = document.getElementById('status_' + type);
    if (!input.files || !input.files[0]) {
        statusEl.innerHTML = '<span class="text-danger">Selecione um arquivo primeiro.</span>';
        return;
    }
    var formData = new FormData();
    formData.append('type', type);
    formData.append('image', input.files[0]);
    statusEl.innerHTML = '<span class="text-muted">Enviando...</span>';
    spFetch('<?= sp_safe_url(sp_route_url('admin.settings.pwa.upload-image')) ?>', {
        method: 'POST',
        body: formData,
    }).then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.success) {
            statusEl.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' + (d.message || 'Enviado!') + '</span>';
        } else {
            statusEl.innerHTML = '<span class="text-danger">' + (d.message || 'Erro ao enviar.') + '</span>';
        }
      })
      .catch(function() {
        statusEl.innerHTML = '<span class="text-danger">Erro de conexão.</span>';
      });
}
</script>
<?= $this->endSection() ?>
