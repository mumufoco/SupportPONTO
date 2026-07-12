<?php

/*
 * --------------------------------------------------------------------
 * Timesheet, Punch and Justifications
 * --------------------------------------------------------------------
 */
$timesheetController = 'Timesheet\TimesheetController';
$timePunchController = 'Timesheet\TimePunchController';
$timesheetAuth = ['filter' => 'auth'];
$timesheetManager = ['filter' => ['auth', 'manager']];
$timesheetThrottle = ['filter' => 'throttle'];

$routes->group('timesheet', $timesheetAuth, static function ($routes) use ($timesheetController, $timePunchController) {
    $routes->get('/', $timesheetController . '::index', ['as' => 'timesheet.index']);
    $routes->get('punch', $timePunchController . '::index', ['as' => 'timesheet.punch']);
    $routes->post('punch', $timePunchController . '::punch', ['as' => 'timesheet.punch.store']);
    $routes->post('punch/code', $timePunchController . '::punchByCode', ['as' => 'timesheet.punch.code']);
    $routes->post('punch/cpf', $timePunchController . '::punchByCpf', ['as' => 'timesheet.punch.cpf']);
    $routes->post('punch/qr', $timePunchController . '::punchByQRCode', ['as' => 'timesheet.punch.qr']);
    $routes->post('punch/face', $timePunchController . '::punchByFace', ['as' => 'timesheet.punch.face']);
    $routes->post('punch/fingerprint', $timePunchController . '::punchByFingerprint', ['as' => 'timesheet.punch.fingerprint']);
    $routes->get('punch/capabilities', $timePunchController . '::capabilities', ['as' => 'timesheet.punch.capabilities']);
    $routes->get('history', $timesheetController . '::history', ['as' => 'timesheet.history']);
    $routes->get('day/(:segment)', $timesheetController . '::day/$1', ['as' => 'timesheet.day']);
    $routes->get('history/(:num)', $timesheetController . '::show/$1', ['as' => 'timesheet.history.show']);
    $routes->get('balance', $timesheetController . '::balance', ['as' => 'timesheet.balance']);
    $routes->get('receipt/(:num)', $timePunchController . '::receipt/$1', ['as' => 'timesheet.receipt']);
    $routes->get('employee/(:num)', $timesheetController . '::employee/$1', ['as' => 'timesheet.employee']);
    $routes->get('export/excel', $timesheetController . '::exportExcel', ['as' => 'timesheet.export.excel']);
    $routes->get('export/pdf', $timesheetController . '::exportPdf', ['as' => 'timesheet.export.pdf']);
    $routes->get('punch-details/(:num)', $timesheetController . '::punchDetails/$1', ['as' => 'timesheet.punch-details']);
    $routes->post('approve/(:num)', $timesheetController . '::approvePunch/$1', ['as' => 'timesheet.approve']);
    $routes->post('reject/(:num)', $timesheetController . '::rejectPunch/$1', ['as' => 'timesheet.reject']);
    $routes->post('filter', $timesheetController . '::filter', ['as' => 'timesheet.filter']);
    $routes->post('get-kpis', $timesheetController . '::getKPIs', ['as' => 'timesheet.get-kpis']);
    $routes->post('get-records', $timesheetController . '::getRecords', ['as' => 'timesheet.get-records']);

    // Protected aliases used by older views and menu entries.
    $routes->get('punch-kiosk', $timePunchController . '::index', ['as' => 'timesheet.punch.kiosk']);
    $routes->get('quick-access', $timePunchController . '::quickAccess', ['as' => 'timesheet.quick-access']);
});

$routes->get('download-receipt/(:num)/(:num)/(:segment)', $timePunchController . '::downloadReceipt/$1/$2/$3', ['as' => 'timesheet.receipt.download', 'filter' => 'auth']);
$routes->get('validate-punch/(:num)', $timePunchController . '::validatePunchByNsr/$1', ['as' => 'timesheet.validate-punch', 'filter' => 'auth']);
$routes->get('validate-punch/public/(:num)', $timePunchController . '::validatePunchByNsrPublic/$1', ['as' => 'timesheet.validate-punch.public', 'filter' => 'throttle']);
$routes->get('/punch', $timePunchController . '::index', ['as' => 'punch', 'filter' => 'auth']);

$routes->group('justifications', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Timesheet\JustificationController::index', ['as' => 'justifications']);
    $routes->get('create', 'Timesheet\JustificationController::create', ['as' => 'justifications.create']);
    $routes->post('/', 'Timesheet\JustificationController::store', ['as' => 'justifications.store']);
    $routes->get('(:num)', 'Timesheet\JustificationController::show/$1', ['as' => 'justifications.show']);
    $routes->post('(:num)/approve', 'Timesheet\JustificationController::approve/$1', ['as' => 'justifications.approve', 'filter' => ['auth', 'manager']]);
    $routes->post('(:num)/reject', 'Timesheet\JustificationController::reject/$1', ['as' => 'justifications.reject', 'filter' => ['auth', 'manager']]);
});

$routes->get('operacao/ponto', $timePunchController . '::index', ['as' => 'operacao.ponto', 'filter' => 'auth']);
$routes->get('operacao/espelho', $timesheetController . '::history', ['as' => 'operacao.espelho', 'filter' => 'auth']);
$routes->get('operacao/banco-horas', $timesheetController . '::balance', ['as' => 'operacao.banco-horas', 'filter' => 'auth']);

$routes->get('operacao/terminal', $timePunchController . '::index', ['as' => 'operacao.terminal', 'filter' => 'auth']);
$routes->get('operacao/terminal-publico', $timePunchController . '::index', ['as' => 'operacao.terminal.publico', 'filter' => 'throttle']);

// Canonical public aliases for kiosk and quick access.
$routes->get('timesheet/punch-terminal', $timePunchController . '::index', ['as' => 'timesheet.punch.terminal.public', 'filter' => 'throttle']);
$routes->get('timesheet/quick-access-public', $timePunchController . '::quickAccess', ['as' => 'timesheet.quick-access.public', 'filter' => 'throttle']);
$routes->get('timesheet/punch-capabilities', $timePunchController . '::capabilities', ['as' => 'timesheet.punch.capabilities.public', 'filter' => 'throttle']);

// ── v1.1.279: Justificativa por falha automática ──────────────────────────────
$routes->group('timesheet/punch', ['filter' => 'auth'], static function ($routes) {
    $routes->get('justify',  'Timesheet\PendingPunchController::index',  ['as' => 'timesheet.punch.justify']);
    $routes->post('justify', 'Timesheet\PendingPunchController::submit', ['as' => 'timesheet.punch.justify.submit']);
});

$routes->group('manager/pending-punches', ['filter' => ['auth', 'manager']], static function ($routes) {
    $routes->get('/', 'Timesheet\PendingPunchController::managerPanel', ['as' => 'manager.pending.punches']);
    $routes->post('(:num)/approve', 'Timesheet\PendingPunchController::approve/$1', ['as' => 'manager.pending.approve']);
    $routes->post('(:num)/reject',  'Timesheet\PendingPunchController::reject/$1',  ['as' => 'manager.pending.reject']);
});


// Relatorio por cliente (unidade de trabalho)
$routes->get('timesheet/por-cliente', 'Timesheet\TimesheetController::byClient', ['as' => 'timesheet.by-client', 'filter' => ['auth', 'manager']]);
$routes->get('relatorio/por-cliente', 'Timesheet\TimesheetController::byClient', ['as' => 'relatorio.por-cliente', 'filter' => ['auth', 'manager']]);
