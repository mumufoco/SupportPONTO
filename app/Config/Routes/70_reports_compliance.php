<?php

/*
 * --------------------------------------------------------------------
 * Reports, Audit, LGPD and Warning Management
 * --------------------------------------------------------------------
 */
$routes->group('reports', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Report\ReportController::index', ['as' => 'reports']);
    $routes->get('employee/(:num)', 'Report\ReportController::index/$1', ['as' => 'reports.employee']);
    $routes->get('timesheet', 'Report\ReportController::timesheet', ['as' => 'reports.timesheet']);
    $routes->get('timesheet/export/pdf', 'Report\ReportController::timesheetExportPdf', ['as' => 'reports.timesheet.export.pdf']);
    $routes->get('timesheet/export/excel', 'Report\ReportController::timesheetExportExcel', ['as' => 'reports.timesheet.export.excel']);
    $routes->get('timesheet/export/csv', 'Report\ReportController::timesheetExportCsv', ['as' => 'reports.timesheet.export.csv']);
    $routes->get('timesheet/(:segment)', 'Report\ReportController::timesheet/$1', ['as' => 'reports.timesheet.month']); // backward compatibility
    $routes->get('attendance', 'Report\ReportController::attendance', ['as' => 'reports.attendance']);
    $routes->get('attendance/export/pdf', 'Report\ReportController::attendanceExportPdf', ['as' => 'reports.attendance.export.pdf']);
    $routes->get('attendance/export/excel', 'Report\ReportController::attendanceExportExcel', ['as' => 'reports.attendance.export.excel']);
    $routes->get('attendance/export/csv', 'Report\ReportController::attendanceExportCsv', ['as' => 'reports.attendance.export.csv']);
    $routes->get('late-arrivals', 'Report\ReportController::lateArrivals', ['as' => 'reports.late_arrivals']);
    $routes->get('justifications', 'Report\ReportController::justifications', ['as' => 'reports.justifications']);
    $routes->get('justifications/export/pdf', 'Report\ReportController::justificationsExportPdf', ['as' => 'reports.justifications.export.pdf']);
    $routes->get('justifications/export/excel', 'Report\ReportController::justificationsExportExcel', ['as' => 'reports.justifications.export.excel']);
    $routes->get('justifications/export/csv', 'Report\ReportController::justificationsExportCsv', ['as' => 'reports.justifications.export.csv']);
    $routes->post('generate', 'Report\ReportController::generate', ['as' => 'reports.generate']);
    $routes->get('download/(:any)', 'Report\ReportController::download/$1', ['as' => 'reports.download']);
    $routes->get('preview/(:any)', 'Report\ReportController::preview/$1', ['as' => 'reports.preview']);
    $routes->get('status/(:any)', 'Report\ReportController::status/$1', ['as' => 'reports.status']);
    $routes->post('export-afd', 'Report\ReportController::exportAFD', ['as' => 'reports.afd']);
});

$routes->group('warnings', ['filter' => 'auth'], static function ($routes) {
    // Employee + management readable endpoints
    $routes->get('/', 'Warning\WarningController::index', ['as' => 'warnings']);
    $routes->get('dashboard', 'Warning\WarningController::dashboard', ['as' => 'warnings.dashboard.self']);
    $routes->get('dashboard/(:num)', 'Warning\WarningController::dashboard/$1', ['as' => 'warnings.dashboard']);
    $routes->get('(:num)', 'Warning\WarningController::show/$1', ['as' => 'warnings.show']);
    $routes->get('(:num)/sign', 'Warning\WarningController::signForm/$1', ['as' => 'warnings.sign.form']);
    $routes->get('create', 'Warning\WarningController::create', ['as' => 'warnings.create']);
    $routes->post('/', 'Warning\WarningController::store', ['as' => 'warnings.store']);
    $routes->post('store', 'Warning\WarningController::store'); // backward compatibility
    $routes->post('(:num)/sign', 'Warning\WarningController::sign/$1', ['as' => 'warnings.sign']);
    $routes->post('(:num)/send-sms', 'Warning\WarningController::sendSMSCode/$1', ['as' => 'warnings.sign.sms']);
    $routes->get('(:num)/download', 'Warning\WarningController::downloadPDF/$1', ['as' => 'warnings.download']);
});

$routes->group('warnings', ['filter' => ['auth', 'manager']], static function ($routes) {
    // Manager/Admin workflow endpoints
    $routes->get('(:num)/add-witness', 'Warning\WarningController::addWitnessForm/$1', ['as' => 'warnings.witness.form']);
    $routes->post('(:num)/add-witness', 'Warning\WarningController::addWitness/$1', ['as' => 'warnings.witness.store']);
    $routes->post('(:num)/refuse-signature', 'Warning\WarningController::refuseSignature/$1', ['as' => 'warnings.refuse.signature']);
    $routes->post('(:num)/refuse', 'Warning\WarningController::refuseSignature/$1', ['as' => 'warnings.refuse']); // backward compatibility
    $routes->delete('(:num)', 'Warning\WarningController::delete/$1', ['as' => 'warnings.delete']);
});

