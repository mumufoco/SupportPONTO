<?php

/*
 * --------------------------------------------------------------------
 * API Routes — versionadas e protegidas
 * --------------------------------------------------------------------
 * Política do Pacote 444:
 *  - endpoints públicos mínimos: /api/health e /api/validate-code;
 *  - todo endpoint operacional/dados usa OAuth2;
 *  - endpoints gerenciais usam OAuth2 + RBAC;
 *  - toda API passa por CORS, rate limit e validação JSON malformado;
 *  - aliases /api/* permanecem apenas por retrocompatibilidade v1.
 * --------------------------------------------------------------------
 */

$apiPublicFilters = ['cors', 'ratelimit', 'api-json'];
$apiVersionedPublicFilters = ['cors', 'ratelimit', 'api-json', 'api-version'];
$apiAuthFilters = ['cors', 'ratelimit', 'api-json', 'oauth2'];
$apiManagerFilters = ['cors', 'ratelimit', 'api-json', 'oauth2', 'api-role:admin,rh,gestor'];
$apiAdminFilters = ['cors', 'ratelimit', 'api-json', 'oauth2', 'api-role:admin'];
$apiBiometricFilters = ['cors', 'ratelimit', 'api-json', 'oauth2', 'biometric-rate-limit'];

// Endpoints públicos mínimos, sem dados sensíveis.
$routes->group('api', ['filter' => $apiPublicFilters], static function ($routes) {
    $routes->post('validate-code', 'API\ApiController::validateCode', ['as' => 'api.validate-code', 'filter' => 'throttle']);
    $routes->get('health', 'API\ApiController::health', ['as' => 'api.health']);
});

