<?php

/*
 * --------------------------------------------------------------------
 * Employees, Schedules and Workforce Management
 * --------------------------------------------------------------------
 */
$routes->group('employees', ['filter' => ['auth', 'manager']], static function ($routes) {
    $routes->get('/', 'Employees\EmployeeController::index', ['as' => 'employees']);
    $routes->get('create', 'Employees\EmployeeController::create', ['as' => 'employees.create']);
    $routes->post('/', 'Employees\EmployeeController::store', ['as' => 'employees.store']);
    $routes->get('(:num)', 'Employees\EmployeeController::show/$1', ['as' => 'employees.show']);
    $routes->get('(:num)/edit', 'Employees\EmployeeController::edit/$1', ['as' => 'employees.edit']);
    $routes->put('(:num)', 'Employees\EmployeeController::update/$1', ['as' => 'employees.update']);
    $routes->post('(:num)/export', 'Employees\EmployeeController::exportData/$1', ['as' => 'employees.export']);
    $routes->get('(:num)/qrcode', 'Employees\EmployeeController::qrcode/$1', ['as' => 'employees.qrcode']);
    $routes->get('(:num)/qrcode/download', 'Employees\EmployeeController::qrcodeDownload/$1', ['as' => 'employees.qrcode.download']);
    $routes->get('(:num)/qrcode/print', 'Employees\EmployeeController::qrcodePrint/$1', ['as' => 'employees.qrcode.print']);
    $routes->get('get-positions-by-department', 'Employees\EmployeeController::getPositionsByDepartment', ['as' => 'employees.get-positions-by-department']);
});

$routes->group('employees/dependents', ['filter' => ['auth', 'manager']], static function ($routes) {
    $routes->get('/', 'Employees\EmployeeDependentController::index', ['as' => 'employees.dependents']);
    $routes->get('create', 'Employees\EmployeeDependentController::create', ['as' => 'employees.dependents.create']);
    $routes->post('/', 'Employees\EmployeeDependentController::store', ['as' => 'employees.dependents.store']);
    $routes->get('(:num)/edit', 'Employees\EmployeeDependentController::edit/$1', ['as' => 'employees.dependents.edit']);
    $routes->put('(:num)', 'Employees\EmployeeDependentController::update/$1', ['as' => 'employees.dependents.update']);
    $routes->post('(:num)/delete', 'Employees\EmployeeDependentController::delete/$1', ['as' => 'employees.dependents.delete']);
});

