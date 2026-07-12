<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Session\Handlers\BaseHandler;
use CodeIgniter\Session\Handlers\FileHandler;
use CodeIgniter\Session\Handlers\RedisHandler;
use App\Support\BootstrapEnv;
class Session extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Session Driver
     * --------------------------------------------------------------------------
     *
     * The session storage driver to use:
     * - `CodeIgniter\Session\Handlers\FileHandler`
     * - `CodeIgniter\Session\Handlers\DatabaseHandler`
     * - `CodeIgniter\Session\Handlers\MemcachedHandler`
     * - `CodeIgniter\Session\Handlers\RedisHandler`
     * - `App\Session\Handlers\SafeFileHandler` (for shared hosting)
     *
     * Default local/dev strategy remains FileHandler for simplicity.
     * Production can and should switch to RedisHandler via session.driver
     * when running with multiple replicas.
     *
     * @var class-string<BaseHandler>
     */
    public string $driver = FileHandler::class;

    /**
     * --------------------------------------------------------------------------
     * Session Cookie Name
     * --------------------------------------------------------------------------
     *
     * The session cookie name, must contain only [0-9a-z_-] characters
     */
    public string $cookieName = 'ci_session';

    /**
     * --------------------------------------------------------------------------
     * Session Expiration
     * --------------------------------------------------------------------------
     *
     * The number of SECONDS you want the session to last.
     * Setting to 0 (zero) means expire when the browser is closed.
     */
    public int $expiration = 7200;

    /**
     * --------------------------------------------------------------------------
     * Session Save Path
     * --------------------------------------------------------------------------
     *
     * The location to save sessions to and is driver dependent.
     *
     * For the 'files' driver, it's a path to a writable directory.
     * WARNING: Only absolute paths are supported!
     *
     * For the 'database' driver, it's a table name.
     * Please read up the manual for the format with other session drivers.
     *
     * IMPORTANT: You are REQUIRED to set a valid save path!
     *
     * NOTE: We cannot use WRITEPATH constant here as it may not be defined
     * when this config class is autoloaded. The path is set in __construct() instead.
     */
    public string $savePath = '';

    /**
     * Constructor
     *
     * Sets savePath using absolute path relative to FCPATH or WRITEPATH
     */
    public function __construct()
    {
        parent::__construct();

        $configuredDriver = BootstrapEnv::get('session.driver', null, ['SESSION_DRIVER']);
        if (is_string($configuredDriver) && trim($configuredDriver) !== '') {
            $this->driver = trim($configuredDriver);
        } elseif (BootstrapEnv::isProduction() && BootstrapEnv::get('REDIS_HOST') !== null) {
            $this->driver = RedisHandler::class;
        }

        $configuredSavePath = BootstrapEnv::get('session.savePath', null, ['SESSION_SAVE_PATH']);
        if (is_string($configuredSavePath) && trim($configuredSavePath) !== '') {
            $this->savePath = $this->usesRedisHandler()
                ? trim($configuredSavePath)
                : $this->normalizeSavePath($configuredSavePath);
        } elseif ($this->usesRedisHandler()) {
            $this->savePath = $this->buildRedisSavePath();
        } elseif (defined('WRITEPATH')) {
            $this->savePath = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'session';
        } else {
            $this->savePath = dirname(FCPATH) . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR . 'session';
        }

        $cookieName = BootstrapEnv::get('session.cookieName', null, ['SESSION_COOKIE_NAME']);
        if (is_string($cookieName) && $cookieName !== '') {
            $this->cookieName = $cookieName;
        }

        $expiration = BootstrapEnv::get('session.expiration', null, ['SESSION_EXPIRATION']);
        if ($expiration !== null && $expiration !== '') {
            $this->expiration = (int) $expiration;
        }

        $matchIP = BootstrapEnv::get('session.matchIP', null, ['SESSION_MATCH_IP']);
        if ($matchIP !== null && $matchIP !== '') {
            $this->matchIP = BootstrapEnv::sessionMatchIp(false);
        }

        $timeToUpdate = BootstrapEnv::get('session.timeToUpdate', null, ['SESSION_TIME_TO_UPDATE']);
        if ($timeToUpdate !== null && $timeToUpdate !== '') {
            $this->timeToUpdate = (int) $timeToUpdate;
        }

        $regenerateDestroy = BootstrapEnv::get('session.regenerateDestroy', null, ['SESSION_REGENERATE_DESTROY']);
        if ($regenerateDestroy !== null && $regenerateDestroy !== '') {
            $this->regenerateDestroy = filter_var($regenerateDestroy, FILTER_VALIDATE_BOOL);
        }

        if (BootstrapEnv::isProduction()) {
            $this->regenerateDestroy = filter_var(BootstrapEnv::get('session.regenerateDestroy', '1', ['SESSION_REGENERATE_DESTROY']), FILTER_VALIDATE_BOOL);
        }
    }

    /**
     * --------------------------------------------------------------------------
     * Session Match IP
     * --------------------------------------------------------------------------
     *
     * Whether to match the user's IP address when reading the session data.
     *
     * WARNING: If you're using the database driver, don't forget to update
     *          your session table's PRIMARY KEY when changing this setting.
     */
    public bool $matchIP = false;

    /**
     * --------------------------------------------------------------------------
     * Session Time to Update
     * --------------------------------------------------------------------------
     *
     * How many seconds between CI regenerating the session ID.
     */
    public int $timeToUpdate = 300;

    /**
     * --------------------------------------------------------------------------
     * Session Regenerate Destroy
     * --------------------------------------------------------------------------
     *
     * Whether to destroy session data associated with the old session ID
     * when auto-regenerating the session ID. When set to FALSE, the data
     * will be later deleted by the garbage collector.
     */
    public bool $regenerateDestroy = true;

    /**
     * --------------------------------------------------------------------------
     * Session Database Group
     * --------------------------------------------------------------------------
     *
     * DB Group for the database session.
     */
    public ?string $DBGroup = null;

    /**
     * --------------------------------------------------------------------------
     * Lock Retry Interval (microseconds)
     * --------------------------------------------------------------------------
     *
     * Relevant only when using RedisHandler.
     *
     * Time (microseconds) to wait if lock cannot be acquired.
     * The default is 100,000 microseconds (= 0.1 seconds).
     */
    public int $lockRetryInterval = 100_000;

    /**
     * --------------------------------------------------------------------------
     * Lock Max Retries
     * --------------------------------------------------------------------------
     *
     * Relevant only when using RedisHandler.
     *
     * Maximum number of lock acquisition attempts.
     * The default is 300 times. That is lock timeout is about 30 (0.1 * 300)
     * seconds.
     */
    public int $lockMaxRetries = 300;



    private function usesRedisHandler(): bool
    {
        return $this->driver === RedisHandler::class;
    }

    private function buildRedisSavePath(): string
    {
        $host = (string) (BootstrapEnv::get('REDIS_HOST', 'redis') ?: 'redis');
        $port = (int) (BootstrapEnv::get('REDIS_PORT', '6379') ?: 6379);
        $database = (int) (BootstrapEnv::get('REDIS_DATABASE', '0') ?: 0);
        $timeout = (float) (BootstrapEnv::get('REDIS_TIMEOUT', '2') ?: 2);
        $password = BootstrapEnv::get('REDIS_PASSWORD');

        $query = [
            'database' => (string) $database,
            'timeout' => (string) $timeout,
        ];

        if (is_string($password) && trim($password) !== '') {
            $query['auth'] = trim($password);
        }

        return sprintf('tcp://%s:%d?%s', $host, $port, http_build_query($query));
    }

    /**
     * Garante que o savePath do handler de arquivos seja absoluto.
     */
    private function normalizeSavePath(string $savePath): string
    {
        $savePath = trim($savePath);

        if ($savePath === '') {
            return $savePath;
        }

        $isWindowsAbsolute = (strlen($savePath) >= 3 && ctype_alpha($savePath[0]) && isset($savePath[1]) && $savePath[1] === ':');
        $isUnixAbsolute = str_starts_with($savePath, DIRECTORY_SEPARATOR);

        if ($isWindowsAbsolute || $isUnixAbsolute) {
            return rtrim($savePath, DIRECTORY_SEPARATOR);
        }

        if (defined('FCPATH')) {
            return dirname(FCPATH) . DIRECTORY_SEPARATOR . trim($savePath, DIRECTORY_SEPARATOR);
        }

        return $savePath;
    }
}
