<?php

namespace Config;

use App\Support\BootstrapEnv;
use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    /**
     * Base Site URL
     *
     * Automatically configured from environment variable.
     * Falls back to controlled auto-detection when not set.
     */
    public string $baseURL = '';

    /**
     * Allowed Hostnames
     */
    public array $allowedHostnames = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $cookieDomain = BootstrapEnv::get('app.cookieDomain');
        if (is_string($cookieDomain) && $cookieDomain !== '') {
            $this->cookieDomain = $cookieDomain;
        }

        $this->proxyIPs = $this->normalizeProxyEntries(BootstrapEnv::csv('app.proxyIPs', [], ['APP_PROXY_IPS']));
        $this->allowedHostnames = $this->normalizeHostnames(BootstrapEnv::csv('app.allowedHostnames', [], ['APP_ALLOWED_HOSTNAMES']));
        $this->forceGlobalSecureRequests = BootstrapEnv::bool('app.forceGlobalSecureRequests', false, ['APP_FORCE_GLOBAL_SECURE_REQUESTS']);

        // Priority: explicit .env > trusted production fallback > development auto-detect.
        $this->baseURL = $this->resolveBaseURL(BootstrapEnv::baseUrl());

        if (BootstrapEnv::bool('app.enforceHttpsSchemeInBaseURL', false, ['APP_ENFORCE_HTTPS_SCHEME_IN_BASE_URL']) && $this->baseURL !== '') {
            $this->baseURL = preg_replace('#^http://#i', 'https://', $this->baseURL) ?? $this->baseURL;
        }

        if ($this->allowedHostnames === [] && $this->baseURL !== '') {
            $baseHost = $this->normalizeHostname((string) parse_url($this->baseURL, PHP_URL_HOST));
            if ($baseHost !== null) {
                $this->allowedHostnames = [$baseHost];
            }
        }

        $this->appTimezone = (string) (BootstrapEnv::get('app.appTimezone', $this->appTimezone, ['APP_TIMEZONE']) ?? $this->appTimezone);
        $this->defaultLocale = (string) (BootstrapEnv::get('app.defaultLocale', $this->defaultLocale, ['APP_DEFAULT_LOCALE']) ?? $this->defaultLocale);
        $configuredLocales = BootstrapEnv::csv('app.supportedLocales', [], ['APP_SUPPORTED_LOCALES']);
        if ($configuredLocales !== []) {
            $this->supportedLocales = $configuredLocales;
        }

        $cookieSecure = strtolower(trim((string) BootstrapEnv::get('app.cookieSecure', 'auto', ['COOKIE_SECURE', 'cookie.secure', 'session.cookieSecure'])));
        if ($cookieSecure === 'true' || $cookieSecure === '1') {
            $this->cookieSecure = true;
        } elseif ($cookieSecure === 'false' || $cookieSecure === '0') {
            $this->cookieSecure = false;
        } else {
            $this->cookieSecure = $this->isSecureRequestContext()
                || $this->forceGlobalSecureRequests
                || BootstrapEnv::isHttpsUrl($this->baseURL);
        }

        $this->emitConfigurationDiagnostics();
    }

    /**
     * Auto-detect base URL from server variables.
     *
     * In production this method avoids trusting HTTP_HOST when the base URL
     * was not explicitly configured, preferring allowedHostnames or a neutral
     * localhost fallback. This reduces host-header driven URL generation.
     */
    private function detectBaseURL(bool $trustedOnly = false): string
    {
        $protocol = $this->defaultDetectedScheme();

        if ($trustedOnly) {
            $trustedHost = $this->allowedHostnames[0] ?? $this->normalizeHostname((string) ($_SERVER['SERVER_NAME'] ?? ''));
            if ($trustedHost !== null) {
                return $protocol . '://' . $trustedHost . '/';
            }

            return $protocol . '://localhost/';
        }

        $host = $this->normalizeHostForRuntime(
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            (string) ($_SERVER['SERVER_NAME'] ?? 'localhost')
        );

        return $protocol . '://' . $host . '/';
    }

    private function defaultDetectedScheme(): string
    {
        return ($this->isSecureRequestContext() || $this->forceGlobalSecureRequests || BootstrapEnv::isProduction())
            ? 'https'
            : 'http';
    }

    private function normalizeHostForRuntime(string $httpHost, string $serverName): string
    {
        $normalizedHttpHost = $this->normalizeHostname($httpHost);
        if ($normalizedHttpHost !== null) {
            return $normalizedHttpHost;
        }

        $normalizedServerName = $this->normalizeHostname($serverName);
        if ($normalizedServerName !== null) {
            return $normalizedServerName;
        }

        return 'localhost';
    }

    private function normalizeHostname(string $host): ?string
    {
        $candidate = strtolower(trim($host));
        if ($candidate === '') {
            return null;
        }

        if (str_contains($candidate, '://')) {
            $parsed = parse_url($candidate, PHP_URL_HOST);
            if (is_string($parsed) && $parsed !== '') {
                $candidate = strtolower(trim($parsed));
            }
        }

        if (str_contains($candidate, ',')) {
            $candidate = trim(explode(',', $candidate)[0] ?? '');
        }

        if (str_starts_with($candidate, '[') && preg_match('/^\[([0-9a-f:.]+)](?::\d+)?$/i', $candidate, $matches) === 1) {
            $candidate = strtolower($matches[1]);
        } elseif (substr_count($candidate, ':') === 1 && preg_match('/^(.+):(\d+)$/', $candidate, $matches) === 1) {
            $candidate = strtolower(trim($matches[1]));
        }

        $candidate = trim($candidate, " \t\n\r\0\x0B[]");
        if ($candidate === '' || preg_match('/[\/\s]/', $candidate) === 1) {
            return null;
        }

        if ($candidate === 'localhost') {
            return $candidate;
        }

        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return $candidate;
        }

        if (preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9-]{1,63}(?<!-)\.)*[a-z0-9-]{1,63}(?<!-)$/', $candidate) === 1) {
            return $candidate;
        }

        return null;
    }

    /**
     * @param list<string> $hosts
     * @return list<string>
     */
    private function normalizeHostnames(array $hosts): array
    {
        $normalized = [];
        foreach ($hosts as $host) {
            if (! is_string($host)) {
                continue;
            }

            $candidate = $this->normalizeHostname($host);
            if ($candidate !== null) {
                $normalized[] = $candidate;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    private function normalizeProxyEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $candidate = trim($entry);
            if ($candidate === '') {
                continue;
            }

            if ($candidate === '*') {
                $normalized[] = '*';
                continue;
            }

            if (str_contains($candidate, '/')) {
                [$subnet, $mask] = array_pad(explode('/', $candidate, 2), 2, '');
                if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) && ctype_digit($mask)) {
                    $normalized[] = strtolower($subnet) . '/' . $mask;
                }
                continue;
            }

            $host = $this->normalizeHostname($candidate);
            if ($host !== null) {
                $normalized[] = $host;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function isSecureRequestContext(): bool
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return false;
        }

        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }

        $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');
        if ($serverPort === '443') {
            return true;
        }

        if ($this->isTrustedProxyRequest()) {
            $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($forwardedProto !== '' && in_array('https', array_map('trim', explode(',', $forwardedProto)), true)) {
                return true;
            }

            $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
            if ($forwardedSsl === 'on') {
                return true;
            }

            $forwardedScheme = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? ''));
            if ($forwardedScheme === 'https') {
                return true;
            }

            $forwardedPort = trim((string) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? ''));
            if ($forwardedPort === '443') {
                return true;
            }
        }

        return false;
    }

    private function isTrustedProxyRequest(): bool
    {
        if ($this->proxyIPs === []) {
            return false;
        }

        $remoteAddr = strtolower(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
        if ($remoteAddr === '') {
            return false;
        }

        foreach ($this->proxyIPs as $proxyEntry) {
            if ($proxyEntry === '*') {
                return true;
            }

            if ($proxyEntry === $remoteAddr) {
                return true;
            }

            if (str_contains($proxyEntry, '/') && $this->ipMatchesCidr($remoteAddr, $proxyEntry)) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $maskBits] = array_pad(explode('/', $cidr, 2), 2, null);
        if (! is_string($subnet) || ! is_string($maskBits) || ! ctype_digit($maskBits)) {
            return false;
        }

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $mask = (int) $maskBits;
        $byteLength = strlen($ipBin);
        $maxMask = $byteLength * 8;
        if ($mask < 0 || $mask > $maxMask) {
            return false;
        }

        $fullBytes = intdiv($mask, 8);
        $remainingBits = $mask % 8;

        if ($fullBytes > 0 && substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $maskByte = chr((0xFF << (8 - $remainingBits)) & 0xFF);

        return (ord($ipBin[$fullBytes]) & ord($maskByte)) === (ord($subnetBin[$fullBytes]) & ord($maskByte));
    }

    /**
     * Resolve base URL from environment while rejecting invalid path-only values.
     *
     * CodeIgniter may surface "/" when no explicit base URL is configured in some
     * CLI/runtime combinations. That value is not a valid absolute URL and breaks
     * route generation and CLI commands.
     */
    private function resolveBaseURL(mixed $envBaseURL): string
    {
        if (! is_string($envBaseURL)) {
            return BootstrapEnv::isProduction() ? $this->detectBaseURL(true) : $this->detectBaseURL(false);
        }

        $baseURL = trim($envBaseURL, " \t\n\r\0\x0B'\"");

        if ($baseURL === '' || $baseURL === '/') {
            return BootstrapEnv::isProduction() ? $this->detectBaseURL(true) : $this->detectBaseURL(false);
        }

        if (! preg_match('#^https?://#i', $baseURL)) {
            if (str_starts_with($baseURL, '//') || str_starts_with($baseURL, '/')) {
                return BootstrapEnv::isProduction() ? $this->detectBaseURL(true) : $this->detectBaseURL(false);
            }

            $scheme = BootstrapEnv::isProduction() ? 'https://' : 'http://';
            $baseURL = $scheme . ltrim($baseURL, '/');
        }

        $host = $this->normalizeHostname((string) parse_url($baseURL, PHP_URL_HOST));
        if ($host === null) {
            return BootstrapEnv::isProduction() ? $this->detectBaseURL(true) : $this->detectBaseURL(false);
        }

        $scheme = strtolower((string) parse_url($baseURL, PHP_URL_SCHEME));
        $path = (string) (parse_url($baseURL, PHP_URL_PATH) ?? '/');
        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        return $scheme . '://' . $host . rtrim($path, '/') . '/';
    }

    private function emitConfigurationDiagnostics(): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }

        if ($this->forceGlobalSecureRequests && ! $this->isSecureRequestContext()) {
            $this->logConfigDiagnostic('warning', 'app.force_global_secure_requests_untrusted_context', [
                'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'proxy_ips_configured' => $this->proxyIPs,
                'host' => (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''),
            ]);
        }

        if (! $this->isTrustedProxyRequest()) {
            $forwardedHeaders = array_filter([
                'x_forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
                'x_forwarded_ssl' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? null,
                'x_forwarded_scheme' => $_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? null,
                'x_forwarded_port' => $_SERVER['HTTP_X_FORWARDED_PORT'] ?? null,
            ], static fn ($value) => is_string($value) && trim($value) !== '');

            if ($forwardedHeaders !== []) {
                $this->logConfigDiagnostic('warning', 'app.forwarded_headers_from_untrusted_proxy', [
                    'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    'headers' => $forwardedHeaders,
                ]);
            }
        }

        if ($this->baseURL === '') {
            $this->logConfigDiagnostic('warning', 'app.base_url_empty_after_resolution', []);
        }

        if (ENVIRONMENT === 'production') {
            $configuredBaseURL = trim((string) BootstrapEnv::baseUrl(''), " \t\n\r\0\x0B'\"");
            if ($configuredBaseURL === '' || $configuredBaseURL === '/' || str_contains($configuredBaseURL, 'CHANGE-ME.example.com')) {
                $this->logConfigDiagnostic('error', 'app.production_base_url_not_hardened', [
                    'configured_base_url' => $configuredBaseURL,
                    'resolved_base_url' => $this->baseURL,
                ]);
            }

            if ($configuredBaseURL === '' || $configuredBaseURL === '/') {
                $this->logConfigDiagnostic('error', 'app.production_base_url_autodetected', [
                    'resolved_base_url' => $this->baseURL,
                    'allowed_hostnames' => $this->allowedHostnames,
                ]);
            }

            if (! BootstrapEnv::bool('app.forceGlobalSecureRequests', false, ['APP_FORCE_GLOBAL_SECURE_REQUESTS'])) {
                $this->logConfigDiagnostic('warning', 'app.production_force_global_secure_requests_disabled', []);
            }

            if (! BootstrapEnv::bool('app.enforceHttpsSchemeInBaseURL', false, ['APP_ENFORCE_HTTPS_SCHEME_IN_BASE_URL'])) {
                $this->logConfigDiagnostic('warning', 'app.production_https_scheme_enforcement_disabled', []);
            }

            if ($this->allowedHostnames === []) {
                $this->logConfigDiagnostic('warning', 'app.production_allowed_hostnames_not_explicit', [
                    'resolved_base_url' => $this->baseURL,
                ]);
            }

            if (array_filter($this->allowedHostnames, static fn (string $host): bool => str_contains($host, 'change-me.example.com')) !== []) {
                $this->logConfigDiagnostic('error', 'app.production_allowed_hostnames_placeholder', [
                    'allowed_hostnames' => $this->allowedHostnames,
                ]);
            }

            if ($this->cookieSecure !== true) {
                $this->logConfigDiagnostic('warning', 'app.production_cookie_secure_not_forced', [
                    'cookie_secure' => $this->cookieSecure,
                ]);
            }

            if ($this->baseURL !== '' && ! BootstrapEnv::isHttpsUrl($this->baseURL)) {
                $this->logConfigDiagnostic('warning', 'app.production_base_url_not_https', [
                    'resolved_base_url' => $this->baseURL,
                ]);
            }
        }
    }

    private function logConfigDiagnostic(string $level, string $event, array $context): void
    {
        $dir = WRITEPATH . 'logs/';
        if (! is_dir($dir)) {
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

    /**
     * Index Page
     */
    public string $indexPage = '';

    /**
     * URI Protocol
     */
    public string $uriProtocol = 'REQUEST_URI';

    /**
     * Default Locale
     */
    public string $defaultLocale = 'pt-BR';

    /**
     * Negotiate Locale
     */
    public bool $negotiateLocale = false;

    /**
     * Supported Locales
     */
    public array $supportedLocales = ['pt-BR'];

    /**
     * Application Timezone
     */
    public string $appTimezone = 'America/Sao_Paulo';

    /**
     * Default Character Set
     */
    public string $charset = 'UTF-8';

    /**
     * Force Global Secure Requests
     * Controlled exclusively by environment configuration.
     */
    public bool $forceGlobalSecureRequests = false;

    /**
     * Session Variables
     *
     * IMPORTANT: Session configuration has been moved to Config/Session.php
     * as per CodeIgniter 4.5+ best practices. DO NOT configure session
     * settings here as it may cause conflicts with Session.php.
     *
     * Session settings are now in: app/Config/Session.php
     */

    /**
     * Cookie Settings
     *
     * SECURITY: cookieSecure is controlled by app.cookieSecure (true/false/auto)
     * SECURITY: cookieHTTPOnly is ALWAYS true to prevent XSS attacks
     */
    public string $cookiePrefix = '';
    public string $cookieDomain = '';
    public string $cookiePath = '/';
    public bool $cookieSecure = false;
    public bool $cookieHTTPOnly = true;
    public ?string $cookieSameSite = 'Lax';

    /**
     * Reverse Proxy IPs
     */
    public array $proxyIPs = [];

    /**
     * Content Security Policy
     */
    public bool $CSPEnabled = false;

    public string $permittedURIChars = 'a-z 0-9~%.:_\-';
}
