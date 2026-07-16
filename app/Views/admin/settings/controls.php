<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Controles<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">

    <?= view('components/page_header', [
        'title'    => 'Controles',
        'subtitle' => 'Segurança, autenticação, sessão, auditoria e privacidade (LGPD) em um só lugar.',
        'icon'     => 'bi bi-shield-lock-fill',
        'actions'  => [],
    ]) ?>


    <form action="<?= sp_safe_url(sp_route_url('admin.settings.controls.update')) ?>" method="POST">
        <?= csrf_field() ?>

        <h6 class="text-uppercase text-muted small fw-semibold mb-2">Segurança</h6>

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
        <div class="sp-card mb-4">
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

        <h6 class="text-uppercase text-muted small fw-semibold mb-2">Autenticação</h6>

        <!-- 5. Sessão e Permanência -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-clock-fill"></i> Sessão e Permanência</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="session_timeout" class="form-label fw-semibold">
                            Tempo de sessão (segundos) <span class="text-danger">*</span>
                        </label>
                        <input type="number" class="form-control" id="session_timeout" name="session_timeout"
                               value="<?= esc($settings['session_timeout'] ?? 3600) ?>"
                               min="300" max="86400" required>
                        <div class="form-text">Expira por inatividade. Padrão: 3600 = 1 hora.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold d-block">Lembrar-me</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox"
                                   id="enable_remember_me" name="enable_remember_me" value="1"
                                   <?= ($settings['enable_remember_me'] ?? '0') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="enable_remember_me">Permitir permanência prolongada</label>
                        </div>
                        <div class="form-text">Mantém o usuário logado entre sessões.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="remember_me_duration" class="form-label fw-semibold">Duração do lembrar-me (segundos)</label>
                        <input type="number" class="form-control" id="remember_me_duration" name="remember_me_duration"
                               value="<?= esc($settings['remember_me_duration'] ?? 2592000) ?>"
                               min="86400" max="31536000">
                        <div class="form-text">Padrão: 2592000 = 30 dias.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 6. Proteção de Login -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-shield-fill-exclamation"></i> Proteção de Login</span>
            </div>
            <div class="sp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="max_login_attempts" class="form-label fw-semibold">Máx. tentativas de login</label>
                        <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts"
                               value="<?= esc($settings['max_login_attempts'] ?? 5) ?>"
                               min="3" max="20">
                        <div class="form-text">Bloqueio após este número de tentativas consecutivas.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="lockout_duration" class="form-label fw-semibold">Duração do bloqueio (segundos)</label>
                        <input type="number" class="form-control" id="lockout_duration" name="lockout_duration"
                               value="<?= esc($settings['lockout_duration'] ?? 900) ?>"
                               min="60" max="86400">
                        <div class="form-text">Padrão: 900 = 15 minutos.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 7. Auto-Cadastro -->
        <div class="sp-card mb-3">
            <div class="sp-card-header">
                <span class="sp-card-title"><i class="bi bi-person-plus-fill"></i> Auto-Cadastro de Funcionários</span>
            </div>
            <div class="sp-card-body">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox"
                           id="self_registration_enabled" name="self_registration_enabled" value="1"
                           <?= !empty($settings['self_registration_enabled']) && ($settings['self_registration_enabled'] === '1' || $settings['self_registration_enabled'] === 1 || $settings['self_registration_enabled'] === true) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="self_registration_enabled">Permitir auto-cadastro</label>
                </div>
                <div class="form-text mb-2">
                    Quando ativado, o botão <strong>"Criar conta"</strong> aparece na tela de login. Cadastros ficam <strong>pendentes de aprovação</strong> pelo administrador.
                </div>
                <?php if (!empty($settings['self_registration_enabled']) && ($settings['self_registration_enabled'] === '1' || $settings['self_registration_enabled'] === true)): ?>
                <div class="alert alert-success py-2 mb-0 small">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Auto-cadastro <strong>ativado</strong> — <a href="<?= site_url('register') ?>" target="_blank" class="alert-link"><?= site_url('register') ?></a>
                </div>
                <?php else: ?>
                <div class="alert alert-secondary py-2 mb-0 small">
                    <i class="bi bi-x-circle me-1"></i>
                    Auto-cadastro <strong>desativado</strong> — o link "Criar conta" redireciona para o login.
                </div>
                <?php endif; ?>
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
                        <i class="bi bi-floppy-fill me-2"></i>Salvar Controles
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

<?= view('admin/settings/partials/controls_scripts') ?>
<?= $this->endSection() ?>
