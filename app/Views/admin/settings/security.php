<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Segurança<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Configurações de Segurança',
        'subtitle' => 'Política de senhas, controle de acesso, auditoria e privacidade (LGPD).',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [
                                ],
    ]) ?>


    <form action="<?= sp_safe_url(sp_route_url('admin.settings.security.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <!-- 1. Política de Senhas -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-key-fill"></i> Política de Senhas</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="password_min_length" class="form-label fw-semibold">
                            Tamanho mínimo <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control" id="password_min_length"
                               name="password_min_length"
                               value="<?= esc($settings['password_min_length'] ?? 8) ?>"
                               min="6" max="128" required>
                        <div class="form-text">Recomendado: 8+ caracteres</div>
                    </div>
                    <div class="col-md-3">
                        <label for="password_expiry_days" class="form-label fw-semibold">
                            Expiração (dias)
                        </label>
                        <input type="number" class="form-control" id="password_expiry_days"
                               name="password_expiry_days"
                               value="<?= esc($settings['password_expiry_days'] ?? 0) ?>"
                               min="0" max="365">
                        <div class="form-text">0 = nunca expira</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Requisitos obrigatórios</label>
                        <div class="row g-2 mt-1">
                            <?php
                            $reqs = [
                                'password_require_uppercase' => 'Maiúsculas (A-Z)',
                                'password_require_lowercase' => 'Minúsculas (a-z)',
                                'password_require_numbers'   => 'Números (0-9)',
                                'password_require_special'   => 'Especiais (!@#$...)',
                            ];
                            foreach ($reqs as $name => $label):
                            ?>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="<?= $name ?>" value="1" id="<?= $name ?>"
                                           <?= ($settings[$name] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="<?= $name ?>"><?= $label ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-3" style="border-top:1px solid var(--sp-border)">
                    <label class="form-label fw-semibold small d-block">Testar política de senha</label>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="password" class="form-control form-control-sm" id="test_password"
                               placeholder="Digite uma senha..." style="max-width:280px">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="testPassword()">
                            <i class="bi bi-shield-check me-1"></i>Testar
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="viewAuditLogs()">
                            <i class="bi bi-list-ul me-1"></i>Logs de auditoria
                        </button>
                    </div>
                    <div id="passwordTestResult" class="mt-2"></div>
                </div>
            </div>
        </div>

        <!-- 2. Controle de Acesso -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-lock"></i> Controle de Acesso e Sessão</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   name="force_https" value="1" id="force_https"
                                   <?= ($settings['force_https'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="force_https">Forçar HTTPS</label>
                        </div>
                        <div class="form-text">Redireciona HTTP → HTTPS automaticamente</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   name="regenerate_session_id" value="1" id="regenerate_session_id"
                                   <?= ($settings['regenerate_session_id'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="regenerate_session_id">Regenerar ID de sessão</label>
                        </div>
                        <div class="form-text">Previne fixação de sessão após login</div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   name="enable_xss_filter" value="1" id="enable_xss_filter"
                                   <?= ($settings['enable_xss_filter'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enable_xss_filter">Filtro XSS</label>
                        </div>
                        <div class="form-text">Bloqueia injeção de scripts maliciosos</div>
                    </div>
                </div>
                <div class="sp-callout-neutral mt-3">
                    <i class="bi bi-shield-check me-2"></i>
                    <strong>Proteção CSRF sempre ativa</strong> — não pode ser desabilitada.
                </div>
            </div>
        </div>

        <!-- 3. Logs de Auditoria -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-journal-text"></i> Logs de Auditoria</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   name="enable_audit_log" value="1" id="enable_audit_log"
                                   <?= ($settings['enable_audit_log'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enable_audit_log">Habilitar auditoria</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="audit_log_retention_days" class="form-label fw-semibold">Retenção (dias)</label>
                        <input type="number" class="form-control" id="audit_log_retention_days"
                               name="audit_log_retention_days"
                               value="<?= esc($settings['audit_log_retention_days'] ?? 90) ?>"
                               min="1" max="3650">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Registrar eventos</label>
                        <div class="row g-2 mt-1">
                            <?php
                            $events = [
                                'log_logins'           => 'Logins e logouts',
                                'log_data_changes'     => 'Alterações de dados',
                                'log_deletions'        => 'Exclusões de registros',
                                'log_settings_changes' => 'Alterações de configurações',
                            ];
                            foreach ($events as $name => $label):
                            ?>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           name="<?= $name ?>" value="1" id="<?= $name ?>"
                                           <?= ($settings[$name] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label small" for="<?= $name ?>"><?= $label ?></label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div id="auditLogsContainer" class="mt-3" style="display:none"></div>
            </div>
        </div>

        <!-- 4. Privacidade LGPD -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-lock"></i> Privacidade de Dados (LGPD)</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   name="enable_data_anonymization" value="1" id="enable_data_anonymization"
                                   <?= ($settings['enable_data_anonymization'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="enable_data_anonymization">Anonimizar inativos</label>
                        </div>
                        <div class="form-text">Remove dados pessoais após prazo definido</div>
                    </div>
                    <div class="col-md-3">
                        <label for="anonymization_period_days" class="form-label fw-semibold">Prazo (dias)</label>
                        <input type="number" class="form-control" id="anonymization_period_days"
                               name="anonymization_period_days"
                               value="<?= esc($settings['anonymization_period_days'] ?? 365) ?>"
                               min="30" max="3650">
                    </div>
                    <div class="col-md-5">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox"
                                   name="allow_data_export" value="1" id="allow_data_export"
                                   <?= ($settings['allow_data_export'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="allow_data_export">Exportação de dados pelo colaborador</label>
                        </div>
                        <div class="form-text">Direito de portabilidade — Art. 18 LGPD</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="sp-card">
            <div class="sp-card-body d-flex gap-2 justify-content-between align-items-center flex-wrap">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="createBackup()">
                        <i class="bi bi-archive me-1"></i>Snapshot de segurança
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="resetToDefaults()">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restaurar padrão
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <a href="<?= sp_safe_url(route_to('admin.settings')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-floppy-fill me-2"></i>Salvar Segurança
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

<?= view('admin/settings/partials/security_scripts') ?>
<?= $this->endSection() ?>