// API v1 — versão estável atual.
$routes->group('api/v1', ['filter' => 'cors'], static function ($routes) use ($apiAuthFilters, $apiManagerFilters, $apiAdminFilters, $apiBiometricFilters) {
    $routes->group('auth', ['filter' => ['ratelimit', 'api-json']], static function ($routes) {
        $routes->post('login', 'API\AuthController::login', ['as' => 'api.v1.auth.login']);
        $routes->post('2fa/verify', 'API\AuthController::verifyTwoFactor', ['as' => 'api.v1.auth.2fa.verify']);
        $routes->post('refresh', 'API\AuthController::refresh', ['as' => 'api.v1.auth.refresh']);
        $routes->post('logout', 'API\AuthController::logout', ['as' => 'api.v1.auth.logout', 'filter' => 'oauth2']);
        $routes->get('me', 'API\AuthController::me', ['as' => 'api.v1.auth.me', 'filter' => 'oauth2']);
        $routes->post('change-password', 'API\AuthController::changePassword', ['as' => 'api.v1.auth.change-password', 'filter' => 'oauth2']);
    });

    $routes->group('oauth', ['filter' => ['ratelimit', 'api-json']], static function ($routes) {
        $routes->post('token', 'API\OAuth2Controller::token', ['as' => 'api.v1.oauth.token']);
        $routes->post('refresh', 'API\OAuth2Controller::refresh', ['as' => 'api.v1.oauth.refresh']);
        $routes->post('revoke', 'API\OAuth2Controller::revoke', ['as' => 'api.v1.oauth.revoke', 'filter' => 'oauth2']);
        $routes->get('tokens', 'API\OAuth2Controller::listTokens', ['as' => 'api.v1.oauth.tokens', 'filter' => 'oauth2']);
        $routes->post('revoke-all', 'API\OAuth2Controller::revokeAll', ['as' => 'api.v1.oauth.revoke-all', 'filter' => 'oauth2']);
    });

    $routes->group('deepface', ['filter' => $apiManagerFilters], static function ($routes) {
        $routes->post('enroll', 'API\ApiController::deepfaceEnroll', ['as' => 'api.v1.deepface.enroll']);
        $routes->post('recognize', 'API\ApiController::deepfaceRecognize', ['as' => 'api.v1.deepface.recognize']);
    });

    $routes->group('biometric', ['filter' => $apiBiometricFilters], static function ($routes) {
        $routes->post('enroll/face', 'API\BiometricController::enrollFace', ['as' => 'api.v1.biometric.enroll.face']);
        $routes->post('test', 'API\BiometricController::testFace', ['as' => 'api.v1.biometric.test']);
        $routes->get('templates', 'API\BiometricController::templates', ['as' => 'api.v1.biometric.templates']);
        $routes->delete('face/(:num)', 'API\BiometricController::deleteFace/$1', ['as' => 'api.v1.biometric.face.delete']);
        $routes->post('consent', 'API\BiometricController::grantConsent', ['as' => 'api.v1.biometric.consent']);
        $routes->post('revoke-consent', 'API\BiometricController::revokeConsent', ['as' => 'api.v1.biometric.revoke-consent']);
        $routes->get('consent/status', 'API\BiometricController::consentStatus', ['as' => 'api.v1.biometric.consent.status']);
        $routes->post('enroll', 'API\BiometricFingerprintController::enroll', ['as' => 'api.v1.biometric.fingerprint.enroll']);
        $routes->post('verify', 'API\BiometricFingerprintController::verify', ['as' => 'api.v1.biometric.fingerprint.verify']);
        $routes->post('identify', 'API\BiometricFingerprintController::identify', ['as' => 'api.v1.biometric.fingerprint.identify']);
        $routes->get('templates/(:num)', 'API\BiometricFingerprintController::listTemplates/$1', ['as' => 'api.v1.biometric.fingerprint.templates']);
        $routes->delete('template/(:num)', 'API\BiometricFingerprintController::deleteTemplate/$1', ['as' => 'api.v1.biometric.fingerprint.delete']);
        $routes->post('consent/fingerprint', 'API\BiometricFingerprintController::consent', ['as' => 'api.v1.biometric.fingerprint.consent']);
        $routes->post('revoke-consent/fingerprint', 'API\BiometricFingerprintController::revokeConsent', ['as' => 'api.v1.biometric.fingerprint.revoke']);
        $routes->get('consent/fingerprint/status', 'API\BiometricFingerprintController::consentStatus', ['as' => 'api.v1.biometric.fingerprint.consent.status']);
    });

    $routes->group('dashboard', ['filter' => $apiManagerFilters], static function ($routes) {
        $routes->get('/', 'API\DashboardController::index', ['as' => 'api.v1.dashboard.index']);
        $routes->get('kpis', 'API\DashboardController::kpis', ['as' => 'api.v1.dashboard.kpis']);
        $routes->get('charts', 'API\DashboardController::charts', ['as' => 'api.v1.dashboard.charts']);
        $routes->get('activity', 'API\DashboardController::activity', ['as' => 'api.v1.dashboard.activity']);
        $routes->get('top-employees', 'API\DashboardController::topEmployees', ['as' => 'api.v1.dashboard.top-employees']);
        $routes->get('attendance', 'API\DashboardController::attendance', ['as' => 'api.v1.dashboard.attendance']);
        $routes->get('departments', 'API\DashboardController::departments', ['as' => 'api.v1.dashboard.departments']);
    });

    $routes->group('employee', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->get('profile', 'API\EmployeeController::profile', ['as' => 'api.v1.employee.profile']);
        $routes->get('balance', 'API\EmployeeController::balance', ['as' => 'api.v1.employee.balance']);
        $routes->get('statistics', 'API\EmployeeController::statistics', ['as' => 'api.v1.employee.statistics']);
        $routes->post('profile', 'API\EmployeeController::updateProfile', ['as' => 'api.v1.employee.profile.update']);
        $routes->get('team', 'API\EmployeeController::team', ['as' => 'api.v1.employee.team', 'filter' => 'api-role:admin,rh,gestor']);
        $routes->get('by-code/(:segment)', 'API\EmployeeController::byCode/$1', ['as' => 'api.v1.employee.by-code', 'filter' => 'api-role:admin,rh,gestor']);
    });

    $routes->group('time-punch', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->post('/', 'API\TimePunchController::create', ['as' => 'api.v1.time-punch.create']);
        $routes->get('today', 'API\TimePunchController::today', ['as' => 'api.v1.time-punch.today']);
        $routes->get('history', 'API\TimePunchController::history', ['as' => 'api.v1.time-punch.history']);
        $routes->get('summary', 'API\TimePunchController::summary', ['as' => 'api.v1.time-punch.summary']);
        $routes->get('verify/(:num)', 'API\TimePunchController::verify/$1', ['as' => 'api.v1.time-punch.verify']);
        $routes->get('geofences', 'API\TimePunchController::geofences', ['as' => 'api.v1.time-punch.geofences']);
    });

    $routes->group('notifications', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->get('/', 'API\NotificationController::index', ['as' => 'api.v1.notifications.index']);
        $routes->get('unread', 'API\NotificationController::unread', ['as' => 'api.v1.notifications.unread']);
        $routes->get('unread-count', 'API\NotificationController::unreadCount', ['as' => 'api.v1.notifications.unread-count']);
        $routes->get('(:num)', 'API\NotificationController::show/$1', ['as' => 'api.v1.notifications.show']);
        $routes->post('(:num)/read', 'API\NotificationController::markAsRead/$1', ['as' => 'api.v1.notifications.read']);
        $routes->post('read-all', 'API\NotificationController::markAllAsRead', ['as' => 'api.v1.notifications.read-all']);
        $routes->delete('(:num)', 'API\NotificationController::delete/$1', ['as' => 'api.v1.notifications.delete']);
        $routes->delete('read', 'API\NotificationController::deleteAllRead', ['as' => 'api.v1.notifications.delete-read']);
    });

    $routes->group('push', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->post('register', 'API\PushNotificationController::register', ['as' => 'api.v1.push.register']);
        $routes->post('unregister', 'API\PushNotificationController::unregister', ['as' => 'api.v1.push.unregister']);
        $routes->post('test', 'API\PushNotificationController::sendTest', ['as' => 'api.v1.push.test']);
        $routes->get('templates', 'API\PushNotificationController::templates', ['as' => 'api.v1.push.templates']);
    });

    $routes->group('chat', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->get('rooms', 'API\ChatAPIController::getRooms', ['as' => 'api.v1.chat.rooms']);
        $routes->post('rooms/private', 'API\ChatAPIController::createPrivateRoom', ['as' => 'api.v1.chat.rooms.private']);
        $routes->post('rooms/group', 'API\ChatAPIController::createGroupRoom', ['as' => 'api.v1.chat.rooms.group']);
        $routes->get('rooms/(:num)/messages', 'API\ChatAPIController::getMessages/$1', ['as' => 'api.v1.chat.rooms.messages']);
        $routes->post('rooms/(:num)/messages', 'API\ChatAPIController::sendMessage/$1', ['as' => 'api.v1.chat.rooms.messages.send']);
        $routes->post('rooms/(:num)/read', 'API\ChatAPIController::markAsRead/$1', ['as' => 'api.v1.chat.rooms.read']);
        $routes->get('rooms/(:num)/search', 'API\ChatAPIController::searchMessages/$1', ['as' => 'api.v1.chat.rooms.search']);
        $routes->get('rooms/(:num)/members', 'API\ChatAPIController::getMembers/$1', ['as' => 'api.v1.chat.rooms.members']);
        $routes->post('rooms/(:num)/members', 'API\ChatAPIController::addMember/$1', ['as' => 'api.v1.chat.rooms.members.add']);
        $routes->delete('rooms/(:num)/members/(:num)', 'API\ChatAPIController::removeMember/$1/$2', ['as' => 'api.v1.chat.rooms.members.remove']);
        $routes->put('messages/(:num)', 'API\ChatAPIController::editMessage/$1', ['as' => 'api.v1.chat.messages.edit']);
        $routes->delete('messages/(:num)', 'API\ChatAPIController::deleteMessage/$1', ['as' => 'api.v1.chat.messages.delete']);
        $routes->post('messages/(:num)/reactions', 'API\ChatAPIController::addReaction/$1', ['as' => 'api.v1.chat.messages.reactions']);
        $routes->get('online', 'API\ChatAPIController::getOnlineUsers', ['as' => 'api.v1.chat.online']);
    });

    $routes->get('reports/status/(:segment)', 'API\ReportController::status/$1', ['as' => 'api.v1.reports.status', 'filter' => $apiAuthFilters]);
    $routes->get('reports/download/(:segment)', 'API\ReportController::download/$1', ['as' => 'api.v1.reports.download', 'filter' => $apiAuthFilters]);
    $routes->get('jobs/status/(:segment)', 'API\AsyncJobController::status/$1', ['as' => 'api.v1.jobs.status', 'filter' => $apiAuthFilters]);
    $routes->get('jobs/download/(:segment)', 'API\AsyncJobController::download/$1', ['as' => 'api.v1.jobs.download', 'filter' => $apiAuthFilters]);
});

