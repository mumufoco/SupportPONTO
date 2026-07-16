<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Integrações<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Integrações',
        'subtitle' => 'Provedores externos conectados ao sistema — credenciais e parâmetros de conexão.',
        'icon'     => 'bi bi-plug-fill',
        'actions'  => [
            ['label' => 'Configurações', 'icon' => 'bi bi-grid-fill', 'url' => sp_admin_settings_index_url()],
        ],
    ]) ?>

    <form action="<?= sp_safe_url(sp_route_url('admin.settings.integrations.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- DeepFace -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-bounding-box"></i> DeepFace — Reconhecimento Facial</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">URL base</label>
                        <input type="url" class="form-control" name="deepface_api_url"
                               value="<?= esc($settings['deepface_api_url'] ?? '') ?>"
                               placeholder="http://localhost:5000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Timeout (segundos)</label>
                        <input type="number" class="form-control" name="deepface_timeout"
                               value="<?= esc($settings['deepface_timeout'] ?? 15) ?>" min="1" max="120">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tentativas de retry</label>
                        <input type="number" class="form-control" name="deepface_retry_attempts"
                               value="<?= esc($settings['deepface_retry_attempts'] ?? 2) ?>" min="0" max="10">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Token de API</label>
                        <input type="password" class="form-control" name="deepface_api_key"
                               placeholder="<?= !empty($settings['deepface_api_key']) ? '••••• configurada' : 'Chave da API' ?>"
                               autocomplete="new-password">
                        <div class="form-text">Deixe em branco para manter a chave atual. Pode ser sobrescrito pela env DEEPFACE_API_KEY.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Token interno</label>
                        <input type="password" class="form-control" name="deepface_internal_token"
                               placeholder="<?= !empty($settings['deepface_internal_token']) ? '••••• configurado' : 'Token interno' ?>"
                               autocomplete="new-password">
                        <div class="form-text">Comunicação segura SupportPONTO ↔ microsserviço DeepFace.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Modelo</label>
                        <input type="text" class="form-control" name="deepface_model"
                               value="<?= esc($settings['deepface_model'] ?? 'VGG-Face') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Limiar do DeepFace</label>
                        <input type="text" class="form-control" name="deepface_threshold"
                               value="<?= esc($settings['deepface_threshold'] ?? '0.40') ?>">
                        <div class="form-text">Enviado como parâmetro ao microsserviço.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Limiar de aceitação (PONTO)</label>
                        <input type="text" class="form-control" name="facial_recognition_threshold"
                               value="<?= esc($settings['facial_recognition_threshold'] ?? '0.70') ?>">
                        <div class="form-text">Corte de similaridade aplicado ao registrar o ponto.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FCM -->
        <div class="sp-card mb-4">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-bell-fill"></i> Firebase Cloud Messaging — Notificações Push</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Server Key</label>
                        <input type="password" class="form-control" name="fcm_server_key"
                               placeholder="<?= !empty($settings['fcm_server_key']) ? '••••• configurada' : 'Server key do Firebase' ?>"
                               autocomplete="new-password">
                        <div class="form-text">Deixe em branco para manter a chave atual. Pode ser sobrescrito pela env FCM_SERVER_KEY.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="sp-card">
            <div class="sp-card-body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(sp_admin_settings_index_url()) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-2"></i>Salvar Integrações
                </button>
            </div>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
