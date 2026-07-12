<?php

helper(['session_context', 'navigation_context']);

$employee         = sp_session_user();
$normalizedRole   = sp_session_role();
$isAdmin          = $normalizedRole === 'admin';
$canManageArea    = sp_can_manage_area($normalizedRole);
$canReviewPunches = sp_can_review_pending_punches($normalizedRole);
$canAudit         = sp_can_audit_area($normalizedRole);
?>
<!-- Right Sidebar - Ações Rápidas -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="rightSidebar" aria-labelledby="rightSidebarLabel">
    <div class="offcanvas-header sp-offcanvas-header-brand">
        <h5 class="offcanvas-title text-white" id="rightSidebarLabel">
            <i class="bi bi-lightning-charge-fill me-2"></i>Ações Rápidas
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body">
        <?php if (sp_session_is_authenticated()): ?>
            <div class="list-group list-group-flush">

                <?php if ($isAdmin): ?>
                    <!-- Ações exclusivas do Administrador -->
                    <a href="<?= sp_employees_index_url() ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-people-fill text-primary me-2"></i>
                        <strong>Funcionários</strong>
                        <small class="d-block text-muted">Gerenciar equipe e cadastros</small>
                    </a>
                    <a href="<?= sp_timesheet_index_url() ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-clock-history text-success me-2"></i>
                        <strong>Espelho de ponto</strong>
                        <small class="d-block text-muted">Consultar marcações da equipe</small>
                    </a>
                    <a href="<?= sp_reports_index_url() ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-bar-chart-fill text-info me-2"></i>
                        <strong>Relatórios</strong>
                        <small class="d-block text-muted">Gerar relatórios e indicadores</small>
                    </a>
                    <a href="<?= sp_settings_center_url() ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-gear-fill text-warning me-2"></i>
                        <strong>Configurações</strong>
                        <small class="d-block text-muted">Sistema, segurança e biometria</small>
                    </a>
                    <a href="<?= site_url('admin/biometric/dashboard') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-fingerprint text-danger me-2"></i>
                        <strong>Painel biométrico</strong>
                        <small class="d-block text-muted">Gerenciar cadastros faciais e digitais</small>
                    </a>
                    <?php if ($canAudit): ?>
                        <hr class="my-2">
                        <a href="<?= base_url('audit') ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-shield-fill-check text-dark me-2"></i>
                            <strong>Auditoria e LGPD</strong>
                            <small class="d-block text-muted">Trilhas de acesso e conformidade</small>
                        </a>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Ações de Gestor / Funcionário / Colaborador -->
                    <a href="<?= sp_timesheet_punch_url() ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-fingerprint text-primary me-2"></i>
                        <strong>Registrar ponto</strong>
                        <small class="d-block text-muted">Bater ponto agora</small>
                    </a>
                    <a href="<?= base_url('timesheet/history') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-calendar-range-fill text-success me-2"></i>
                        <strong>Espelho de ponto</strong>
                        <small class="d-block text-muted">Consultar registros pessoais</small>
                    </a>
                    <a href="<?= base_url('justifications/create') ?>" class="list-group-item list-group-item-action">
                        <i class="bi bi-file-earmark-text text-warning me-2"></i>
                        <strong>Nova justificativa</strong>
                        <small class="d-block text-muted">Justificar ausência ou atraso</small>
                    </a>

                    <?php if ($canReviewPunches): ?>
                        <a href="<?= base_url('manager/pending-punches') ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-person-check-fill text-danger me-2"></i>
                            <strong>Revisar pendências</strong>
                            <small class="d-block text-muted">Aprovar ou rejeitar registros</small>
                        </a>
                    <?php endif; ?>

                    <?php if ($canManageArea): ?>
                        <hr class="my-2">
                        <a href="<?= sp_reports_index_url() ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-bar-chart-fill text-info me-2"></i>
                            <strong>Relatórios</strong>
                            <small class="d-block text-muted">Gerar relatórios e indicadores</small>
                        </a>
                        <a href="<?= base_url('employees') ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-people-fill text-secondary me-2"></i>
                            <strong>Funcionários</strong>
                            <small class="d-block text-muted">Gerenciar equipe e cadastros</small>
                        </a>
                    <?php endif; ?>

                    <?php if ($canAudit): ?>
                        <a href="<?= base_url('audit') ?>" class="list-group-item list-group-item-action">
                            <i class="bi bi-shield-fill-check text-dark me-2"></i>
                            <strong>Auditoria e LGPD</strong>
                            <small class="d-block text-muted">Trilhas e conformidade</small>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>

            </div>

            <hr>

            <?php if ($isAdmin): ?>
                <div class="p-3 bg-light rounded">
                    <h6 class="fw-bold mb-2">
                        <i class="bi bi-shield-fill me-2"></i>Acesso total
                    </h6>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Você tem permissão total no sistema</li>
                        <li>Use Configurações para ajustes sistêmicos</li>
                        <li>Acompanhe a saúde do sistema em Monitoramento</li>
                    </ul>
                </div>
            <?php else: ?>
                <div class="p-3 bg-light rounded">
                    <h6 class="fw-bold mb-2">
                        <i class="bi bi-info-circle me-2"></i>Dicas
                    </h6>
                    <ul class="small text-muted mb-0 ps-3">
                        <li>Registre o ponto nos horários corretos</li>
                        <li>Sempre justifique atrasos ou faltas</li>
                        <li>Consulte pendências e saldo de horas com frequência</li>
                    </ul>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-box-arrow-in-right fs-1 text-muted mb-3 d-block"></i>
                <p class="text-muted">Faça login para acessar as ações rápidas</p>
                <a href="<?= sp_login_url() ?>" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
