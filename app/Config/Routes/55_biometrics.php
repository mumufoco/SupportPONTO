<?php

/*
 * --------------------------------------------------------------------
 * Biometric Routes — face/fingerprint isolated from generic access points
 * --------------------------------------------------------------------
 * Pacote 445:
 *  - rotas biométricas com autenticação explícita;
 *  - operações que recebem imagem usam rate limit biométrico;
 *  - gestão biométrica restrita a admin/RH/gestor;
 *  - compliance/LGPD permanece em rotas próprias de compliance.
 * --------------------------------------------------------------------
 */

$routes->get('face-terminal', 'Biometric\FaceRecognitionController::terminal', ['as' => 'biometric.face.terminal', 'filter' => 'throttle']);
$routes->get('acesso/terminal-facial', 'Biometric\FaceRecognitionController::terminal', ['as' => 'acesso.terminal.facial', 'filter' => 'throttle']);

$routes->group('biometric', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Biometric\FaceRecognitionController::index', ['as' => 'biometric.index']);
    $routes->get('enrollment', 'Biometric\FaceRecognitionController::index', ['as' => 'biometric.enrollment']);
    $routes->post('face/enroll', 'Biometric\FaceRecognitionController::enrollFace', ['as' => 'biometric.face.enroll', 'filter' => 'biometric-rate-limit']);
    $routes->post('face/test', 'Biometric\FaceRecognitionController::testRecognition', ['as' => 'biometric.face.test', 'filter' => 'biometric-rate-limit']);
    $routes->delete('face/(:num)', 'Biometric\FaceRecognitionController::deleteTemplate/$1', ['as' => 'biometric.face.delete']);
    $routes->delete('face/user/(:num)/delete', 'Biometric\FaceRecognitionController::deleteUserTemplates/$1', ['as' => 'biometric.face.user.delete', 'filter' => 'role:admin,rh,gestor']);
    $routes->post('consent', 'Biometric\FaceRecognitionController::grantConsent', ['as' => 'biometric.consent.grant']);
    $routes->post('consent/revoke', 'Biometric\FaceRecognitionController::revokeConsent', ['as' => 'biometric.consent.revoke', 'filter' => 'stepup:profile.security']);

    $routes->get('manage', 'Biometric\FaceRecognitionController::manage', ['as' => 'biometric.manage', 'filter' => 'role:admin,rh,gestor']);
    $routes->get('enroll-for/(:num)', 'Biometric\FaceRecognitionController::enrollmentForEmployee/$1', ['as' => 'biometric.face.enroll-for', 'filter' => 'role:admin,rh,gestor']);
    $routes->get('diagnostics', 'Biometric\FaceRecognitionController::diagnostics', ['as' => 'biometric.diagnostics', 'filter' => 'role:admin,rh,gestor,auditor,dpo']);
    $routes->get('fingerprint/enroll/(:num)', 'Biometric\FingerprintController::enroll/$1', ['as' => 'biometric.fingerprint.enroll', 'filter' => 'role:admin,rh,gestor']);
    $routes->post('fingerprint/enroll-from-image', 'Biometric\\FingerprintController::enrollFromImage', ['as' => 'biometric.fingerprint.enroll.image', 'filter' => ['role:admin,rh,gestor', 'biometric-rate-limit']]);
    $routes->get('fingerprint/webauthn-challenge', 'Biometric\\FingerprintController::webauthnChallenge', ['as' => 'biometric.fingerprint.webauthn.challenge', 'filter' => 'role:admin,rh,gestor']);
    $routes->post('fingerprint/enroll', 'Biometric\FingerprintController::store', ['as' => 'biometric.fingerprint.store', 'filter' => ['role:admin,rh,gestor', 'biometric-rate-limit']]);
    $routes->delete('fingerprint/(:num)', 'Biometric\FingerprintController::delete/$1', ['as' => 'biometric.fingerprint.delete', 'filter' => 'role:admin,rh,gestor']);
});

