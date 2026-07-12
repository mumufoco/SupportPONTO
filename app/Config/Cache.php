<?php

namespace Config;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Cache\Handlers\DummyHandler;
use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Cache\Handlers\MemcachedHandler;
use CodeIgniter\Cache\Handlers\PredisHandler;
use CodeIgniter\Cache\Handlers\RedisHandler;
use CodeIgniter\Cache\Handlers\WincacheHandler;
use CodeIgniter\Config\BaseConfig;

class Cache extends BaseConfig
{
    /**
     * Handler padrão seguro para bootstrap e instalação.
     *
     * Redis pode ser habilitado por ambiente somente quando a infraestrutura
     * estiver pronta e validada. Isso evita que a aplicação dependa de Redis
     * logo no bootstrap em ambientes locais, de instalação ou homologação.
     */
    public string $handler = 'file';

    /**
     * Fallback seguro para filesystem caso o handler primário falhe.
     */
    public string $backupHandler = 'file';

    /**
     * --------------------------------------------------------------------------
     * Key Prefix
     * --------------------------------------------------------------------------
     *
     * This string is added to all cache item names to help avoid collisions
     * if you run multiple applications with the same cache engine.
     */
    public string $prefix = '';

    /**
     * --------------------------------------------------------------------------
     * Default TTL
     * --------------------------------------------------------------------------
     *
     * The default number of seconds to save items when none is specified.
     *
     * WARNING: This is not used by framework handlers where 60 seconds is
     * hard-coded, but may be useful to projects and modules. This will replace
     * the hard-coded value in a future release.
     */
    public int $ttl = 60;

    /**
     * --------------------------------------------------------------------------
     * Reserved Characters
     * --------------------------------------------------------------------------
     *
     * A string of reserved characters that will not be allowed in keys or tags.
     * Strings that violate this restriction will cause handlers to throw.
     * Default: {}()/\@:
     *
     * NOTE: The default set is required for PSR-6 compliance.
     */
    public string $reservedCharacters = '{}()/\@:';

    /**
     * --------------------------------------------------------------------------
     * File settings
     * --------------------------------------------------------------------------
     *
     * Your file storage preferences can be specified below, if you are using
     * the File driver.
     *
     * @var array{storePath?: string, mode?: int}
     */
    public array $file = [
        'storePath' => WRITEPATH . 'cache/',
        'mode'      => 0640,
    ];

    /**
     * -------------------------------------------------------------------------
     * Memcached settings
     * -------------------------------------------------------------------------
     *
     * Your Memcached servers can be specified below, if you are using
     * the Memcached drivers.
     *
     * @see https://codeigniter.com/user_guide/libraries/caching.html#memcached
     *
     * @var array{host?: string, port?: int, weight?: int, raw?: bool}
     */
    public array $memcached = [
        'host'   => '127.0.0.1',
        'port'   => 11211,
        'weight' => 1,
        'raw'    => false,
    ];

    /**
     * Redis settings — valores lidos do ambiente para evitar hardcode.
     *
     * Para habilitar Redis como cache principal use, por ambiente:
     * cache.handler = redis
     * cache.requireRedis = true
     * REDIS_HOST / REDIS_PORT / REDIS_DATABASE
     *
     * @var array{host?: string, password?: string|null, port?: int, timeout?: int, database?: int}
     */
    public array $redis = [
        'host'     => '${REDIS_HOST}',
        'password' => '${REDIS_PASSWORD}',
        'port'     => 6379,
        'timeout'  => 2,
        'database' => 0,
    ];



