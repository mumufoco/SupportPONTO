<?php

/*
 * --------------------------------------------------------------------
 * QR Code, Biometric and Public Access Points
 * --------------------------------------------------------------------
 */
$routes->group('qrcode', static function ($routes) {
    $routes->get('scanner', 'QRCode\QRCodeController::scanner', ['as' => 'qrcode.scanner']);
    $routes->post('validate', 'QRCode\QRCodeController::validateQRCode', ['as' => 'qrcode.validate']);
});

$routes->group('qrcode', ['filter' => 'auth'], static function ($routes) {
    $routes->get('my-qrcode', 'QRCode\QRCodeController::myQRCode', ['as' => 'qrcode.my']);
    $routes->post('regenerate', 'QRCode\QRCodeController::regenerate', ['as' => 'qrcode.regenerate']);
    $routes->get('download', 'QRCode\QRCodeController::download', ['as' => 'qrcode.download']);
});

$routes->get('kiosk/token', 'Timesheet\TimePunchController::generateKioskToken', ['as' => 'kiosk.token', 'filter' => 'throttle']);
$routes->post('punch-terminal/face', 'Timesheet\TimePunchController::punchByFace', ['as' => 'timesheet.punch.face.kiosk', 'filter' => 'throttle']);
$routes->get('punch-terminal', 'Timesheet\TimePunchController::index', ['as' => 'punch.terminal.public', 'filter' => 'throttle']);
$routes->post('punch-terminal/code', 'Timesheet\TimePunchController::punchByCode', ['as' => 'punch.terminal.code', 'filter' => 'throttle']);
$routes->post('punch-terminal/cpf', 'Timesheet\TimePunchController::punchByCpf', ['as' => 'punch.terminal.cpf', 'filter' => 'throttle']);
$routes->post('punch-terminal/fingerprint', 'Timesheet\TimePunchController::punchByFingerprint', ['as' => 'punch.terminal.fingerprint', 'filter' => 'throttle']);
$routes->get('registro-rapido', 'Timesheet\TimePunchController::quickAccess', ['as' => 'punch.quick-access', 'filter' => 'throttle']);
