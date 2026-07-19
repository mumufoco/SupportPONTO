<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Configurações Operacionais<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= sp_safe_url(asset_url('css/pages/settings.css')) ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Configurações Operacionais',
        'subtitle' => 'Jornada de trabalho, notificações, integrações e backup.',
        'icon'     => 'bi bi-sliders',
        'actions'  => [
            ['label' => 'Aparência',    'icon' => 'bi bi-palette-fill',    'url' => route_to('admin.settings.appearance')],
            ['label' => 'Sistema',      'icon' => 'bi bi-cpu-fill',        'url' => route_to('admin.settings.system')],
            ['label' => 'Controles',    'icon' => 'bi bi-shield-lock-fill','url' => route_to('admin.settings.controls')],
        ],
    ]) ?>

    <?php if (!($isAdmin ?? false)): ?>
        <div class="sp-alert sp-alert-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>Você não tem permissão para acessar as configurações do sistema.</span>
        </div>
    <?php else: ?>

    <div id="spSettingsToast" style="position:fixed;top:1rem;right:1rem;z-index:9999;min-width:300px"></div>

    <div class="row g-3">

        <!-- NAV LATERAL -->
        <div class="col-md-3 col-lg-2">
            <div class="sp-card mb-0 position-sticky" style="top:1rem">
                <div class="sp-card-body p-2">
                    <p class="small text-uppercase text-muted fw-bold px-2 mb-2">Operacional</p>
                    <div class="nav flex-column nav-pills" id="opNav">
                        <button class="nav-link active text-start py-2 px-2" data-bs-toggle="pill" data-bs-target="#tab-workday">
                            <i class="bi bi-clock-fill me-2"></i>Jornada
                        </button>
                        <button class="nav-link text-start py-2 px-2" data-bs-toggle="pill" data-bs-target="#tab-notifications">
                            <i class="bi bi-envelope-fill me-2"></i>Notificações
                        </button>
                        <button class="nav-link text-start py-2 px-2" data-bs-toggle="pill" data-bs-target="#tab-apis">
                            <i class="bi bi-plug-fill me-2"></i>Integrações
                        </button>
                        <button class="nav-link text-start py-2 px-2" data-bs-toggle="pill" data-bs-target="#tab-backup">
                            <i class="bi bi-database-fill me-2"></i>Backup
                        </button>
                        <button class="nav-link text-start py-2 px-2" data-bs-toggle="pill" data-bs-target="#tab-holidays" id="btn-tab-holidays">
                            <i class="bi bi-calendar-x-fill me-2"></i>Feriados
                        </button>
                    </div>
                    <hr class="my-2">
                    <p class="small text-uppercase text-muted fw-bold px-2 mb-2">Cadastros</p>
                    <a href="<?= sp_route_url('settings.departments') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-diagram-3 me-2"></i>Departamentos</a>
                    <a href="<?= sp_route_url('settings.positions') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-briefcase-fill me-2"></i>Cargos</a>
                    <a href="<?= sp_route_url('settings.roles') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-person-badge-fill me-2"></i>Funções</a>
                    <a href="<?= sp_route_url('settings.work-units') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-building me-2"></i>Unidades de Trabalho</a>
                    <a href="<?= sp_route_url('settings.vacations') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-umbrella-fill me-2"></i>Férias</a>
                    <a href="<?= sp_route_url('settings.work-shifts') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-calendar2-week-fill me-2"></i>Turnos</a>
                    <a href="<?= site_url('geofences') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-geo-alt-fill me-2"></i>Cercas Geográficas</a>
                    <hr class="my-2">
                    <p class="small text-uppercase text-muted fw-bold px-2 mb-2">Outras seções</p>
                    <a href="<?= route_to('admin.settings.appearance') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-palette-fill me-2"></i>Aparência</a>
                    <a href="<?= route_to('admin.settings.personalization') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-brush-fill me-2"></i>Personalização</a>
                    <a href="<?= route_to('admin.settings.information') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-info-circle-fill me-2"></i>Informações</a>
                    <a href="<?= route_to('admin.settings.system') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-cpu-fill me-2"></i>Sistema</a>
                    <a href="<?= route_to('admin.settings.controls') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-shield-lock-fill me-2"></i>Controles</a>
                    <a href="<?= route_to('admin.settings.two-factor') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-shield-check me-2"></i>Dois Fatores</a>
                    <a href="<?= route_to('admin.settings.certificate') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-award-fill me-2"></i>Certificado ICP</a>
                    <a href="<?= route_to('admin.settings.email') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-envelope-at-fill me-2"></i>Email</a>
                    <a href="<?= route_to('admin.settings.email-templates') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-envelope-paper-fill me-2"></i>Templates de Email</a>
                    <a href="<?= route_to('admin.settings.integrations') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-plug-fill me-2"></i>Integrações</a>
                    <a href="<?= route_to('admin.settings.backup') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-database-fill me-2"></i>Backup</a>
                    <a href="<?= route_to('admin.settings.pwa') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-phone-fill me-2"></i>PWA</a>
                    <hr class="my-2">
                    <p class="small text-uppercase text-muted fw-bold px-2 mb-2">LGPD</p>
                    <a href="<?= site_url('settings/consent-terms') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-file-earmark-text-fill me-2"></i>Templates de Termos</a>
                    <a href="<?= site_url('biometric/consent-terms/list') ?>" class="nav-link text-start py-2 px-2 text-secondary small"><i class="bi bi-list-check me-2"></i>Aceites Registrados</a>
                </div>
            </div>
        </div>

        <!-- CONTEÚDO -->
        <div class="col-md-9 col-lg-10">
            <div class="tab-content">

                <!-- ABA: JORNADA -->
                <div class="tab-pane fade show active" id="tab-workday">
                    <div class="sp-card">
                        <div class="sp-card-header">
                            <span class="sp-card-title"><i class="bi bi-clock-fill"></i> Jornada de Trabalho</span>
                        </div>
                        <div class="sp-card-body">
                            <p class="text-muted small mb-3">Defina o horário padrão, tolerâncias e dias úteis da empresa. Cada colaborador pode ter jornada individualizada em <a href="<?= sp_safe_url(sp_shifts_index_url()) ?>">Turnos</a>.</p>
                            <form id="form-workday">
                                <?= csrf_field() ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Entrada padrão</label>
                                        <input type="time" class="form-control" name="workday_start"
                                               value="<?= esc($settings['workday']['workday_start'] ?? '08:00') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Saída padrão</label>
                                        <input type="time" class="form-control" name="workday_end"
                                               value="<?= esc($settings['workday']['workday_end'] ?? '17:00') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Intervalo obrigatório (h)</label>
                                        <input type="number" class="form-control" name="mandatory_break_hours"
                                               min="0" max="4" step="0.5"
                                               value="<?= esc($settings['workday']['mandatory_break_hours'] ?? '1') ?>">
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Tolerância atraso (min)</label>
                                        <input type="number" class="form-control" name="late_tolerance_minutes"
                                               min="0" max="60"
                                               value="<?= esc($settings['workday']['late_tolerance_minutes'] ?? '10') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Tolerância saída antecipada (min)</label>
                                        <input type="number" class="form-control" name="early_tolerance_minutes"
                                               min="0" max="60"
                                               value="<?= esc($settings['workday']['early_tolerance_minutes'] ?? '10') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Tolerância hora extra (min)</label>
                                        <input type="number" class="form-control" name="overtime_tolerance_minutes"
                                               min="0" max="60"
                                               value="<?= esc($settings['workday']['overtime_tolerance_minutes'] ?? '10') ?>">
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Descanso mínimo entre jornadas (h)</label>
                                        <input type="number" class="form-control" name="min_interjornada_hours"
                                               min="0" max="24" step="0.5"
                                               value="<?= esc($settings['workday']['min_interjornada_hours'] ?? '11') ?>">
                                        <div class="form-text">Art. 66 CLT — mínimo legal: 11h.</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Limite diário de hora extra (h)</label>
                                        <input type="number" class="form-control" name="max_daily_overtime_hours"
                                               min="0" max="10" step="0.5"
                                               value="<?= esc($settings['workday']['max_daily_overtime_hours'] ?? '2') ?>">
                                        <div class="form-text">Art. 58/59 CLT — limite padrão: 2h sem acordo de compensação.</div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold d-block">Dias úteis</label>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <?php
                                        $bd = $settings['workday']['business_days'] ?? ['1','2','3','4','5'];
                                        if (is_string($bd)) $bd = json_decode($bd, true) ?? ['1','2','3','4','5'];
                                        $days = ['1'=>'Seg','2'=>'Ter','3'=>'Qua','4'=>'Qui','5'=>'Sex','6'=>'Sáb','7'=>'Dom'];
                                        foreach ($days as $d => $label):
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                   name="business_days[]" value="<?= $d ?>" id="bd<?= $d ?>"
                                                   <?= in_array((string)$d, array_map('strval', $bd)) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="bd<?= $d ?>"><?= $label ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-floppy-fill me-2"></i>Salvar Jornada
                                    </button>
                                    <a href="<?= sp_safe_url(sp_shifts_index_url()) ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-calendar2-week-fill me-2"></i>Gerenciar Turnos
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ABA: NOTIFICAÇÕES -->
                <div class="tab-pane fade" id="tab-notifications">
                    <div class="sp-card">
                        <div class="sp-card-header">
                            <span class="sp-card-title"><i class="bi bi-envelope-fill"></i> Configurações SMTP</span>
                        </div>
                        <div class="sp-card-body">
                            <form id="form-notifications">
                                <?= csrf_field() ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Servidor SMTP</label>
                                        <input type="text" class="form-control" name="smtp_host"
                                               value="<?= esc($settings['notifications']['smtp_host'] ?? '') ?>"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Porta</label>
                                        <input type="number" class="form-control" name="smtp_port"
                                               value="<?= esc($settings['notifications']['smtp_port'] ?? '587') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Criptografia</label>
                                        <select class="form-select" name="smtp_secure">
                                            <option value="tls" <?= ($settings['notifications']['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                            <option value="ssl" <?= ($settings['notifications']['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                            <option value=""    <?= empty($settings['notifications']['smtp_secure'] ?? '') ? 'selected' : '' ?>>Nenhuma</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Usuário SMTP</label>
                                        <input type="text" class="form-control" name="smtp_user"
                                               value="<?= esc($settings['notifications']['smtp_user'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Senha SMTP</label>
                                        <input type="password" class="form-control" name="smtp_password"
                                               placeholder="<?= !empty($settings['notifications']['smtp_password']) ? '••••••••' : 'Senha' ?>">
                                        <div class="form-text">Deixe em branco para não alterar</div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">E-mail remetente</label>
                                        <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                               value="<?= esc($settings['notifications']['smtp_from_email'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Nome do remetente</label>
                                        <input type="text" class="form-control" name="smtp_from_name"
                                               value="<?= esc($settings['notifications']['smtp_from_name'] ?? 'SupportPONTO') ?>">
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-floppy-fill me-2"></i>Salvar SMTP
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btn-test-smtp">
                                        <i class="bi bi-send-fill me-2"></i>Testar Conexão
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ABA: INTEGRAÇÕES -->
                <div class="tab-pane fade" id="tab-apis">
                    <div class="sp-card">
                        <div class="sp-card-header">
                            <span class="sp-card-title"><i class="bi bi-plug-fill"></i> Chaves de Integração</span>
                        </div>
                        <div class="sp-card-body">
                            <div class="sp-callout-warning mb-3">
                                <strong><i class="bi bi-lock-fill me-2"></i>Segurança</strong>
                                <div>Chaves ocultas por segurança. Preencha apenas para atualizar.</div>
                            </div>
                            <form id="form-apis">
                                <?= csrf_field() ?>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-camera-video me-1"></i>API Reconhecimento Facial
                                        </label>
                                        <input type="password" class="form-control" name="api_facial_key"
                                               placeholder="<?= !empty($settings['apis']['api_facial_key']) ? '••••• configurada' : 'Não configurada' ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-fingerprint me-1"></i>API Biometria Digital
                                        </label>
                                        <input type="password" class="form-control" name="api_biometry_key"
                                               placeholder="<?= !empty($settings['apis']['api_biometry_key']) ? '••••• configurada' : 'Não configurada' ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-geo-alt-fill me-1"></i>Google Maps API Key
                                        </label>
                                        <input type="password" class="form-control" name="api_geolocation_key"
                                               placeholder="<?= !empty($settings['apis']['api_geolocation_key']) ? '••••• configurada' : 'Não configurada' ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">
                                            <i class="bi bi-bell-fill me-1"></i>FCM Server Key (Push)
                                        </label>
                                        <input type="password" class="form-control" name="api_fcm_key"
                                               placeholder="<?= !empty($settings['apis']['api_fcm_key']) ? '••••• configurada' : 'Não configurada' ?>">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-floppy-fill me-2"></i>Salvar Chaves
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- ABA: BACKUP -->
                <div class="tab-pane fade" id="tab-backup">
                    <div class="sp-card">
                        <div class="sp-card-header">
                            <span class="sp-card-title"><i class="bi bi-database-fill"></i> Backup do Sistema</span>
                        </div>
                        <div class="sp-card-body">
                            <form id="form-backup">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="backup_enabled_chk"
                                               value="1" id="backupEnabled"
                                               <?= ($settings['backup']['backup_enabled'] ?? '0') == '1' ? 'checked' : '' ?>>
                                        <input type="hidden" name="backup_enabled" id="backupEnabledHidden"
                                               value="<?= ($settings['backup']['backup_enabled'] ?? '0') == '1' ? '1' : '0' ?>">
                                        <label class="form-check-label fw-semibold" for="backupEnabled">
                                            Ativar Backup Automático
                                        </label>
                                    </div>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Frequência</label>
                                        <select class="form-select" name="backup_frequency">
                                            <option value="daily"   <?= ($settings['backup']['backup_frequency'] ?? 'daily') === 'daily'  ? 'selected' : '' ?>>Diário</option>
                                            <option value="weekly"  <?= ($settings['backup']['backup_frequency'] ?? '') === 'weekly'  ? 'selected' : '' ?>>Semanal</option>
                                            <option value="monthly" <?= ($settings['backup']['backup_frequency'] ?? '') === 'monthly' ? 'selected' : '' ?>>Mensal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Retenção (dias)</label>
                                        <input type="number" class="form-control" name="backup_retention_days"
                                               value="<?= esc($settings['backup']['backup_retention_days'] ?? '30') ?>"
                                               min="1" max="1825">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Armazenamento</label>
                                        <select class="form-select" name="backup_storage">
                                            <option value="local" <?= ($settings['backup']['backup_storage'] ?? 'local') === 'local' ? 'selected' : '' ?>>Local</option>
                                            <option value="s3"    <?= ($settings['backup']['backup_storage'] ?? '') === 's3'   ? 'selected' : '' ?>>Amazon S3</option>
                                            <option value="gcs"   <?= ($settings['backup']['backup_storage'] ?? '') === 'gcs'  ? 'selected' : '' ?>>Google Cloud</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-floppy-fill me-2"></i>Salvar Backup
                                    </button>
                                    <a href="<?= sp_safe_url(sp_route_url('settings.backup.download')) ?>"
                                       class="btn btn-outline-secondary">
                                        <i class="bi bi-download me-2"></i>Baixar Backup Agora
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="tab-holidays">
                    <div class="sp-card">
                        <div class="sp-card-header d-flex justify-content-between align-items-center">
                            <span class="sp-card-title"><i class="bi bi-calendar-x-fill me-2"></i>Feriados</span>
                            <button class="btn btn-primary btn-sm" onclick="openHolidayModal()"><i class="bi bi-plus-lg me-1"></i>Novo Feriado</button>
                        </div>
                        <div class="sp-card-body p-0"><div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light"><tr>
                                    <th>Data</th><th>Nome</th><th>Tipo</th>
                                    <th class="text-center">Recorrente</th>
                                    <th class="text-center">Bloqueia Ponto</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end">Acoes</th>
                                </tr></thead>
                                <tbody id="holidaysBody">
                                    <tr><td colspan="7" class="text-center text-muted py-3">Carregando...</td></tr>
                                </tbody>
                            </table></div></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce_attr() ?>>
(function () {
    'use strict';

    SupportPontoValidation.bindEmailFormatField(document.getElementById('smtp_from_email'));

    const EP = {
        saveWorkday:       '<?= esc(route_to('settings.save-workday')) ?>',
        saveNotifications: '<?= esc(route_to('settings.save-notifications')) ?>',
        saveApis:          '<?= esc(route_to('settings.save-apis')) ?>',
        saveBackup:        '<?= esc(route_to('settings.save-backup')) ?>',
        smtpTest:          '<?= esc(route_to('settings.smtp.test')) ?>',
        holidays:          '<?= esc(sp_route_url('settings.holidays.json')) ?>',
        holidaysStore:     '<?= esc(sp_route_url('settings.holidays.store')) ?>',
    };

    // Toast simples
    function toast(msg, ok = true) {
        const c = document.getElementById('spSettingsToast');
        if (!c) return;
        const el = document.createElement('div');
        const bg = ok ? 'var(--sp-success)' : 'var(--sp-danger)';
        el.style.cssText = `background:${bg};color:#fff;padding:.875rem 1.125rem;border-radius:var(--sp-radius-md);margin-bottom:.5rem;font-size:.875rem;display:flex;gap:.5rem;align-items:center;box-shadow:var(--sp-shadow-md);`;
        el.innerHTML = `<i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i><span>${msg}</span>`;
        c.appendChild(el);
        setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 350); }, 4500);
    }

    async function doSave(formId, url, successMsg) {
        const form = document.getElementById(formId);
        if (!form) return;
        const btn = form.querySelector('button[type=submit]');
        const orig = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Salvando...'; }
        try {
            const fd = new FormData(form);
            const resp = await spFetch(url, { method: 'POST', body: fd });
            const j = await resp.json();
            toast(j.success ? (j.message || successMsg) : (j.message || 'Erro ao salvar'), j.success);
        } catch (e) {
            toast('Erro de comunicação com o servidor', false);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = orig; }
        }
    }

    // Handlers
    document.getElementById('form-workday')?.addEventListener('submit', e => {
        e.preventDefault(); doSave('form-workday', EP.saveWorkday, 'Jornada salva!');
    });

    document.getElementById('form-notifications')?.addEventListener('submit', e => {
        e.preventDefault(); doSave('form-notifications', EP.saveNotifications, 'Notificações salvas!');
    });

    document.getElementById('btn-test-smtp')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-test-smtp');
        const orig = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Testando...';
        try {
            const fd = new FormData(document.getElementById('form-notifications'));
            const r = await spFetch(EP.smtpTest, { method: 'POST', body: fd });
            const j = await r.json();
            toast(j.message || (j.success ? 'SMTP OK!' : 'Falha'), j.success);
        } catch { toast('Erro ao testar SMTP', false); }
        finally { btn.disabled = false; btn.innerHTML = orig; }
    });

    document.getElementById('form-apis')?.addEventListener('submit', e => {
        e.preventDefault(); doSave('form-apis', EP.saveApis, 'Chaves salvas!');
    });

    // Backup: sincronizar checkbox com hidden input
    document.getElementById('backupEnabled')?.addEventListener('change', function () {
        document.getElementById('backupEnabledHidden').value = this.checked ? '1' : '0';
    });

    document.getElementById('form-backup')?.addEventListener('submit', e => {
        e.preventDefault(); doSave('form-backup', EP.saveBackup, 'Backup configurado!');
    });


    // ---- Holidays ----
    function escH(s) { const d = document.createElement("div"); d.appendChild(document.createTextNode(String(s || ""))); return d.innerHTML; }

    function loadHolidays() {
        fetch(EP.holidays).then(function(r) { return r.json(); }).then(function(r) {
            const tbody = document.getElementById("holidaysBody");
            if (!tbody) return;
            const types = {national:"Nacional",state:"Estadual",municipal:"Municipal",company:"Empresa",non_working:"Dia Nao Trabalhado"};
            let html = "";
            (r.data || []).forEach(function(h) {
                html += "<tr>"
                    + "<td>" + escH(h.date) + "</td>"
                    + "<td>" + escH(h.name) + "</td>"
                    + "<td>" + escH(types[h.type] || h.type) + "</td>"
                    + "<td class=\"text-center\">" + (h.recurring ? "Sim" : "N\u00e3o") + "</td>"
                    + "<td class=\"text-center\">" + (h.blocks_punch ? "Sim" : "N\u00e3o") + "</td>"
                    + "<td class=\"text-center\"><span class=\"badge " + (h.active ? "bg-success" : "bg-secondary") + "\">" + (h.active ? "Ativo" : "Inativo") + "</span></td>"
                    + "<td class=\"text-end\">"
                        + "<button class=\"btn btn-warning btn-sm me-1\" onclick=\"editHoliday(" + parseInt(h.id) + ")\" title=\"Editar\"><i class=\"bi bi-pencil-fill\"></i></button>"
                        + "<button class=\"btn btn-secondary btn-sm me-1\" onclick=\"toggleHoliday(" + parseInt(h.id) + ")\" title=\"Ativar/Desativar\"><i class=\"bi bi-toggle-on\"></i></button>"
                        + "<button class=\"btn btn-danger btn-sm\" onclick=\"confirmDeleteHoliday(" + parseInt(h.id) + ", " + JSON.stringify(h.name) + ")\" title=\"Excluir\"><i class=\"bi bi-trash-fill\"></i></button>"
                    + "</td>"
                + "</tr>";
            });
            tbody.innerHTML = html || "<tr><td colspan=\"7\" class=\"text-center text-muted py-3\">Nenhum feriado cadastrado</td></tr>";
        });
    }

    function openHolidayModal() {
        document.getElementById("holidayForm").reset();
        document.getElementById("holiday_id").value = "";
        new bootstrap.Modal(document.getElementById("holidayModal")).show();
    }

    function editHoliday(id) {
        fetch(EP.holidays).then(function(r) { return r.json(); }).then(function(r) {
            const h = (r.data || []).find(function(x) { return x.id == id; });
            if (h) {
                document.getElementById("holiday_id").value = h.id;
                document.getElementById("holiday_name").value = h.name;
                document.getElementById("holiday_date").value = (h.date || "").substring(0, 10);
                document.getElementById("holiday_type").value = h.type;
                document.getElementById("holiday_description").value = h.description || "";
                document.getElementById("holiday_recurring").checked = h.recurring == 1;
                document.getElementById("holiday_blocks_punch").checked = h.blocks_punch == 1;
                new bootstrap.Modal(document.getElementById("holidayModal")).show();
            }
        });
    }

    function saveHoliday() {
        const id = document.getElementById("holiday_id").value;
        const url = id ? EP.holidays.replace("/json", "/" + id + "/update") : EP.holidaysStore;
        const fd = new FormData(document.getElementById("holidayForm"));
        spFetch(url, { method: "POST", body: fd }).then(function(r) { return r.json(); }).then(function(r) {
            if (r.success) {
                bootstrap.Modal.getInstance(document.getElementById("holidayModal")).hide();
                loadHolidays();
                toast(r.message || "Feriado salvo com sucesso!", true);
            } else {
                toast(r.message || "Erro ao salvar feriado", false);
            }
        });
    }

    function toggleHoliday(id) {
        const url = EP.holidays.replace("/json", "/" + id + "/toggle");
        spFetch(url, { method: "POST" }).then(function() { loadHolidays(); toast("Status do feriado alterado", true); });
    }

    function confirmDeleteHoliday(id, name) {
        if (!confirm("Excluir feriado \"" + name + "\"? Esta a\u00e7\u00e3o n\u00e3o pode ser desfeita.")) return;
        const url = EP.holidays.replace("/json", "/" + id + "/delete");
        spFetch(url, { method: "POST" }).then(function() { loadHolidays(); toast("Feriado excu\u00eddo com sucesso!", true); });
    }

    document.addEventListener("DOMContentLoaded", function() {
        var btn = document.getElementById("btn-tab-holidays");
        if (btn) {
            btn.addEventListener("shown.bs.tab", loadHolidays);
            if (window.location.hash === "#tab-holidays") { btn.click(); }
        }
    });

})();
</script>

