<?php

/*
 * --------------------------------------------------------------------
 * Async Jobs Routes
 * --------------------------------------------------------------------
 * Rotas canônicas para polling e download de jobs assíncronos genéricos.
 */

$routes->group('jobs', ['filter' => 'auth'], static function ($routes) {
    $routes->get('status/(:segment)', 'AsyncJobController::status/$1', ['as' => 'jobs.status']);
    $routes->get('download/(:segment)', 'AsyncJobController::download/$1', ['as' => 'jobs.download']);
});
