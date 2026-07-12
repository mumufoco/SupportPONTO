<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Editar Nível de Acesso<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0">Editar: <?= esc($role->name ?? $role['name'] ?? '') ?></h1>
        <a href="<?= sp_route_url('settings.roles') ?>" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
    <div class="card">
        <div class="card-body">
            <form action="<?= sp_route_url('settings.roles.update', ($role->id ?? $role['id'])) ?>" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Nome <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= esc(old('name', $role->name ?? $role['name'] ?? '')) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-control" rows="2"><?= esc(old('description', $role->description ?? $role['description'] ?? '')) ?></textarea>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Permissões</label>
                    <p class="text-muted small mb-3">Selecione as permissões deste nível. Administradores têm acesso total (<code>*</code>) automaticamente.</p>
                    <?php
                        $_currentPerms = [];
                        if (!empty($role->permissions ?? null)) {
                            $_p = is_string($role->permissions) ? json_decode($role->permissions, true) : (array) $role->permissions;
                            $_currentPerms = is_array($_p) ? $_p : [];
                        }
                        $_oldPerms = old('permissions', $_currentPerms);
                        if (is_string($_oldPerms)) { $_oldPerms = json_decode($_oldPerms, true) ?: []; }
                        $_isAdmin = in_array('*', $_currentPerms, true);
                    ?>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">Colaboradores</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_employees_manage" value="employees.manage"
                                           <?= in_array('employees.manage', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_employees_manage">
                                        <code class="text-muted" style="font-size:.7rem">employees.manage</code><br>
                                        Gerenciar todos os colaboradores
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_employees_manage_team" value="employees.manage.team"
                                           <?= in_array('employees.manage.team', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_employees_manage_team">
                                        <code class="text-muted" style="font-size:.7rem">employees.manage.team</code><br>
                                        Gerenciar equipe/departamento
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_employees_approve" value="employees.approve"
                                           <?= in_array('employees.approve', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_employees_approve">
                                        <code class="text-muted" style="font-size:.7rem">employees.approve</code><br>
                                        Aprovar cadastros
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">Relatórios</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_reports_view" value="reports.view"
                                           <?= in_array('reports.view', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_reports_view">
                                        <code class="text-muted" style="font-size:.7rem">reports.view</code><br>
                                        Visualizar relatórios
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_reports_export" value="reports.export"
                                           <?= in_array('reports.export', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_reports_export">
                                        <code class="text-muted" style="font-size:.7rem">reports.export</code><br>
                                        Exportar relatórios
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_reports_audit" value="reports.audit"
                                           <?= in_array('reports.audit', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_reports_audit">
                                        <code class="text-muted" style="font-size:.7rem">reports.audit</code><br>
                                        Relatórios de auditoria
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">Advertências e Justificativas</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_warnings_manage" value="warnings.manage"
                                           <?= in_array('warnings.manage', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_warnings_manage">
                                        <code class="text-muted" style="font-size:.7rem">warnings.manage</code><br>
                                        Gerenciar advertências (todos)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_warnings_manage_team" value="warnings.manage.team"
                                           <?= in_array('warnings.manage.team', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_warnings_manage_team">
                                        <code class="text-muted" style="font-size:.7rem">warnings.manage.team</code><br>
                                        Gerenciar advertências (equipe)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_warnings_view_self" value="warnings.view.self"
                                           <?= in_array('warnings.view.self', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_warnings_view_self">
                                        <code class="text-muted" style="font-size:.7rem">warnings.view.self</code><br>
                                        Ver próprias advertências
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_justifications_approve" value="justifications.approve"
                                           <?= in_array('justifications.approve', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_justifications_approve">
                                        <code class="text-muted" style="font-size:.7rem">justifications.approve</code><br>
                                        Aprovar justificativas (todos)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_justifications_approve_team" value="justifications.approve.team"
                                           <?= in_array('justifications.approve.team', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_justifications_approve_team">
                                        <code class="text-muted" style="font-size:.7rem">justifications.approve.team</code><br>
                                        Aprovar justificativas (equipe)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_justifications_self" value="justifications.self"
                                           <?= in_array('justifications.self', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_justifications_self">
                                        <code class="text-muted" style="font-size:.7rem">justifications.self</code><br>
                                        Enviar próprias justificativas
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">Biometria</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_biometric_manage" value="biometric.manage"
                                           <?= in_array('biometric.manage', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_biometric_manage">
                                        <code class="text-muted" style="font-size:.7rem">biometric.manage</code><br>
                                        Gerenciar biometria (todos)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_biometric_manage_team" value="biometric.manage.team"
                                           <?= in_array('biometric.manage.team', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_biometric_manage_team">
                                        <code class="text-muted" style="font-size:.7rem">biometric.manage.team</code><br>
                                        Gerenciar biometria (equipe)
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_biometric_self" value="biometric.self"
                                           <?= in_array('biometric.self', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_biometric_self">
                                        <code class="text-muted" style="font-size:.7rem">biometric.self</code><br>
                                        Biometria própria
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">Auditoria e Conformidade</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_audit_view" value="audit.view"
                                           <?= in_array('audit.view', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_audit_view">
                                        <code class="text-muted" style="font-size:.7rem">audit.view</code><br>
                                        Ver todos os logs
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_audit_view_limited" value="audit.view.limited"
                                           <?= in_array('audit.view.limited', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_audit_view_limited">
                                        <code class="text-muted" style="font-size:.7rem">audit.view.limited</code><br>
                                        Ver logs do departamento
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_audit_view_details" value="audit.view.details"
                                           <?= in_array('audit.view.details', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_audit_view_details">
                                        <code class="text-muted" style="font-size:.7rem">audit.view.details</code><br>
                                        Ver detalhes de log
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_audit_export" value="audit.export"
                                           <?= in_array('audit.export', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_audit_export">
                                        <code class="text-muted" style="font-size:.7rem">audit.export</code><br>
                                        Exportar logs
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_compliance_view" value="compliance.view"
                                           <?= in_array('compliance.view', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_compliance_view">
                                        <code class="text-muted" style="font-size:.7rem">compliance.view</code><br>
                                        Ver painel de conformidade
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">LGPD</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_lgpd_view" value="lgpd.view"
                                           <?= in_array('lgpd.view', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_lgpd_view">
                                        <code class="text-muted" style="font-size:.7rem">lgpd.view</code><br>
                                        Ver dados LGPD
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_lgpd_manage" value="lgpd.manage"
                                           <?= in_array('lgpd.manage', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_lgpd_manage">
                                        <code class="text-muted" style="font-size:.7rem">lgpd.manage</code><br>
                                        Gerenciar LGPD
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="text-muted small text-uppercase fw-semibold mb-2">Ponto e Perfil</p>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_timesheet_self" value="timesheet.self"
                                           <?= in_array('timesheet.self', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_timesheet_self">
                                        <code class="text-muted" style="font-size:.7rem">timesheet.self</code><br>
                                        Registrar próprio ponto
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="permissions[]"
                                           id="perm_profile_self" value="profile.self"
                                           <?= in_array('profile.self', $_oldPerms, true) ? 'checked' : '' ?>
                                           <?= $_isAdmin ? 'disabled' : '' ?>>
                                    <label class="form-check-label small" for="perm_profile_self">
                                        <code class="text-muted" style="font-size:.7rem">profile.self</code><br>
                                        Editar próprio perfil
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <a href="<?= sp_route_url('settings.roles') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
