<?php

/*
 * --------------------------------------------------------------------
 * Dashboard, Profile and Notifications
 * --------------------------------------------------------------------
 */
$dashboardController = 'Dashboard\DashboardController';
$dashboardAuth = ['filter' => 'auth'];
$dashboardAdmin = ['filter' => ['auth', 'admin']];
$dashboardManager = ['filter' => ['auth', 'manager']];
$dashboardEmployee = ['filter' => 'auth'];

$routes->get('/dashboard', $dashboardController . '::index', $dashboardAuth);
$routes->get('/inicio', $dashboardController . '::index', ['filter' => 'auth', 'as' => 'home']);
$routes->get('/painel', $dashboardController . '::index', ['filter' => 'auth', 'as' => 'panel']);

$routes->group('dashboard', $dashboardAuth, static function ($routes) use ($dashboardController, $dashboardAdmin, $dashboardManager, $dashboardEmployee) {
    $routes->get('/', $dashboardController . '::index', ['as' => 'dashboard']);
    $routes->get('admin', $dashboardController . '::admin', ['as' => 'dashboard.admin', 'filter' => ['auth', 'admin']]);
    $routes->get('manager', $dashboardController . '::manager', ['as' => 'dashboard.manager', 'filter' => ['auth', 'manager']]);
    $routes->get('dpo', $dashboardController . '::dpo', ['as' => 'dashboard.dpo', 'filter' => ['auth', 'role:admin,dpo']]);
    $routes->get('employee', $dashboardController . '::employee', ['as' => 'dashboard.employee', 'filter' => 'auth']);
});

$routes->group('profile', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Employees\EmployeeController::profile', ['as' => 'profile']);
    $routes->post('update', 'Employees\EmployeeController::updateProfile', ['as' => 'profile.update']);
    $routes->get('biometric', 'Employees\EmployeeController::biometric', ['as' => 'profile.biometric']);
    $routes->post('biometric/consent', 'Employees\EmployeeController::biometricConsent', ['as' => 'profile.biometric.consent']);
    $routes->post('biometric/revoke', 'Employees\EmployeeController::biometricRevoke', ['as' => 'profile.biometric.revoke', 'filter' => ['auth', 'stepup:profile.security']]);
    $routes->get('change-password', 'Employees\EmployeeController::changePassword', ['as' => 'profile.changePassword']);

    $routes->get('security', 'Security\SessionController::index', ['as' => 'profile.security']);
    $routes->post('security/confirm-password', 'Security\SessionController::confirmPassword', ['as' => 'profile.security.confirm-password']);
    $routes->post('security/revoke-others', 'Security\SessionController::revokeOthers', ['as' => 'profile.security.revoke-others', 'filter' => ['auth', 'stepup:profile.security']]);
    $routes->post('security/revoke/(:segment)', 'Security\SessionController::revoke/$1', ['as' => 'profile.security.revoke', 'filter' => ['auth', 'stepup:profile.security']]);
    $routes->post('change-password', 'Employees\EmployeeController::updatePassword', ['as' => 'profile.changePassword.update']);
});

$routes->group('notifications', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', static fn() => redirect()->to(site_url('operations/notifications-center')), ['as' => 'notifications']);
    $routes->get('(:num)', static fn() => redirect()->to(site_url('operations/notifications-center')), ['as' => 'notifications.show']);
    $routes->post('(:num)/read', 'NotificationsController::markAsRead/$1', ['as' => 'notifications.read']);
    $routes->post('mark-all-read', 'NotificationsController::markAllAsRead', ['as' => 'notifications.mark-all-read']);
});

$routes->get('admin/home', $dashboardController . '::admin', ['as' => 'dashboard.admin.home', 'filter' => ['auth', 'admin']]);
$routes->get('gestor/home', $dashboardController . '::manager', ['as' => 'dashboard.manager.home', 'filter' => ['auth', 'manager']]);
$routes->get('colaborador/home', $dashboardController . '::employee', ['as' => 'dashboard.employee.home', 'filter' => 'auth']);
$routes->get('funcionario/home', 'Compatibility\LegacyRouteRedirectController::employeeHome', ['as' => 'dashboard.employee.home.legacy', 'filter' => 'auth']);
$routes->get('dpo/home', $dashboardController . '::dpo', ['as' => 'dashboard.dpo.home', 'filter' => ['auth', 'role:admin,dpo']]);

$routes->get('conta/perfil', 'ProfileController::index', ['as' => 'conta.perfil', 'filter' => 'auth']);
$routes->get('conta/biometria', 'ProfileController::biometric', ['as' => 'conta.biometria', 'filter' => 'auth']);

$routes->get('operations/pending-center', 'Operations\PendingCenterController::index', ['as' => 'operations.pending.center', 'filter' => ['auth', 'manager']]);
$routes->view('operations/action-center', 'operations/action_center', ['as' => 'operations.action.center', 'filter' => 'auth']);
$routes->get('operations/notifications-center', 'Operations\NotificationCenterController::index', ['as' => 'operations.notifications.center', 'filter' => 'auth']);
$routes->view('operations/onboarding', 'operations/onboarding', ['as' => 'operations.onboarding', 'filter' => 'auth']);

$routes->view('employees/change-history', 'employees/change_history', ['as' => 'employees.change.history', 'filter' => ['auth', 'manager']]);
$routes->get('admin/health', 'Admin\HealthController::index', ['as' => 'admin.health', 'filter' => ['auth', 'admin']]);
$routes->get('admin/health/json', 'Admin\HealthController::json', ['as' => 'admin.health.json', 'filter' => ['auth', 'admin']]);
$routes->get('admin/health/support-diagnostics', 'Admin\HealthController::diagnostics', ['as' => 'admin.health.diagnostics', 'filter' => ['auth', 'admin']]);
$routes->get('admin/health/support-bundle', 'Admin\HealthController::supportBundle', ['as' => 'admin.health.support_bundle', 'filter' => ['auth', 'admin']]);
$routes->get('admin/metrics', 'Admin\MetricsController::index', ['as' => 'admin.metrics', 'filter' => ['auth', 'admin']]);
$routes->get('admin/system-health', 'Admin\HealthController::index', ['as' => 'admin.system.health', 'filter' => ['auth', 'admin']]);
$routes->get('admin/system-health/support-diagnostics', 'Admin\HealthController::diagnostics', ['as' => 'admin.system.health.diagnostics', 'filter' => ['auth', 'admin']]);
$routes->get('admin/system-health/support-bundle', 'Admin\HealthController::supportBundle', ['as' => 'admin.system.health.support_bundle', 'filter' => ['auth', 'admin']]);

$routes->get('compliance/audit-advanced', 'Compliance\AuditAdvancedController::index', ['as' => 'compliance.audit.advanced', 'filter' => ['auth', 'role:admin,dpo,auditor']]);
$routes->get('compliance/lgpd', 'Compliance\LgpdController::index', ['as' => 'compliance.lgpd', 'filter' => ['auth', 'role:admin,dpo']]);
$routes->get('compliance/permissions-matrix', 'Compliance\PermissionMatrixController::index', ['as' => 'compliance.permissions.matrix', 'filter' => ['auth', 'role:admin,dpo,auditor']]);
$routes->get('compliance/security-center', 'Compliance\SecurityCenterController::index', ['as' => 'compliance.security.center', 'filter' => ['auth', 'admin']]);


$routes->get('compliance/biometric', 'Compliance\BiometricComplianceController::index', ['as' => 'compliance.biometric', 'filter' => ['auth', 'role:admin,dpo']]);