$routes->group('employees', ['filter' => ['auth', 'admin']], static function ($routes) {
    $routes->get('pending', 'Employees\EmployeeController::pendingRegistrations', ['as' => 'employees.pending']);
    $routes->post('pending/(:num)/approve', 'Employees\EmployeeController::approveRegistration/$1', ['as' => 'employees.pending.approve', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('pending/(:num)/reject', 'Employees\EmployeeController::rejectRegistration/$1', ['as' => 'employees.pending.reject', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->delete('(:num)', 'Employees\EmployeeController::delete/$1', ['as' => 'employees.delete', 'filter' => ['auth', 'admin', 'stepup:back']]);
    $routes->post('(:num)/activate', 'Employees\EmployeeController::activate/$1', ['as' => 'employees.activate', 'filter' => ['auth', 'admin']]);
    $routes->post('(:num)/toggle-active', 'Employees\EmployeeController::toggleActive/$1', ['as' => 'employees.toggle-active', 'filter' => ['auth', 'admin']]);
    $routes->post('(:num)/deactivate', 'Employees\EmployeeController::deactivate/$1', ['as' => 'employees.deactivate', 'filter' => ['auth', 'admin']]);
});

$routes->group('shifts', ['filter' => ['auth', 'manager']], static function ($routes) {
    $routes->get('/', 'Shift\ShiftController::index', ['as' => 'shifts']);
    $routes->get('create', 'Shift\ShiftController::create', ['as' => 'shifts.create']);
    $routes->post('store', 'Shift\ShiftController::store', ['as' => 'shifts.store']);
    $routes->get('(:num)', 'Shift\ShiftController::show/$1', ['as' => 'shifts.show']);
    $routes->get('(:num)/edit', 'Shift\ShiftController::edit/$1', ['as' => 'shifts.edit']);
    $routes->post('(:num)/update', 'Shift\ShiftController::update/$1', ['as' => 'shifts.update']);
    $routes->delete('(:num)', 'Shift\ShiftController::delete/$1', ['as' => 'shifts.delete']);
    $routes->post('(:num)/clone', 'Shift\ShiftController::clone/$1', ['as' => 'shifts.clone']);
    $routes->post('(:num)/toggle-active', 'Shift\ShiftController::toggleActive/$1', ['as' => 'shifts.toggle-active']);
    $routes->get('statistics', 'Shift\ShiftController::statistics', ['as' => 'shifts.statistics']);
});

$routes->group('schedules', ['filter' => ['auth', 'manager']], static function ($routes) {
    $routes->get('/', 'Shift\ScheduleController::index', ['as' => 'schedules']);
    $routes->get('create', 'Shift\ScheduleController::create', ['as' => 'schedules.create']);
    $routes->post('store', 'Shift\ScheduleController::store', ['as' => 'schedules.store']);
    $routes->get('(:num)/edit', 'Shift\ScheduleController::edit/$1', ['as' => 'schedules.edit']);
    $routes->post('(:num)/update', 'Shift\ScheduleController::update/$1', ['as' => 'schedules.update']);
    $routes->delete('(:num)', 'Shift\ScheduleController::delete/$1', ['as' => 'schedules.delete']);
    $routes->get('bulk-assign', 'Shift\ScheduleController::bulkAssignForm', ['as' => 'schedules.bulk-assign']);
    $routes->post('bulk-assign', 'Shift\ScheduleController::bulkAssign', ['as' => 'schedules.bulk-assign.store']);
    $routes->get('export', 'Shift\ScheduleController::export', ['as' => 'schedules.export']);
});

$routes->group('my-schedules', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'Shift\ScheduleController::mySchedules', ['as' => 'my-schedules']);
});

$routes->get('cadastros/colaboradores', 'Employees\EmployeeController::index', ['as' => 'cadastros.colaboradores', 'filter' => ['auth', 'manager']]);
$routes->get('cadastros/colaboradores/novo', 'Employees\EmployeeController::create', ['as' => 'cadastros.colaboradores.novo', 'filter' => ['auth', 'manager']]);

// Employee invite system
$routes->post('employees/invite', 'Employees\EmployeeInviteController::create', ['as' => 'employees.invite.create', 'filter' => ['auth', 'manager']]);
$routes->get('convite/(:segment)',  'Employees\EmployeeInviteController::show/$1',  ['as' => 'employees.invite.show',   'filter' => 'throttle']);
$routes->post('convite/(:segment)', 'Employees\EmployeeInviteController::store/$1', ['as' => 'employees.invite.store',  'filter' => 'throttle']);

$routes->get('admin/employees/overview', 'Employees\EmployeeController::adminOverview', ['as' => 'admin.employees.overview', 'filter' => ['auth', 'admin']]);
$routes->get('cadastros/controle-colaboradores', 'Employees\EmployeeController::adminOverview', ['as' => 'cadastros.controle.colaboradores', 'filter' => ['auth', 'admin']]);

$routes->post('employees/(:num)/photo', 'Employees\EmployeeController::uploadPhoto/$1', ['as' => 'employees.photo.upload', 'filter' => 'auth']);
// MED-09 (auditoria): foto servida por rota autenticada (self/admin/rh/gestor do mesmo
// departamento), não mais por arquivo estático em URL pública previsível.
$routes->get('employees/(:num)/photo', 'Employees\EmployeeController::photo/$1', ['as' => 'employees.photo.view', 'filter' => 'auth']);

// Employee change requests
$routes->get('employees/change-request/create/(:num)',  'Employees\EmployeeChangeRequestController::create/$1',  ['as' => 'employees.change-request.create',  'filter' => 'auth']);
$routes->post('employees/change-request/store',         'Employees\EmployeeChangeRequestController::store',      ['as' => 'employees.change-request.store',   'filter' => 'auth']);
$routes->get('employees/change-request/status/(:num)',  'Employees\EmployeeChangeRequestController::status/$1',  ['as' => 'employees.change-request.status',  'filter' => 'auth']);
$routes->get('admin/change-requests',                   'Employees\EmployeeChangeRequestController::adminIndex', ['as' => 'admin.change-requests',             'filter' => ['auth', 'admin']]);
$routes->post('admin/change-requests/(:num)/approve',   'Employees\EmployeeChangeRequestController::approve/$1', ['as' => 'admin.change-requests.approve',     'filter' => ['auth', 'admin']]);
$routes->post('admin/change-requests/(:num)/reject',    'Employees\EmployeeChangeRequestController::reject/$1',  ['as' => 'admin.change-requests.reject',      'filter' => ['auth', 'admin']]);