<!-- Modal Feriado -->
<div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Feriado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form id="holidayForm"><?= csrf_field() ?><input type="hidden" id="holiday_id" name="holiday_id"><div class="mb-3"><label class="form-label">Nome *</label><input type="text" class="form-control" id="holiday_name" name="name" required maxlength="255"></div><div class="row g-3 mb-3"><div class="col-6"><label class="form-label">Data *</label><input type="date" class="form-control" id="holiday_date" name="date" required></div><div class="col-6"><label class="form-label">Tipo *</label><select class="form-select" id="holiday_type" name="type" required><option value="">Selecione...</option><option value="national">Nacional</option><option value="state">Estadual</option><option value="municipal">Municipal</option><option value="company">Empresa</option><option value="non_working">Dia Nao Trabalhado</option></select></div></div><div class="mb-3"><label class="form-label">Observacao</label><input type="text" class="form-control" id="holiday_description" name="description" maxlength="500"></div><div class="row g-3"><div class="col-6"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="holiday_recurring" name="recurring" value="1"><label class="form-check-label" for="holiday_recurring"><strong>Recorrente</strong></label></div></div><div class="col-6"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="holiday_blocks_punch" name="blocks_punch" value="1" checked><label class="form-check-label" for="holiday_blocks_punch"><strong>Bloquear ponto</strong></label></div></div></div></form></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-primary" onclick="saveHoliday()">Salvar</button></div></div></div></div>
<?= $this->endSection() ?>
