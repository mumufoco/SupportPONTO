<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

class ProductionHardening extends BaseConfig
{
    public bool $enabled;
    public bool $protectInternalPaths;
    public bool $requireProductionEnvironment;
    public bool $requireWebInstallerDisabled;
    public bool $requireDebugDisabled;
    public bool $requireHttpsBaseUrl;
    public int $maxLoggerThreshold;

    /** @var list<string> */
    public array $protectedDirectories = [
        'app', 'system', 'writable', 'vendor', 'tests', 'tools', 'build', 'storage', 'docker', 'docs', 'deepface-api',
    ];

    /** @var list<string> */
    public array $protectedFiles = [
        '.env', '.git', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'phpunit.xml', 'spark', 'release.json', 'artifact-manifest.json', 'openapi.yaml',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->enabled = $this->boolEnv('SECURITY_PRODUCTION_HARDENING', true);
        $this->protectInternalPaths = $this->boolEnv('SECURITY_PROTECT_INTERNAL_PATHS', true);
        $this->requireProductionEnvironment = $this->boolEnv('SECURITY_REQUIRE_PRODUCTION_ENVIRONMENT', true);
        $this->requireWebInstallerDisabled = $this->boolEnv('SECURITY_REQUIRE_WEB_INSTALLER_DISABLED', true);
        $this->requireDebugDisabled = $this->boolEnv('SECURITY_REQUIRE_DEBUG_DISABLED', true);
        $this->requireHttpsBaseUrl = $this->boolEnv('SECURITY_REQUIRE_HTTPS_BASE_URL', true);
        $this->maxLoggerThreshold = max(1, (int) env('SECURITY_MAX_LOGGER_THRESHOLD', 5));
    }

    private function boolEnv(string $key, bool $default): bool
    {
        $value = env($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
