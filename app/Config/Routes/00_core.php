<?php

/*
 * --------------------------------------------------------------------
 * Core, Health and Utility Routes
 * --------------------------------------------------------------------
 */
$routes->get('/', 'Home::index');

$routes->get('healthz', 'HealthController::healthz', ['as' => 'healthz']);
$routes->get('healthz/ready', 'HealthController::readiness', ['as' => 'healthz.ready']);
$routes->get('healthz/detailed', 'HealthController::detailed', ['as' => 'healthz.detailed']);

// SEC-02 FIX: controller de debug removido da árvore ativa — nunca expor em produção.
// Para debug local use um stub temporário fora da árvore principal.

$routes->group('health', static function ($routes) {
    $routes->get('/', 'HealthController::index', ['as' => 'health']);
    $routes->get('liveness', 'HealthController::liveness', ['as' => 'health.liveness']);
    $routes->get('readiness', 'HealthController::readiness', ['as' => 'health.readiness']);
    $routes->get('detailed', 'HealthController::detailed', ['as' => 'health.detailed']);
});

$routes->group('organizational', ['filter' => ['auth', 'admin']], static function ($routes) {
    $routes->get('/', 'OrganizationalController::index', ['as' => 'organizational.index']);
});
