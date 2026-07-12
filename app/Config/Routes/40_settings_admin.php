<?php

/*
 * --------------------------------------------------------------------
 * Settings and Admin Configuration
 * --------------------------------------------------------------------
 */
$routes->group('settings', ['filter' => ['auth', 'admin']], static function ($routes) {
    $routes->get('/', 'SettingsController::index', ['as' => 'settings']);

    // SupportCHECK — painel e envio manual de relatórios
    $routes->get('supportcheck', 'Settings\SupportCheckSettingsController::index', ['as' => 'settings.supportcheck']);
    $routes->post('supportcheck/send-report', 'Settings\SupportCheckSettingsController::sendReport', ['as' => 'settings.supportcheck.send-report']);
    $routes->post('supportcheck/sync-all', 'Settings\SupportCheckSettingsController::syncAll', ['as' => 'settings.supportcheck.sync-all']);
    $routes->get('supportcheck/ping', 'Settings\SupportCheckSettingsController::ping', ['as' => 'settings.supportcheck.ping']);

    // Configurações sistêmicas
    $routes->post('save-general', 'Settings\SystemSettingsController::saveGeneral', ['as' => 'settings.save-general']);
    $routes->post('logo/save', 'Settings\SystemSettingsController::saveLogoAssets', ['as' => 'settings.logo.save']);
    $routes->post('save-workday', 'Settings\SystemSettingsController::saveWorkday', ['as' => 'settings.save-workday']);
    $routes->post('save-geolocation', 'Settings\SystemSettingsController::saveGeolocation', ['as' => 'settings.save-geolocation']);
    $routes->post('save-notifications', 'Settings\SystemSettingsController::saveNotifications', ['as' => 'settings.save-notifications']);
    $routes->post('save-biometry', 'Settings\SystemSettingsController::saveBiometry', ['as' => 'settings.save-biometry']);
    $routes->post('save-apis', 'Settings\SystemSettingsController::saveApis', ['as' => 'settings.save-apis']);
    $routes->post('save-icp-brasil', 'Settings\SystemSettingsController::saveICPBrasil', ['as' => 'settings.save-icp-brasil']);
    $routes->post('save-lgpd', 'Settings\SystemSettingsController::saveLgpd', ['as' => 'settings.save-lgpd']);
    $routes->post('save-backup', 'Settings\SystemSettingsController::saveBackup', ['as' => 'settings.save-backup']);

    // Cadastros auxiliares
    $routes->get('work-units', 'Settings\CatalogSettingsController::workUnits', ['as' => 'settings.work-units']);
    $routes->get('work-units/create', 'Settings\CatalogSettingsController::createWorkUnit', ['as' => 'settings.work-units.create']);
    $routes->post('work-units/store', 'Settings\CatalogSettingsController::storeWorkUnit', ['as' => 'settings.work-units.store']);
    $routes->get('work-units/(:num)/edit', 'Settings\CatalogSettingsController::editWorkUnit/$1', ['as' => 'settings.work-units.edit']);
    $routes->post('work-units/(:num)/update', 'Settings\CatalogSettingsController::updateWorkUnit/$1', ['as' => 'settings.work-units.update']);
    $routes->post('work-units/(:num)/toggle', 'Settings\CatalogSettingsController::toggleWorkUnit/$1', ['as' => 'settings.work-units.toggle']);

    $routes->get('departments', 'Settings\CatalogSettingsController::departments', ['as' => 'settings.departments']);
    $routes->get('departments/create', 'Settings\CatalogSettingsController::createDepartment', ['as' => 'settings.departments.create']);
    $routes->post('departments/store', 'Settings\CatalogSettingsController::storeDepartment', ['as' => 'settings.departments.store']);
    $routes->get('departments/(:num)/edit', 'Settings\CatalogSettingsController::editDepartment/$1', ['as' => 'settings.departments.edit']);
    $routes->post('departments/(:num)/update', 'Settings\CatalogSettingsController::updateDepartment/$1', ['as' => 'settings.departments.update']);
    $routes->post('departments/(:num)/toggle', 'Settings\CatalogSettingsController::toggleDepartment/$1', ['as' => 'settings.departments.toggle']);

    $routes->get('positions', 'Settings\CatalogSettingsController::positions', ['as' => 'settings.positions']);
    $routes->get('positions/create', 'Settings\CatalogSettingsController::createPosition', ['as' => 'settings.positions.create']);
    $routes->post('positions/store', 'Settings\CatalogSettingsController::storePosition', ['as' => 'settings.positions.store']);
    $routes->get('positions/(:num)/edit', 'Settings\CatalogSettingsController::editPosition/$1', ['as' => 'settings.positions.edit']);
    $routes->post('positions/(:num)/update', 'Settings\CatalogSettingsController::updatePosition/$1', ['as' => 'settings.positions.update']);
    $routes->post('positions/(:num)/toggle', 'Settings\CatalogSettingsController::togglePosition/$1', ['as' => 'settings.positions.toggle']);

    $routes->get('roles', 'Settings\CatalogSettingsController::roles', ['as' => 'settings.roles']);
    $routes->get('roles/create', 'Settings\CatalogSettingsController::createRole', ['as' => 'settings.roles.create']);
    $routes->post('roles/store', 'Settings\CatalogSettingsController::storeRole', ['as' => 'settings.roles.store']);
    $routes->get('roles/(:num)/edit', 'Settings\CatalogSettingsController::editRole/$1', ['as' => 'settings.roles.edit']);
    $routes->post('roles/(:num)/update', 'Settings\CatalogSettingsController::updateRole/$1', ['as' => 'settings.roles.update']);
    $routes->post('roles/(:num)/toggle', 'Settings\CatalogSettingsController::toggleRole/$1', ['as' => 'settings.roles.toggle']);
    $routes->post('roles/(:num)/delete', 'Settings\CatalogSettingsController::deleteRole/$1', ['as' => 'settings.roles.delete']);

    // Operações de jornada/feriados/cercas
    $routes->get('vacations', 'Settings\WorkforceSettingsController::vacations', ['as' => 'settings.vacations']);
    $routes->get('work-shifts', 'Settings\WorkforceSettingsController::workShifts', ['as' => 'settings.work-shifts']);
    $routes->post('work-shifts/store', 'Settings\WorkforceSettingsController::storeWorkShift', ['as' => 'settings.work-shifts.store']);
    $routes->post('work-shifts/(:num)/update', 'Settings\WorkforceSettingsController::updateWorkShift/$1', ['as' => 'settings.work-shifts.update']);
    $routes->post('work-shifts/(:num)/toggle', 'Settings\WorkforceSettingsController::toggleWorkShift/$1', ['as' => 'settings.work-shifts.toggle']);
    $routes->post('work-shifts/(:num)/delete', 'Settings\WorkforceSettingsController::deleteWorkShift/$1', ['as' => 'settings.work-shifts.delete']);

    $routes->get('holidays', 'Settings\WorkforceSettingsController::holidays', ['as' => 'settings.holidays']);
    $routes->get('holidays/json', 'Settings\WorkforceSettingsController::holidaysJson', ['as' => 'settings.holidays.json']);
    $routes->post('holidays/store', 'Settings\WorkforceSettingsController::storeHoliday', ['as' => 'settings.holidays.store']);
    $routes->get('holidays/(:num)/edit', 'Settings\WorkforceSettingsController::editHoliday/$1', ['as' => 'settings.holidays.edit']);
    $routes->post('holidays/(:num)/update', 'Settings\WorkforceSettingsController::updateHoliday/$1', ['as' => 'settings.holidays.update']);
    $routes->post('holidays/(:num)/toggle', 'Settings\WorkforceSettingsController::toggleHoliday/$1', ['as' => 'settings.holidays.toggle']);
    $routes->post('holidays/(:num)/delete', 'Settings\WorkforceSettingsController::deleteHoliday/$1', ['as' => 'settings.holidays.delete']);

    $routes->get('geofences', 'Settings\GeofenceSettingsController::geofences', ['as' => 'settings.geofences']);
    $routes->post('geofences/store', 'Settings\GeofenceSettingsController::storeGeofence', ['as' => 'settings.geofences.store']);
    $routes->post('geofences/(:num)/update', 'Settings\GeofenceSettingsController::updateGeofence/$1', ['as' => 'settings.geofences.update']);
    $routes->post('geofences/(:num)/toggle', 'Settings\GeofenceSettingsController::toggleGeofence/$1', ['as' => 'settings.geofences.toggle']);
    $routes->post('geofences/(:num)/delete', 'Settings\GeofenceSettingsController::deleteGeofence/$1', ['as' => 'settings.geofences.delete']);

    $routes->get('backup/download', 'Settings\SystemMaintenanceController::downloadBackup', ['as' => 'settings.backup.download', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('smtp/test', 'Settings\SystemMaintenanceController::testSmtp', ['as' => 'settings.smtp.test']);
});

$routes->group('admin/settings', ['filter' => ['auth', 'admin']], static function ($routes) {
    $routes->get('/', 'SettingsController::adminIndex', ['as' => 'admin.settings']);
    $routes->post('clear-cache', 'Settings\SystemMaintenanceController::clearCache', ['as' => 'admin.settings.clear-cache']);
    $routes->get('export', 'Settings\SystemMaintenanceController::export', ['as' => 'admin.settings.export', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('import', 'Settings\SystemMaintenanceController::import', ['as' => 'admin.settings.import', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('reset', 'Settings\SystemMaintenanceController::reset', ['as' => 'admin.settings.reset', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->get('test-database', 'Settings\SystemMaintenanceController::testDatabase', ['as' => 'admin.settings.test-database']);
    $routes->get('system-info', 'Settings\SystemMaintenanceController::systemInfo', ['as' => 'admin.settings.system-info']);
    $routes->get('appearance', 'Admin\AppearanceController::index', ['as' => 'admin.settings.appearance']);
    $routes->post('appearance/update', 'Admin\AppearanceController::update', ['as' => 'admin.settings.appearance.update']);
    $routes->post('appearance/upload-logo', 'Admin\AppearanceController::uploadLogo', ['as' => 'admin.settings.appearance.upload-logo']);
    $routes->post('appearance/upload-favicon', 'Admin\AppearanceController::uploadFavicon', ['as' => 'admin.settings.appearance.upload-favicon']);
    $routes->post('appearance/upload-login-background', 'Admin\AppearanceController::uploadLoginBackground', ['as' => 'admin.settings.appearance.upload-login-background']);
    $routes->post('personalization/upload-logo-auth', 'Admin\\PersonalizationController::uploadLogoAuth', ['as' => 'admin.settings.personalization.upload-logo-auth']);
    $routes->post('personalization/upload-logo-sidebar', 'Admin\\PersonalizationController::uploadLogoSidebar', ['as' => 'admin.settings.personalization.upload-logo-sidebar']);
    $routes->post('appearance/reset', 'Admin\AppearanceController::reset', ['as' => 'admin.settings.appearance.reset', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('appearance/preview', 'Admin\AppearanceController::preview', ['as' => 'admin.settings.appearance.preview']);
    $routes->get('authentication', 'Admin\AuthenticationController::index', ['as' => 'admin.settings.authentication']);
    $routes->post('authentication/update', 'Admin\AuthenticationController::update', ['as' => 'admin.settings.authentication.update']);
    $routes->post('authentication/test-2fa', 'Admin\AuthenticationController::test2FA', ['as' => 'admin.settings.authentication.test-2fa']);
    $routes->get('authentication/login-stats', 'Admin\AuthenticationController::loginStats', ['as' => 'admin.settings.authentication.login-stats']);
    $routes->post('authentication/clear-locked', 'Admin\AuthenticationController::clearLockedAccounts', ['as' => 'admin.settings.authentication.clear-locked', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('authentication/test-email', 'Admin\AuthenticationController::testEmail', ['as' => 'admin.settings.authentication.test-email']);
    $routes->post('authentication/reset', 'Admin\AuthenticationController::reset', ['as' => 'admin.settings.authentication.reset', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->get('system', 'Admin\SystemController::index', ['as' => 'admin.settings.system']);
    $routes->post('system/update', 'Admin\SystemController::update', ['as' => 'admin.settings.system.update']);
    $routes->post('system/test-timezone', 'Admin\SystemController::testTimezone', ['as' => 'admin.settings.system.test-timezone']);
    $routes->post('system/reset', 'Admin\SystemController::reset', ['as' => 'admin.settings.system.reset', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->get('security', 'Admin\SecurityController::index', ['as' => 'admin.settings.security']);
    $routes->post('security/update', 'Admin\SecurityController::update', ['as' => 'admin.settings.security.update']);
    $routes->post('security/test-password', 'Admin\SecurityController::testPassword', ['as' => 'admin.settings.security.test-password']);
    $routes->get('security/audit-logs', 'Admin\SecurityController::auditLogs', ['as' => 'admin.settings.security.audit-logs']);
    $routes->post('security/backup', 'Admin\SecurityController::backup', ['as' => 'admin.settings.security.backup', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('security/reset', 'Admin\SecurityController::reset', ['as' => 'admin.settings.security.reset', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->get('certificate', 'Admin\CertificateController::index', ['as' => 'admin.settings.certificate']);
    $routes->post('certificate/update', 'Admin\CertificateController::update', ['as' => 'admin.settings.certificate.update', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('certificate/test', 'Admin\CertificateController::test', ['as' => 'admin.settings.certificate.test', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('certificate/remove', 'Admin\CertificateController::remove', ['as' => 'admin.settings.certificate.remove', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('certificate/reset', 'Admin\CertificateController::reset', ['as' => 'admin.settings.certificate.reset', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->get('clock-adjustments', 'Admin\ClockAdjustmentController::index', ['as' => 'admin.clock-adjustments']);
    $routes->post('clock-adjustments/store', 'Admin\ClockAdjustmentController::store', ['as' => 'admin.clock-adjustments.store']);
    $routes->get('company-record-events', 'Admin\CompanyRecordEventController::index', ['as' => 'admin.company-record-events']);
    $routes->post('company-record-events/store', 'Admin\CompanyRecordEventController::store', ['as' => 'admin.company-record-events.store']);
    $routes->get('information', 'Admin\InformationController::index', ['as' => 'admin.settings.information']);
    $routes->post('information/update', 'Admin\InformationController::update', ['as' => 'admin.settings.information.update']);
    $routes->get('personalization', 'Admin\PersonalizationController::index', ['as' => 'admin.settings.personalization']);
    $routes->post('personalization/update', 'Admin\PersonalizationController::update', ['as' => 'admin.settings.personalization.update']);
    $routes->get('email', 'Admin\EmailController::index', ['as' => 'admin.settings.email']);
    $routes->post('email/update', 'Admin\EmailController::update', ['as' => 'admin.settings.email.update']);
    $routes->get('email-templates', 'Admin\EmailTemplatesController::index', ['as' => 'admin.settings.email-templates']);
    $routes->post('email-templates/update', 'Admin\EmailTemplatesController::update', ['as' => 'admin.settings.email-templates.update']);
    $routes->post('email-templates/preview', 'Admin\EmailTemplatesController::preview', ['as' => 'admin.settings.email-templates.preview']);
    $routes->get('integrations', 'Admin\IntegrationsController::index', ['as' => 'admin.settings.integrations']);
    $routes->post('integrations/update', 'Admin\IntegrationsController::update', ['as' => 'admin.settings.integrations.update']);
    $routes->get('backup', 'Admin\BackupController::index', ['as' => 'admin.settings.backup']);
    $routes->post('backup/update', 'Admin\BackupController::update', ['as' => 'admin.settings.backup.update']);
    $routes->get('pwa', 'Admin\PwaController::index', ['as' => 'admin.settings.pwa']);
    $routes->post('pwa/update', 'Admin\PwaController::update', ['as' => 'admin.settings.pwa.update']);
    $routes->post('pwa/upload-image', 'Admin\PwaController::uploadPwaImage', ['as' => 'admin.settings.pwa.upload-image']);
    $routes->get('two-factor', 'Admin\TwoFactorController::index', ['as' => 'admin.settings.two-factor']);
    $routes->post('two-factor/update', 'Admin\TwoFactorController::update', ['as' => 'admin.settings.two-factor.update']);
    $routes->post('two-factor/generate-qr', 'Admin\TwoFactorController::generateQr', ['as' => 'admin.settings.two-factor.generate-qr']);
    $routes->post('two-factor/verify', 'Admin\TwoFactorController::verify', ['as' => 'admin.settings.two-factor.verify']);
});

$routes->group('geofence', ['filter' => ['auth', 'admin']], static function ($routes) {
    // Compatibilidade legada: redirecionar GETs para a trilha canônica plural.
    $routes->get('/', 'Compatibility\LegacyRouteRedirectController::geofencesIndex', ['as' => 'geofence']);
    $routes->get('create', 'Compatibility\LegacyRouteRedirectController::geofencesCreate', ['as' => 'geofence.create']);
    $routes->get('map', 'Compatibility\LegacyRouteRedirectController::geofencesMap', ['as' => 'geofence.map']);
    $routes->get('(:num)', 'Compatibility\LegacyRouteRedirectController::geofencesShow/$1', ['as' => 'geofence.show']);
    $routes->get('(:num)/edit', 'Compatibility\LegacyRouteRedirectController::geofencesEdit/$1', ['as' => 'geofence.edit']);
    $routes->post('store', 'Geolocation\GeofenceController::store', ['as' => 'geofence.store']);
    $routes->post('(:num)/update', 'Geolocation\GeofenceController::update/$1', ['as' => 'geofence.update.post']);
    $routes->put('(:num)', 'Geolocation\GeofenceController::update/$1', ['as' => 'geofence.update']);
    $routes->post('(:num)/toggle', 'Geolocation\GeofenceController::toggle/$1', ['as' => 'geofence.toggle']);
    $routes->delete('(:num)', 'Geolocation\GeofenceController::delete/$1', ['as' => 'geofence.delete']);
    $routes->post('(:num)/delete', 'Geolocation\GeofenceController::delete/$1', ['as' => 'geofence.delete.post']);
    $routes->post('test', 'Geolocation\GeofenceController::test', ['as' => 'geofence.test']);
    $routes->get('json', 'Geolocation\GeofenceController::json', ['as' => 'geofence.json']);
});

$routes->group('geofences', ['filter' => ['auth', 'admin']], static function ($routes) {
    $routes->get('/', 'Geolocation\GeofenceController::index', ['as' => 'geofences']);
    $routes->get('create', 'Geolocation\GeofenceController::create', ['as' => 'geofences.create']);
    $routes->get('map', 'Geolocation\GeofenceController::map', ['as' => 'geofences.map']);
    $routes->get('(:num)', 'Geolocation\GeofenceController::show/$1', ['as' => 'geofences.show']);
    $routes->get('(:num)/edit', 'Geolocation\GeofenceController::edit/$1', ['as' => 'geofences.edit']);
    $routes->post('/', 'Geolocation\GeofenceController::store', ['as' => 'geofences.store']);
    $routes->post('store', 'Geolocation\GeofenceController::store', ['as' => 'geofences.store.compat']);
    $routes->post('(:num)/update', 'Geolocation\GeofenceController::update/$1', ['as' => 'geofences.update']);
    $routes->post('(:num)/toggle', 'Geolocation\GeofenceController::toggle/$1', ['as' => 'geofences.toggle']);
    $routes->post('(:num)/delete', 'Geolocation\GeofenceController::delete/$1', ['as' => 'geofences.delete']);
    $routes->get('json', 'Geolocation\GeofenceController::json', ['as' => 'geofences.json']);
    $routes->post('test', 'Geolocation\GeofenceController::test', ['as' => 'geofences.test']);
});

$routes->get('configuracoes/geral', 'Compatibility\LegacyRouteRedirectController::settingsCenter', ['as' => 'configuracoes.geral', 'filter' => ['auth', 'admin']]);
$routes->get('configuracoes/aparencia', 'Compatibility\LegacyRouteRedirectController::settingsAppearance', ['as' => 'configuracoes.aparencia', 'filter' => ['auth', 'admin']]);
$routes->get('configuracoes/seguranca', 'Compatibility\LegacyRouteRedirectController::settingsSecurity', ['as' => 'configuracoes.seguranca', 'filter' => ['auth', 'admin']]);

$routes->get('configuracoes/sistema', 'Compatibility\LegacyRouteRedirectController::settingsSystem', ['as' => 'configuracoes.sistema', 'filter' => ['auth', 'admin']]);
$routes->get('configuracoes/autenticacao', 'Compatibility\LegacyRouteRedirectController::settingsAuthentication', ['as' => 'configuracoes.autenticacao', 'filter' => ['auth', 'admin']]);

$routes->get('configuracoes/aparencia-visual', 'Compatibility\LegacyRouteRedirectController::settingsAppearance', ['as' => 'configuracoes.aparencia.visual', 'filter' => ['auth', 'admin']]);
$routes->get('configuracoes/centro', 'Compatibility\LegacyRouteRedirectController::settingsCenter', ['as' => 'configuracoes.centro', 'filter' => ['auth', 'admin']]);

// Sprint D - preferir rotas canônicas de settings/admin; aliases devem ser apenas compatíveis e sem duplicar regra de negócio

// Sprint 5 - settings: manter centro de configurações com rotas canônicas, saves consistentes e partials corretos.

// Bloco 2 - canônico: settings seguem a trilha principal de App\\Controllers\\SettingsController; wrapper admin é apenas compatível.

// Bloco 3 - settings devem usar trilha canônica; wrapper admin serve apenas compatibilidade pontual.

// Bloco 4 - settings: validar forms, saves e trilha administrativa canônica antes dos testes reais.

// Templates de Termos de Consentimento Biométrico
$routes->get('settings/consent-terms', 'Biometric\BiometricConsentController::manageTerms', ['as' => 'settings.consent-terms', 'filter' => ['auth', 'admin']]);
$routes->post('settings/consent-terms/save', 'Biometric\BiometricConsentController::saveTerm', ['as' => 'settings.consent-terms.save', 'filter' => ['auth', 'admin']]);
