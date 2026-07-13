<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Integrações<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid sp-module-stack">

    <?= view('components/page_header', [
        'title'    => 'Integrações',
        'subtitle' => 'APIs externas, biometria, geolocalização e backup em nuvem. Credenciais armazenadas de forma criptografada.',
        'icon'     => 'bi bi-plug-fill',
        'actions'  => [
            ['label' => 'Configurações', 'icon' => 'bi bi-grid-fill', 'url' => sp_admin_settings_index_url()],
        ],
    ]) ?>


    <form action="<?= sp_safe_url(sp_route_url('admin.settings.integrations.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- 1. Geolocalização -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-geo-alt-fill"></i>Geolocalização</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="geolocation_enabled" value="1" id="geolocation_enabled"
                                   <?= ($settings['geolocation_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="geolocation_enabled">Habilitada</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="geolocation_radius" class="form-label fw-semibold">Raio permitido (metros)</label>
                        <input type="number" class="form-control" id="geolocation_radius" name="geolocation_radius"
                               value="<?= esc($settings['geolocation_radius'] ?? '100') ?>" min="10" max="5000">
                        <div class="form-text">Distância máxima da geofence.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Modo restrito</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="geolocation_strict" value="1" id="geolocation_strict"
                                   <?= ($settings['geolocation_strict'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="geolocation_strict">Bloquear fora do raio</label>
                        </div>
                        <div class="form-text">Desativado: apenas alerta.</div>
                    </div>
                </div>

                <h6 class="fw-semibold mb-3 border-top pt-3"><i class="bi bi-map me-2"></i>Provedores de Mapa</h6>
                <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-3 small">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <span>Ative apenas um provedor. Credenciais sensíveis: deixe o campo de chave em branco para manter a chave atual.</span>
                </div>
                <div class="row g-3">
                    <!-- Google Maps -->
                    <div class="col-md-6">
                        <div class="sp-data-card h-100">
                            <div class="sp-data-card__header">
                                <h2 class="sp-data-card__title"><i class="bi bi-google me-1"></i>Google Maps</h2>
                            </div>
                            <div class="sp-data-card__body">
                                <p class="small text-muted mb-3">Mapas com alta precisão e cobertura detalhada no Brasil. Recomendado.</p>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="googlemaps_enabled" value="1" id="googlemaps_enabled"
                                           <?= ($settings['googlemaps_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="googlemaps_enabled">Habilitado</label>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Chave API</label>
                                    <input type="password" class="form-control form-control-sm" name="googlemaps_api_key"
                                           placeholder="<?= !empty($settings['googlemaps_api_key']) ? '••••• configurada (deixe vazio para manter)' : 'Chave da Google Maps Platform' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold">Endpoint</label>
                                    <input type="url" class="form-control form-control-sm" name="googlemaps_endpoint"
                                           value="<?= esc($settings['googlemaps_endpoint'] ?? 'https://maps.googleapis.com/maps/api') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Mapbox -->
                    <div class="col-md-6">
                        <div class="sp-data-card h-100">
                            <div class="sp-data-card__header">
                                <h2 class="sp-data-card__title"><i class="bi bi-map me-1"></i>Mapbox</h2>
                            </div>
                            <div class="sp-data-card__body">
                                <p class="small text-muted mb-3">Mapas customizáveis e APIs de direções com alta performance.</p>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="mapbox_enabled" value="1" id="mapbox_enabled"
                                           <?= ($settings['mapbox_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="mapbox_enabled">Habilitado</label>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Token público (pk.eyJ1…)</label>
                                    <input type="password" class="form-control form-control-sm" name="mapbox_api_key"
                                           placeholder="<?= !empty($settings['mapbox_api_key']) ? '••••• configurado (deixe vazio para manter)' : 'pk.eyJ1...' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold">Endpoint</label>
                                    <input type="url" class="form-control form-control-sm" name="mapbox_endpoint"
                                           value="<?= esc($settings['mapbox_endpoint'] ?? 'https://api.mapbox.com') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Biometria -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-fingerprint"></i>Biometria</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="alert alert-info d-flex gap-2 align-items-start py-2 mb-3 small">
                    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
                    <span>A biometria identifica o colaborador por impressão digital ou reconhecimento facial para registro de ponto seguro e rápido.</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Biometria geral</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="biometry_enabled" value="1" id="biometry_enabled"
                                   <?= ($settings['biometry_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="biometry_enabled">Habilitada</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Reconhecimento facial</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="facial_recognition_enabled" value="1" id="facial_recognition_enabled"
                                   <?= ($settings['facial_recognition_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="facial_recognition_enabled">Habilitado</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="facial_recognition_threshold" class="form-label fw-semibold">
                            Threshold facial
                            <span class="badge bg-secondary ms-1" style="font-size:.65rem;">0.0 – 1.0</span>
                        </label>
                        <input type="number" class="form-control" id="facial_recognition_threshold"
                               name="facial_recognition_threshold"
                               value="<?= esc($settings['facial_recognition_threshold'] ?? '0.6') ?>"
                               min="0" max="1" step="0.01">
                        <div class="form-text">Precisão mínima para aceitar o reconhecimento. Padrão: 0.6.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Digital (fingerprint)</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="fingerprint_enabled" value="1" id="fingerprint_enabled"
                                   <?= ($settings['fingerprint_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="fingerprint_enabled">Habilitada</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Chaves de API -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-key-fill"></i>Chaves de API</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="alert alert-warning d-flex gap-2 align-items-start py-2 mb-3 small">
                    <i class="bi bi-shield-lock-fill flex-shrink-0 mt-1"></i>
                    <span>Chaves armazenadas <strong>criptografadas</strong>. Deixe o campo em branco para manter a chave atual sem alteração.</span>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">API — Reconhecimento Facial</label>
                        <input type="password" class="form-control" name="api_facial_key"
                               placeholder="<?= !empty($settings['api_facial_key'] ?? '') ? '••••• configurada' : 'Chave atual mantida se vazio' ?>"
                               autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">API — Biometria Digital</label>
                        <input type="password" class="form-control" name="api_biometry_key"
                               placeholder="<?= !empty($settings['api_biometry_key'] ?? '') ? '••••• configurada' : 'Chave atual mantida se vazio' ?>"
                               autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">API — Firebase/FCM (notificações push)</label>
                        <input type="password" class="form-control" name="api_fcm_key"
                               placeholder="<?= !empty($settings['api_fcm_key'] ?? '') ? '••••• configurada' : 'Chave atual mantida se vazio' ?>"
                               autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">API — Geolocalização</label>
                        <input type="password" class="form-control" name="api_geolocation_key"
                               placeholder="<?= !empty($settings['api_geolocation_key'] ?? '') ? '••••• configurada' : 'Chave atual mantida se vazio' ?>"
                               autocomplete="new-password">
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Backup em Nuvem -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__header">
                <h2 class="sp-data-card__title"><i class="bi bi-cloud-arrow-up-fill"></i>Backup em Nuvem</h2>
            </div>
            <div class="sp-data-card__body">
                <div class="alert alert-warning d-flex gap-2 align-items-start py-2 mb-3 small">
                    <i class="bi bi-shield-lock-fill flex-shrink-0 mt-1"></i>
                    <span>Credenciais sensíveis (chaves e senhas) são preservadas automaticamente quando deixadas em branco — nunca são apagadas por acidente.</span>
                </div>
                <div class="row g-3">
                    <!-- Amazon S3 -->
                    <div class="col-md-4">
                        <div class="sp-data-card h-100">
                            <div class="sp-data-card__header">
                                <h2 class="sp-data-card__title"><i class="bi bi-cloud-fill text-warning me-1"></i>Amazon S3</h2>
                            </div>
                            <div class="sp-data-card__body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="backup_s3_enabled" value="1" id="backup_s3_enabled"
                                           <?= ($settings['backup_s3_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="backup_s3_enabled">Habilitado</label>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Access Key</label>
                                    <input type="password" class="form-control form-control-sm" name="backup_s3_key"
                                           placeholder="<?= !empty($settings['backup_s3_key'] ?? '') ? '••••• configurada' : 'Deixe vazio para manter' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Secret Key</label>
                                    <input type="password" class="form-control form-control-sm" name="backup_s3_secret"
                                           placeholder="<?= !empty($settings['backup_s3_secret'] ?? '') ? '••••• configurada' : 'Deixe vazio para manter' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Bucket</label>
                                    <input type="text" class="form-control form-control-sm" name="backup_s3_bucket"
                                           value="<?= esc($settings['backup_s3_bucket'] ?? '') ?>" placeholder="meu-bucket">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Região</label>
                                    <input type="text" class="form-control form-control-sm" name="backup_s3_region"
                                           value="<?= esc($settings['backup_s3_region'] ?? 'us-east-1') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Retenção (dias)</label>
                                    <input type="number" class="form-control form-control-sm" name="backup_s3_retention"
                                           value="<?= (int)($settings['backup_s3_retention'] ?? 30) ?>" min="1" max="365">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold">Frequência</label>
                                    <select class="form-select form-select-sm" name="backup_s3_frequency">
                                        <option value="daily"   <?= ($settings['backup_s3_frequency'] ?? 'daily') === 'daily'   ? 'selected' : '' ?>>Diário</option>
                                        <option value="weekly"  <?= ($settings['backup_s3_frequency'] ?? '') === 'weekly'  ? 'selected' : '' ?>>Semanal</option>
                                        <option value="monthly" <?= ($settings['backup_s3_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Google Cloud Storage -->
                    <div class="col-md-4">
                        <div class="sp-data-card h-100">
                            <div class="sp-data-card__header">
                                <h2 class="sp-data-card__title"><i class="bi bi-cloud-fill text-info me-1"></i>Google Cloud Storage</h2>
                            </div>
                            <div class="sp-data-card__body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="backup_gcs_enabled" value="1" id="backup_gcs_enabled"
                                           <?= ($settings['backup_gcs_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="backup_gcs_enabled">Habilitado</label>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Service Account Key (JSON)</label>
                                    <input type="password" class="form-control form-control-sm" name="backup_gcs_key"
                                           placeholder="<?= !empty($settings['backup_gcs_key'] ?? '') ? '••••• configurada' : 'Deixe vazio para manter' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Bucket</label>
                                    <input type="text" class="form-control form-control-sm" name="backup_gcs_bucket"
                                           value="<?= esc($settings['backup_gcs_bucket'] ?? '') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Pasta</label>
                                    <input type="text" class="form-control form-control-sm" name="backup_gcs_folder"
                                           value="<?= esc($settings['backup_gcs_folder'] ?? 'backups/') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Retenção (dias)</label>
                                    <input type="number" class="form-control form-control-sm" name="backup_gcs_retention"
                                           value="<?= (int)($settings['backup_gcs_retention'] ?? 30) ?>" min="1" max="365">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold">Frequência</label>
                                    <select class="form-select form-select-sm" name="backup_gcs_frequency">
                                        <option value="daily"   <?= ($settings['backup_gcs_frequency'] ?? 'daily') === 'daily'   ? 'selected' : '' ?>>Diário</option>
                                        <option value="weekly"  <?= ($settings['backup_gcs_frequency'] ?? '') === 'weekly'  ? 'selected' : '' ?>>Semanal</option>
                                        <option value="monthly" <?= ($settings['backup_gcs_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- OneDrive -->
                    <div class="col-md-4">
                        <div class="sp-data-card h-100">
                            <div class="sp-data-card__header">
                                <h2 class="sp-data-card__title"><i class="bi bi-cloud-fill text-primary me-1"></i>OneDrive / Azure</h2>
                            </div>
                            <div class="sp-data-card__body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="backup_onedrive_enabled" value="1" id="backup_onedrive_enabled"
                                           <?= ($settings['backup_onedrive_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="backup_onedrive_enabled">Habilitado</label>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Client ID</label>
                                    <input type="password" class="form-control form-control-sm" name="backup_onedrive_client_id"
                                           placeholder="<?= !empty($settings['backup_onedrive_client_id'] ?? '') ? '••••• configurado' : 'Deixe vazio para manter' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Client Secret</label>
                                    <input type="password" class="form-control form-control-sm" name="backup_onedrive_client_secret"
                                           placeholder="<?= !empty($settings['backup_onedrive_client_secret'] ?? '') ? '••••• configurado' : 'Deixe vazio para manter' ?>"
                                           autocomplete="new-password">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Pasta de destino</label>
                                    <input type="text" class="form-control form-control-sm" name="backup_onedrive_folder"
                                           value="<?= esc($settings['backup_onedrive_folder'] ?? 'SupportPONTO/Backups') ?>">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-semibold">Retenção (dias)</label>
                                    <input type="number" class="form-control form-control-sm" name="backup_onedrive_retention"
                                           value="<?= (int)($settings['backup_onedrive_retention'] ?? 30) ?>" min="1" max="365">
                                </div>
                                <div>
                                    <label class="form-label small fw-semibold">Frequência</label>
                                    <select class="form-select form-select-sm" name="backup_onedrive_frequency">
                                        <option value="daily"   <?= ($settings['backup_onedrive_frequency'] ?? 'daily') === 'daily'   ? 'selected' : '' ?>>Diário</option>
                                        <option value="weekly"  <?= ($settings['backup_onedrive_frequency'] ?? '') === 'weekly'  ? 'selected' : '' ?>>Semanal</option>
                                        <option value="monthly" <?= ($settings['backup_onedrive_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="sp-data-card mb-4">
            <div class="sp-data-card__body d-flex gap-2 justify-content-end">
                <a href="<?= sp_safe_url(sp_admin_settings_index_url()) ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-floppy-fill me-1"></i>Salvar integrações
                </button>
            </div>
        </div>

    </form>
</div>
<?= $this->endSection() ?>
