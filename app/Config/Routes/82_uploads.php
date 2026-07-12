<?php

/*
 * --------------------------------------------------------------------
 * Controlled Upload Downloads
 * --------------------------------------------------------------------
 * Arquivos operacionais devem permanecer em writable/uploads e sair apenas por
 * rota autenticada. Chat mantém rota própria por validar participação na sala.
 */
$routes->group('uploads', ['filter' => ['auth']], static function ($routes) {
    $routes->get('secure/(:any)', 'Upload\SecureDownloadController::show/$1', ['as' => 'uploads.secure.download']);
});
