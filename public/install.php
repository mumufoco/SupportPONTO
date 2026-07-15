<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

(new \CodeIgniter\Config\DotEnv(dirname(__DIR__) . '/'))->load();

$installerScript = dirname(__DIR__) . '/tools/installer/install_web.php';

if (! \App\Support\BootstrapEnv::bool('ALLOW_WEB_INSTALLER', false) || ! is_file($installerScript)) {
    http_response_code(404);
    exit;
}

require $installerScript;