// Aliases retrocompatíveis /api/* => v1, exceto health/validate-code acima.
$routes->group('api', ['filter' => ['cors', 'api-version']], static function ($routes) use ($apiAuthFilters, $apiManagerFilters, $apiAdminFilters, $apiBiometricFilters) {
    $routes->group('auth', ['filter' => ['ratelimit', 'api-json']], static function ($routes) {
        $routes->post('login', 'API\AuthController::login', ['as' => 'api.auth.login']);
        $routes->post('2fa/verify', 'API\AuthController::verifyTwoFactor', ['as' => 'api.auth.2fa.verify']);
        $routes->post('refresh', 'API\AuthController::refresh', ['as' => 'api.auth.refresh']);
        $routes->post('logout', 'API\AuthController::logout', ['as' => 'api.auth.logout', 'filter' => 'oauth2']);
        $routes->get('me', 'API\AuthController::me', ['as' => 'api.auth.me', 'filter' => 'oauth2']);
        $routes->post('change-password', 'API\AuthController::changePassword', ['as' => 'api.auth.change-password', 'filter' => 'oauth2']);
    });

    $routes->group('oauth', ['filter' => ['ratelimit', 'api-json']], static function ($routes) {
        $routes->post('token', 'API\OAuth2Controller::token', ['as' => 'api.oauth.token']);
        $routes->post('refresh', 'API\OAuth2Controller::refresh', ['as' => 'api.oauth.refresh']);
        $routes->post('revoke', 'API\OAuth2Controller::revoke', ['as' => 'api.oauth.revoke', 'filter' => 'oauth2']);
        $routes->get('tokens', 'API\OAuth2Controller::listTokens', ['as' => 'api.oauth.tokens', 'filter' => 'oauth2']);
        $routes->post('revoke-all', 'API\OAuth2Controller::revokeAll', ['as' => 'api.oauth.revoke-all', 'filter' => 'oauth2']);
    });

    $routes->group('deepface', ['filter' => $apiManagerFilters], static function ($routes) {
        $routes->post('enroll', 'API\ApiController::deepfaceEnroll', ['as' => 'api.deepface.enroll']);
        $routes->post('recognize', 'API\ApiController::deepfaceRecognize', ['as' => 'api.deepface.recognize']);
    });

    $routes->group('biometric', ['filter' => $apiBiometricFilters], static function ($routes) {
        $routes->post('enroll/face', 'API\BiometricController::enrollFace', ['as' => 'api.biometric.enroll.face']);
        $routes->post('test', 'API\BiometricController::testFace', ['as' => 'api.biometric.test']);
        $routes->get('templates', 'API\BiometricController::templates', ['as' => 'api.biometric.templates']);
        $routes->delete('face/(:num)', 'API\BiometricController::deleteFace/$1', ['as' => 'api.biometric.face.delete']);
        $routes->post('consent', 'API\BiometricController::grantConsent', ['as' => 'api.biometric.consent']);
        $routes->post('revoke-consent', 'API\BiometricController::revokeConsent', ['as' => 'api.biometric.revoke-consent']);
        $routes->get('consent/status', 'API\BiometricController::consentStatus', ['as' => 'api.biometric.consent.status']);
        $routes->post('enroll', 'API\BiometricFingerprintController::enroll', ['as' => 'api.biometric.fingerprint.enroll']);
        $routes->post('verify', 'API\BiometricFingerprintController::verify', ['as' => 'api.biometric.fingerprint.verify']);
        $routes->post('identify', 'API\BiometricFingerprintController::identify', ['as' => 'api.biometric.fingerprint.identify']);
        $routes->get('templates/(:num)', 'API\BiometricFingerprintController::listTemplates/$1', ['as' => 'api.biometric.fingerprint.templates']);
        $routes->delete('template/(:num)', 'API\BiometricFingerprintController::deleteTemplate/$1', ['as' => 'api.biometric.fingerprint.delete']);
        $routes->post('consent/fingerprint', 'API\BiometricFingerprintController::consent', ['as' => 'api.biometric.fingerprint.consent']);
        $routes->post('revoke-consent/fingerprint', 'API\BiometricFingerprintController::revokeConsent', ['as' => 'api.biometric.fingerprint.revoke']);
        $routes->get('consent/fingerprint/status', 'API\BiometricFingerprintController::consentStatus', ['as' => 'api.biometric.fingerprint.consent.status']);
    });

    $routes->group('dashboard', ['filter' => $apiManagerFilters], static function ($routes) {
        $routes->get('/', 'API\DashboardController::index', ['as' => 'api.dashboard.index']);
        $routes->get('kpis', 'API\DashboardController::kpis', ['as' => 'api.dashboard.kpis']);
        $routes->get('charts', 'API\DashboardController::charts', ['as' => 'api.dashboard.charts']);
        $routes->get('activity', 'API\DashboardController::activity', ['as' => 'api.dashboard.activity']);
        $routes->get('top-employees', 'API\DashboardController::topEmployees', ['as' => 'api.dashboard.top-employees']);
        $routes->get('attendance', 'API\DashboardController::attendance', ['as' => 'api.dashboard.attendance']);
        $routes->get('departments', 'API\DashboardController::departments', ['as' => 'api.dashboard.departments']);
    });

    $routes->group('employee', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->get('profile', 'API\EmployeeController::profile', ['as' => 'api.employee.profile']);
        $routes->get('balance', 'API\EmployeeController::balance', ['as' => 'api.employee.balance']);
        $routes->get('statistics', 'API\EmployeeController::statistics', ['as' => 'api.employee.statistics']);
        $routes->post('profile', 'API\EmployeeController::updateProfile', ['as' => 'api.employee.profile.update']);
        $routes->get('team', 'API\EmployeeController::team', ['as' => 'api.employee.team', 'filter' => 'api-role:admin,rh,gestor']);
        $routes->get('by-code/(:segment)', 'API\EmployeeController::byCode/$1', ['as' => 'api.employee.by-code', 'filter' => 'api-role:admin,rh,gestor']);
    });

    $routes->group('time-punch', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->post('/', 'API\TimePunchController::create', ['as' => 'api.time-punch.create']);
        $routes->get('today', 'API\TimePunchController::today', ['as' => 'api.time-punch.today']);
        $routes->get('history', 'API\TimePunchController::history', ['as' => 'api.time-punch.history']);
        $routes->get('summary', 'API\TimePunchController::summary', ['as' => 'api.time-punch.summary']);
        $routes->get('verify/(:num)', 'API\TimePunchController::verify/$1', ['as' => 'api.time-punch.verify']);
        $routes->get('geofences', 'API\TimePunchController::geofences', ['as' => 'api.time-punch.geofences']);
    });

    $routes->group('notifications', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->get('/', 'API\NotificationController::index', ['as' => 'api.notifications.index']);
        $routes->get('unread', 'API\NotificationController::unread', ['as' => 'api.notifications.unread']);
        $routes->get('unread-count', 'API\NotificationController::unreadCount', ['as' => 'api.notifications.unread-count']);
        $routes->get('(:num)', 'API\NotificationController::show/$1', ['as' => 'api.notifications.show']);
        $routes->post('(:num)/read', 'API\NotificationController::markAsRead/$1', ['as' => 'api.notifications.read']);
        $routes->post('read-all', 'API\NotificationController::markAllAsRead', ['as' => 'api.notifications.read-all']);
        $routes->delete('(:num)', 'API\NotificationController::delete/$1', ['as' => 'api.notifications.delete']);
        $routes->delete('read', 'API\NotificationController::deleteAllRead', ['as' => 'api.notifications.delete-read']);
    });

    $routes->group('push', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->post('register', 'API\PushNotificationController::register', ['as' => 'api.push.register']);
        $routes->post('unregister', 'API\PushNotificationController::unregister', ['as' => 'api.push.unregister']);
        $routes->post('test', 'API\PushNotificationController::sendTest', ['as' => 'api.push.test']);
        $routes->get('templates', 'API\PushNotificationController::templates', ['as' => 'api.push.templates']);
    });

    $routes->group('chat', ['filter' => $apiAuthFilters], static function ($routes) {
        $routes->get('rooms', 'API\ChatAPIController::getRooms', ['as' => 'api.chat.rooms']);
        $routes->post('rooms/private', 'API\ChatAPIController::createPrivateRoom', ['as' => 'api.chat.rooms.private']);
        $routes->post('rooms/group', 'API\ChatAPIController::createGroupRoom', ['as' => 'api.chat.rooms.group']);
        $routes->get('rooms/(:num)/messages', 'API\ChatAPIController::getMessages/$1', ['as' => 'api.chat.rooms.messages']);
        $routes->post('rooms/(:num)/messages', 'API\ChatAPIController::sendMessage/$1', ['as' => 'api.chat.rooms.messages.send']);
        $routes->post('rooms/(:num)/read', 'API\ChatAPIController::markAsRead/$1', ['as' => 'api.chat.rooms.read']);
        $routes->get('rooms/(:num)/search', 'API\ChatAPIController::searchMessages/$1', ['as' => 'api.chat.rooms.search']);
        $routes->get('rooms/(:num)/members', 'API\ChatAPIController::getMembers/$1', ['as' => 'api.chat.rooms.members']);
        $routes->post('rooms/(:num)/members', 'API\ChatAPIController::addMember/$1', ['as' => 'api.chat.rooms.members.add']);
        $routes->delete('rooms/(:num)/members/(:num)', 'API\ChatAPIController::removeMember/$1/$2', ['as' => 'api.chat.rooms.members.remove']);
        $routes->put('messages/(:num)', 'API\ChatAPIController::editMessage/$1', ['as' => 'api.chat.messages.edit']);
        $routes->delete('messages/(:num)', 'API\ChatAPIController::deleteMessage/$1', ['as' => 'api.chat.messages.delete']);
        $routes->post('messages/(:num)/reactions', 'API\ChatAPIController::addReaction/$1', ['as' => 'api.chat.messages.reactions']);
        $routes->get('online', 'API\ChatAPIController::getOnlineUsers', ['as' => 'api.chat.online']);
    });

    $routes->get('reports/status/(:segment)', 'API\ReportController::status/$1', ['as' => 'api.reports.status', 'filter' => $apiAuthFilters]);
    $routes->get('reports/download/(:segment)', 'API\ReportController::download/$1', ['as' => 'api.reports.download', 'filter' => $apiAuthFilters]);
    $routes->get('jobs/status/(:segment)', 'API\AsyncJobController::status/$1', ['as' => 'api.jobs.status', 'filter' => $apiAuthFilters]);
    $routes->get('jobs/download/(:segment)', 'API\AsyncJobController::download/$1', ['as' => 'api.jobs.download', 'filter' => $apiAuthFilters]);
});

// SupportCHECK → SupportPONTO: callback para solicitar reenvio de relatório
$routes->group('api/v1/supportcheck', ['filter' => ['cors', 'ratelimit', 'api-json', 'supportcheck-callback']], static function ($routes): void {
    $routes->post('request-report', 'API\SupportCheckCallbackController::requestReport', ['as' => 'api.supportcheck.request-report']);
    $routes->post('request-terms', 'API\SupportCheckCallbackController::requestTerms', ['as' => 'api.supportcheck.request-terms']);
});

