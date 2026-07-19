<?php

/*
 * --------------------------------------------------------------------
 * Authentication Routes
 * --------------------------------------------------------------------
 */
// Fluxo de aceitação dos termos LGPD no primeiro acesso (requer auth, não requer consent-gate)
$routes->group('consent-gate', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Auth\ConsentGateController::index', ['as' => 'consent-gate']);
    $routes->post('accept-all', 'Auth\ConsentGateController::acceptAll', ['as' => 'consent-gate.accept-all']);
    $routes->get('(:segment)', 'Auth\ConsentGateController::show/$1', ['as' => 'consent-gate.show']);
    $routes->post('(:segment)/accept', 'Auth\ConsentGateController::accept/$1', ['as' => 'consent-gate.accept']);
});

$routes->group('auth', static function ($routes) {
    $routes->get('login', 'Auth\LoginController::index', ['as' => 'login']);
    $routes->post('login', 'Auth\LoginController::authenticate', ['as' => 'login.authenticate']);
    $routes->get('register', 'Auth\RegisterController::index', ['as' => 'register']);
    $routes->post('register', 'Auth\RegisterController::store', ['as' => 'register.store']);
    $routes->get('positions-by-department', 'Auth\RegisterController::getPositionsByDepartment', ['as' => 'register.positions-by-department']);
    $routes->post('logout', 'Auth\LogoutController::logout', ['as' => 'logout']);

    $routes->get('forgot-password', 'Auth\ForgotPasswordController::index', ['as' => 'forgot-password']);
    $routes->post('forgot-password', 'Auth\ForgotPasswordController::sendResetLink', ['as' => 'forgot-password.send']);
    $routes->get('reset-password/(:segment)', 'Auth\ResetPasswordController::index/$1', ['as' => 'reset-password']);
    $routes->post('reset-password', 'Auth\ResetPasswordController::reset', ['as' => 'reset-password.reset']);
    $routes->get('first-access-password', 'Auth\LoginController::firstAccessPassword', ['as' => 'first-access-password']);
    $routes->post('first-access-password', 'Auth\LoginController::updateFirstAccessPassword', ['as' => 'first-access-password.update']);

    $routes->get('2fa/verify', 'Auth\TwoFactorAuthController::verify', ['as' => '2fa.verify']);
    $routes->post('2fa/verify', 'Auth\TwoFactorAuthController::verify', ['as' => '2fa.verify.post']);
    $routes->group('2fa', ['filter' => 'auth'], static function ($routes) {
        $routes->get('setup', 'Auth\TwoFactorAuthController::setup', ['as' => '2fa.setup']);
        $routes->post('enable', 'Auth\TwoFactorAuthController::enable', ['as' => '2fa.enable']);
        $routes->get('backup-codes', 'Auth\TwoFactorAuthController::showBackupCodes', ['as' => '2fa.backup-codes']);
        $routes->get('manage', 'Auth\TwoFactorAuthController::manage', ['as' => '2fa.manage']);
        $routes->post('disable', 'Auth\TwoFactorAuthController::disable', ['as' => '2fa.disable']);
        $routes->post('regenerate-backup-codes', 'Auth\TwoFactorAuthController::regenerateBackupCodes', ['as' => '2fa.regenerate-backup-codes']);
    });
});
