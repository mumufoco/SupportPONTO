<?php

namespace Config;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Modular Route Loading (single source of truth)
 * --------------------------------------------------------------------
 * All application routes are defined under app/Config/Routes/*.php.
 * Files are loaded in deterministic alphabetical order.
 */
$routeFiles = glob(APPPATH . 'Config/Routes/*.php') ?: [];
sort($routeFiles, SORT_STRING);

foreach ($routeFiles as $routeFile) {
    require $routeFile;
}

/*
 * --------------------------------------------------------------------
 * Environment Routing
 * --------------------------------------------------------------------
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