$routes->group('admin/biometric', ['filter' => ['auth', 'role:admin,rh,auditor']], static function ($routes) {
    $routes->get('dashboard', 'Admin\BiometricDashboardController::index', ['as' => 'admin.biometric.dashboard']);
    $routes->get('auth-history', 'Admin\BiometricDashboardController::getAuthHistory', ['as' => 'admin.biometric.auth-history']);
    $routes->get('export-stats', 'Admin\BiometricDashboardController::exportStats', ['as' => 'admin.biometric.export']);
});

$routes->get('acesso/biometria', 'Biometric\FaceRecognitionController::index', ['as' => 'acesso.biometria', 'filter' => 'auth']);
$routes->get('acesso/biometria/gerenciar', 'Biometric\FaceRecognitionController::manage', ['as' => 'acesso.biometria.gerenciar', 'filter' => ['auth', 'role:admin,rh,gestor']]);
$routes->get('acesso/biometria/diagnostico', 'Biometric\FaceRecognitionController::diagnostics', ['as' => 'acesso.biometria.diagnostico', 'filter' => ['auth', 'role:admin,rh,gestor,auditor,dpo']]);

// Self-enrollment page for the authenticated employee
$routes->get('minha-biometria', 'Biometric\FaceRecognitionController::selfEnroll', ['as' => 'biometric.self.enroll', 'filter' => 'auth']);
$routes->get('perfil/biometria', 'Biometric\FaceRecognitionController::selfEnroll', ['as' => 'biometric.perfil', 'filter' => 'auth']);

// Consent term gate — shown before enrollment if employee has not yet accepted
$routes->get('biometric/consent-term/(:num)', 'Biometric\BiometricConsentController::showForEmployee/$1', ['as' => 'biometric.consent.term.show', 'filter' => ['auth', 'role:admin,rh,gestor']]);
$routes->post('biometric/consent-term/(:num)/accept', 'Biometric\BiometricConsentController::acceptForEmployee/$1', ['as' => 'biometric.consent.term.accept', 'filter' => ['auth', 'role:admin,rh,gestor']]);

// Consent terms management & audit list (admin/dpo/auditor)
$routes->get('biometric/consent-terms/manage', 'Biometric\BiometricConsentController::manageTerms', ['as' => 'biometric.consent.terms.manage', 'filter' => ['auth', 'role:admin,dpo']]);
$routes->post('biometric/consent-terms/save', 'Biometric\BiometricConsentController::saveTerm', ['as' => 'biometric.consent.terms.save', 'filter' => ['auth', 'role:admin,dpo']]);
$routes->get('biometric/consent-terms/list', 'Biometric\BiometricConsentController::listConsents', ['as' => 'biometric.consent.terms.list', 'filter' => ['auth', 'role:admin,dpo,auditor,rh']]);
$routes->get('biometric/consent-terms/pdf/(:num)', 'Biometric\BiometricConsentController::downloadConsentPdf/$1', ['as' => 'biometric.consent.terms.pdf', 'filter' => ['auth', 'role:admin,dpo,auditor,rh']]);

// Consent term gate generico por tipo
$routes->get('biometric/consent-term/(:segment)/(:num)', 'Biometric\BiometricConsentController::showForEmployeeByType/$2/$1', ['as' => 'biometric.consent.term.typed', 'filter' => ['auth', 'role:admin,rh,gestor']]);
$routes->post('biometric/consent-term/(:segment)/(:num)/accept', 'Biometric\BiometricConsentController::acceptForEmployeeByType/$2/$1', ['as' => 'biometric.consent.term.typed.accept', 'filter' => ['auth', 'role:admin,rh,gestor']]);

// Alertas de possível fraude na segunda camada de verificação facial (código/CPF/QR/digital)
$routes->get('admin/facial-fraud-alerts', 'Admin\FacialFraudAlertController::index', ['as' => 'admin.facial.fraud.alerts', 'filter' => ['auth', 'role:admin,rh,gestor,dpo']]);
$routes->post('admin/facial-fraud-alerts/(:num)/review', 'Admin\FacialFraudAlertController::review/$1', ['as' => 'admin.facial.fraud.alerts.review', 'filter' => ['auth', 'role:admin,rh,gestor,dpo']]);