$routes->group('lgpd', ['filter' => 'auth'], static function ($routes) {
    $routes->get('consents', 'LGPDController::consents', ['as' => 'lgpd.consents']);
    $routes->post('consent/grant', 'LGPDController::grantConsent', ['as' => 'lgpd.consent.grant']);
    $routes->post('consent/revoke', 'LGPDController::revokeConsent', ['as' => 'lgpd.consent.revoke']);
    $routes->get('export', 'LGPDController::exportData', ['as' => 'lgpd.export']);
    $routes->post('export/request', 'LGPDController::requestExport', ['as' => 'lgpd.export.request']);
    $routes->get('export/download/(:segment)', 'LGPDController::downloadExport/$1', ['as' => 'lgpd.export.download']);
    $routes->get('inventory', 'LGPDController::inventory', ['as' => 'lgpd.inventory']);
    $routes->get('retention', 'LGPDController::retentionPolicies', ['as' => 'lgpd.retention']);
    $routes->post('privacy-request', 'LGPDController::requestPrivacyAction', ['as' => 'lgpd.privacy.request']);

    // Compatibilidade com views legadas.
    $routes->post('grant-consent', 'LGPDController::grantConsent');
    $routes->post('revoke-consent', 'LGPDController::revokeConsent');
    $routes->post('request-export', 'LGPDController::requestExport');
    $routes->get('download-export/(:segment)', 'LGPDController::downloadExport/$1');
});

$routes->group('lgpd/admin', ['filter' => ['auth', 'role:admin,dpo']], static function ($routes) {
    $routes->get('anpd', 'LGPDController::anpdReport', ['as' => 'lgpd.admin.anpd']);
    $routes->get('anpd/export', 'LGPDController::exportANPDReport', ['as' => 'lgpd.admin.anpd.export']);
    $routes->post('employee/(:num)/deactivate', 'LGPDController::adminDeactivateEmployee/$1', ['as' => 'lgpd.admin.employee.deactivate']);
    $routes->post('employee/(:num)/anonymize', 'LGPDController::adminAnonymizeEmployee/$1', ['as' => 'lgpd.admin.employee.anonymize']);
    $routes->post('employee/(:num)/purge-biometrics', 'LGPDController::purgeBiometrics/$1', ['as' => 'lgpd.admin.employee.purge_biometrics']);
    $routes->post('employee/(:num)/resolve-request', 'LGPDController::resolveSubjectRequest/$1', ['as' => 'lgpd.admin.employee.resolve_request']);
});

$routes->group('audit', ['filter' => ['auth', 'role:admin,dpo,auditor,gestor']], static function ($routes) {
    $routes->get('/', 'AuditController::index', ['as' => 'audit']);
    $routes->post('data', 'AuditController::getData', ['as' => 'audit.data']);
    $routes->get('(:num)', 'AuditController::show/$1', ['as' => 'audit.show']);
    $routes->get('details/(:num)', 'AuditController::details/$1', ['as' => 'audit.details']);
    $routes->get('export', 'AuditController::export', ['as' => 'audit.export', 'filter' => ['auth', 'role:admin,dpo,auditor']]);
    $routes->post('clear', 'AuditController::clear', ['as' => 'audit.clear', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->get('afd', 'AuditController::generateAFD', ['as' => 'audit.afd']);
    $routes->get('compliance', 'AuditController::compliance', ['as' => 'audit.compliance']);
});

$routes->get('operacao/justificativas', 'Timesheet\JustificationController::index', ['as' => 'operacao.justificativas', 'filter' => 'auth']);
$routes->get('gestao/relatorios', 'Report\ReportController::index', ['as' => 'gestao.relatorios', 'filter' => ['auth', 'manager']]);
$routes->get('gestao/advertencias', 'Warning\WarningController::index', ['as' => 'gestao.advertencias', 'filter' => 'auth']);

$routes->get('compliance/advertencias', 'Warning\WarningController::index', ['as' => 'compliance.advertencias', 'filter' => ['auth', 'manager']]);
$routes->get('compliance/advertencias/nova', 'Warning\WarningController::create', ['as' => 'compliance.advertencias.nova', 'filter' => ['auth', 'manager']]);
$routes->get('compliance/relatorios', 'Report\ReportController::index', ['as' => 'compliance.relatorios', 'filter' => ['auth', 'role:admin,dpo,auditor']]);