    public function __construct()
    {
        parent::__construct();

        $backupHandler = env('cache.backupHandler');
        if (is_string($backupHandler) && $backupHandler !== '') {
            $this->backupHandler = $this->normalizeHandler($backupHandler, $this->backupHandler);
        }

        $redisHost = env('REDIS_HOST');
        if (is_string($redisHost) && $redisHost !== '') {
            $this->redis['host'] = $redisHost;
        }

        $redisPassword = env('REDIS_PASSWORD');
        if ($redisPassword !== null && $redisPassword !== '') {
            $this->redis['password'] = $redisPassword;
        } else {
            $this->redis['password'] = null;
        }

        $redisPort = env('REDIS_PORT');
        if ($redisPort !== null && $redisPort != '') {
            $this->redis['port'] = (int) $redisPort;
        }

        $redisTimeout = env('REDIS_TIMEOUT');
        if ($redisTimeout !== null && $redisTimeout !== '') {
            $this->redis['timeout'] = (int) $redisTimeout;
        }

        $redisDatabase = env('REDIS_DATABASE');
        if ($redisDatabase !== null && $redisDatabase !== '') {
            $this->redis['database'] = (int) $redisDatabase;
        }

        $requestedHandler = env('cache.handler');
        if (is_string($requestedHandler) && $requestedHandler !== '') {
            $this->handler = $this->normalizeRequestedHandler($requestedHandler);
        }

        if (! is_string($this->backupHandler) || $this->backupHandler === '') {
            $this->backupHandler = 'file';
        }

        if ($this->handler === 'redis' && ! $this->isRedisUsable()) {
            $fallback = $this->backupHandler !== 'redis' ? $this->backupHandler : 'file';
            $this->logCacheDiagnostic('warning', 'cache.redis_unavailable_fallback', ['requested_handler' => 'redis', 'fallback_handler' => $fallback]);
            $this->handler = $fallback;
        }

        if ($this->handler === $this->backupHandler && $this->handler !== 'file') {
            $this->backupHandler = 'file';
        }

        if ($this->handler !== 'redis' && $this->toBool(env('cache.requireRedis'))) {
            $this->logCacheDiagnostic('error', 'cache.redis_required_but_not_usable', ['active_handler' => $this->handler]);
        }
    }

    private function normalizeRequestedHandler(string $handler): string
    {
        $normalized = $this->normalizeHandler($handler, 'file');

        if ($normalized === 'redis' && ! $this->shouldUseRedisAsPrimaryHandler()) {
            return 'file';
        }

        return $normalized;
    }

    private function normalizeHandler(string $handler, string $fallback): string
    {
        $normalized = strtolower(trim($handler));

        return array_key_exists($normalized, $this->validHandlers) ? $normalized : $fallback;
    }


    private function logCacheDiagnostic(string $level, string $event, array $context): void
    {
        $dir = WRITEPATH . 'logs/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = [
            'level' => $level,
            'event' => $event,
            'timestamp' => date('c'),
            'context' => $context,
        ];

        @error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, 3, $dir . 'config-' . date('Y-m-d') . '.log');
    }

    private function shouldUseRedisAsPrimaryHandler(): bool
    {
        $requireRedis = env('cache.requireRedis');
        $allowRedis = env('cache.enableRedis');
        $environment = strtolower((string) env('CI_ENVIRONMENT', ENVIRONMENT));

        if ($this->toBool($requireRedis)) {
            return true;
        }

        if ($this->toBool($allowRedis)) {
            return true;
        }

        return $environment === 'production';
    }

    private function isRedisUsable(): bool
    {
        $host = trim((string) ($this->redis['host'] ?? ''));
        $port = (int) ($this->redis['port'] ?? 0);

        return $host !== '' && $port > 0;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * --------------------------------------------------------------------------
     * Available Cache Handlers
     * --------------------------------------------------------------------------
     *
     * This is an array of cache engine alias' and class names. Only engines
     * that are listed here are allowed to be used.
     *
     * @var array<string, class-string<CacheInterface>>
     */
    public array $validHandlers = [
        'dummy'     => DummyHandler::class,
        'file'      => FileHandler::class,
        'memcached' => MemcachedHandler::class,
        'predis'    => PredisHandler::class,
        'redis'     => RedisHandler::class,
        'wincache'  => WincacheHandler::class,
    ];

    /**
     * --------------------------------------------------------------------------
     * Web Page Caching: Cache Include Query String
     * --------------------------------------------------------------------------
     *
     * Whether to take the URL query string into consideration when generating
     * output cache files. Valid options are:
     *
     *    false = Disabled
     *    true  = Enabled, take all query parameters into account.
     *            Please be aware that this may result in numerous cache
     *            files generated for the same page over and over again.
     *    ['q'] = Enabled, but only take into account the specified list
     *            of query parameters.
     *
     * @var bool|list<string>
     */
    public $cacheQueryString = false;
}
