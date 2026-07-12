<?php

/**
 * Sistema de Ponto Eletrônico Brasileiro
 *
 * Entry point for the application
 *
 * @package    PontoEletronico
 * @author     Mumufoco Team
 * @copyright  2024 Mumufoco
 * @license    MIT
 * @link       https://github.com/mumufoco/ponto-eletronico
 */

use App\Support\SensitiveDataSanitizer;
use CodeIgniter\Boot;
use Config\Paths;

function supportponto_bootstrap_log(string $event, array $context = []): void
{
    $dir = dirname(__DIR__) . '/writable/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $payload = [
        'event' => 'bootstrap.' . $event,
        'timestamp' => date('c'),
        'environment' => $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'unknown',
        'context' => $context,
    ];

    @error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $dir . '/bootstrap-' . date('Y-m-d') . '.log');
}

function supportponto_bootstrap_fail(string $title, string $message, int $status = 503): never
{
    supportponto_bootstrap_log('fatal', [
        'title' => $title,
        'status' => $status,
        'message' => $message,
        'php_version' => PHP_VERSION,
        'sapi' => PHP_SAPI,
    ]);

    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</title><style>body{font-family:Arial,sans-serif;background:#f6f8f7;color:#2f3a2f;margin:0;padding:32px}main{max-width:760px;margin:0 auto;background:#fff;border:1px solid #d9e2d8;border-radius:14px;padding:28px;box-shadow:0 8px 24px rgba(0,0,0,.06)}h1{margin-top:0;font-size:1.6rem}code{background:#eef3ee;padding:2px 6px;border-radius:6px}pre{white-space:pre-wrap;background:#f6f8f7;padding:12px;border-radius:10px;border:1px solid #d9e2d8}</style></head><body><main><h1>'
        . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
        . '</h1><p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p></main></body></html>';
    exit(1);
}

if (function_exists('ini_set')) {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    @ini_set('html_errors', '0');
    @ini_set('log_errors', '1');
}

/*
 *---------------------------------------------------------------
 * MAINTENANCE MODE
 *---------------------------------------------------------------
 * Checked before Composer autoload / DB config so it still works
 * mid-deploy (new code checked out, migrations not yet applied).
 * Toggle by creating/removing writable/maintenance.lock (not versioned).
 */
$maintenanceLock = dirname(__DIR__) . '/writable/maintenance.lock';
if (is_file($maintenanceLock)) {
    http_response_code(503);
    header('Retry-After: 120');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Manutenção programada</title><style>body{font-family:Arial,sans-serif;background:#f6f8f7;color:#2f3a2f;margin:0;padding:32px}main{max-width:560px;margin:80px auto;background:#fff;border:1px solid #d9e2d8;border-radius:14px;padding:28px;box-shadow:0 8px 24px rgba(0,0,0,.06);text-align:center}h1{margin-top:0;font-size:1.4rem}</style></head><body><main><h1>Manutenção programada</h1><p>O SupportPONTO está em manutenção rápida. Tente novamente em alguns minutos.</p></main></body></html>';
    exit;
}

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.3'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    supportponto_bootstrap_log('php_version_too_low', ['required' => $minPhpVersion, 'current' => PHP_VERSION]);
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}

/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

if (!is_dir($paths->systemDirectory) || !is_file(rtrim($paths->systemDirectory, '/\\') . '/Boot.php')) {
    supportponto_bootstrap_fail(
        'Framework não encontrado',
        'A pasta do framework CodeIgniter não foi localizada. Instale as dependências com Composer antes de acessar a aplicação.\n\nComandos sugeridos:\n1. composer install --no-dev --optimize-autoloader\n2. php spark install:doctor\n3. Verifique se a pasta vendor/ foi publicada no servidor.'
    );
}

// LOAD COMPOSER AUTOLOADER
// This must be loaded before Boot.php to ensure all classes are available
if (is_file(FCPATH . '../vendor/autoload.php')) {
    require FCPATH . '../vendor/autoload.php';
} else {
    supportponto_bootstrap_fail(
        'Dependências ausentes',
        'O arquivo vendor/autoload.php não foi encontrado. A aplicação não pode iniciar sem as dependências instaladas.\n\nExecute composer install no diretório do projeto e publique a pasta vendor/ no servidor antes de acessar a aplicação ou o instalador.'
    );
}

$environment = $_ENV['CI_ENVIRONMENT'] ?? $_SERVER['CI_ENVIRONMENT'] ?? getenv('CI_ENVIRONMENT') ?: 'production';

if ($environment === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
} else {
    error_reporting(E_ALL);
}

/*
 *---------------------------------------------------------------
 * SENTRY ERROR MONITORING
 *---------------------------------------------------------------
 * Initialize Sentry for error tracking and monitoring in production
 */
if ($environment === 'production' && getenv('SENTRY_ENABLED') !== 'false' && getenv('SENTRY_DSN_PHP')) {
    if (class_exists('\Sentry\init')) {
        \Sentry\init([
            'dsn' => getenv('SENTRY_DSN_PHP'),
            'environment' => getenv('SENTRY_ENVIRONMENT') ?: $environment,
            'traces_sample_rate' => (float) (getenv('SENTRY_TRACES_SAMPLE_RATE') ?: 0.2),
            'profiles_sample_rate' => (float) (getenv('SENTRY_TRACES_SAMPLE_RATE') ?: 0.2),
            'send_default_pii' => false, // Don't send personally identifiable information
            'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
                // Filter sensitive data
                $exceptions = $event->getExceptions();
                foreach ($exceptions as $exception) {
                    $stacktrace = $exception->getStacktrace();
                    if ($stacktrace) {
                        foreach ($stacktrace->getFrames() as $frame) {
                            // Remove sensitive variables from stack trace
                            if ($vars = $frame->getVars()) {
                                $frame->setVars(SensitiveDataSanitizer::sanitizeForTelemetry($vars));
                            }
                        }
                    }
                }
                if (method_exists($event, 'setExtra') && method_exists($event, 'getExtra')) {
                    $event->setExtra((array) SensitiveDataSanitizer::sanitizeForTelemetry($event->getExtra()));
                }
                if (method_exists($event, 'setTags') && method_exists($event, 'getTags')) {
                    $event->setTags((array) SensitiveDataSanitizer::sanitizeForTelemetry($event->getTags()));
                }
                if (method_exists($event, 'getRequest') && method_exists($event, 'setRequest')) {
                    $requestPayload = $event->getRequest();
                    if (is_array($requestPayload)) {
                        $event->setRequest((array) SensitiveDataSanitizer::sanitizeForTelemetry($requestPayload));
                    }
                }
                return $event;
            },
        ]);
    }
}

/*
 *---------------------------------------------------------------
 * CRITICAL SESSION CONFIGURATION
 *---------------------------------------------------------------
 * MUST be set BEFORE CodeIgniter boots to prevent session mismatch.
 *
 * PROBLEM: PHP defaults to session.name='PHPSESSID' and
 * session.save_path='/var/lib/php/sessions', but CodeIgniter
 * expects 'ci_session' and 'writable/session'.
 *
 * If session is started with wrong config, it cannot be changed later,
 * causing login loop (session created with one name/path, read with another).
 */
if (session_status() === PHP_SESSION_NONE) {
    // Set session name to match CodeIgniter config
    $sessionCookieName = $_ENV['session.cookieName'] ?? $_SERVER['session.cookieName'] ?? getenv('session.cookieName') ?: 'ci_session';
    session_name($sessionCookieName);

    // Set session save path to CodeIgniter's writable directory
    $sessionPath = dirname(__DIR__) . '/writable/session';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0755, true);
    }
    if (is_writable($sessionPath)) {
        session_save_path($sessionPath);
    } else {
        supportponto_bootstrap_log('session_path_not_writable', ['session_path' => $sessionPath]);
    }
}

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
