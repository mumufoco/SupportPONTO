<?php

namespace App\Filters;

use function helper;
use function sp_csp_nonce;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\ContentSecurityPolicy;

/**
 * Security Headers Filter
 *
 * Adiciona headers de segurança em todas as respostas HTTP.
 */
class SecurityHeadersFilter implements FilterInterface
{
    protected array $headers = [
        'X-Frame-Options' => 'DENY',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(self), camera=(self), microphone=(), payment=(), usb=(), fullscreen=(self)',
        'Cross-Origin-Opener-Policy' => 'same-origin',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'Cross-Origin-Embedder-Policy' => 'unsafe-none',
        'X-Permitted-Cross-Domain-Policies' => 'none',
    ];

    protected ?bool $enableHSTS = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        foreach ($this->headers as $name => $value) {
            if (! $response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        if (! $response->hasHeader('Content-Security-Policy')) {
            helper('csp_nonce');
            $response->setHeader('Content-Security-Policy', $this->buildFallbackCsp($request));
        }

        if ($this->shouldSendHsts($request)) {
            $response->setHeader('Strict-Transport-Security', $this->buildHstsValue());
        }

        $response->appendHeader('Vary', 'Accept');
        $response->appendHeader('Vary', 'Authorization');
        $response->appendHeader('Vary', 'Cookie');

        if ($this->shouldDisableCache($request)) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->setHeader('Pragma', 'no-cache');
            $response->setHeader('Expires', '0');
        }

        return $response;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function removeHeader(string $name): self
    {
        unset($this->headers[$name]);
        return $this;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function allowSameOriginFrames(): self
    {
        $this->headers['X-Frame-Options'] = 'SAMEORIGIN';
        return $this;
    }

    public function allowFramesFrom(string $origin): self
    {
        $this->headers['X-Frame-Options'] = 'SAMEORIGIN';
        $this->appendFrameAncestorsDirective(["'self'", $origin]);

        return $this;
    }

    public function denyAllFrames(): self
    {
        $this->headers['X-Frame-Options'] = 'DENY';
        return $this;
    }

    public function setCSP(array $directives): self
    {
        $parts = [];
        foreach ($directives as $directive => $values) {
            $values = array_values(array_filter((array) $values, static fn ($value) => $value !== null && $value !== ''));
            if ($directive === '' || $values === []) {
                continue;
            }

            $parts[] = $directive . ' ' . implode(' ', $values);
        }

        if ($parts !== []) {
            $this->headers['Content-Security-Policy'] = implode('; ', $parts);
        }

        return $this;
    }

    public function getCSP(): string
    {
        return $this->headers['Content-Security-Policy'] ?? $this->buildFallbackCsp(service('request'));
    }

    public function setPermissionsPolicy(array $permissions): self
    {
        $parts = [];
        foreach ($permissions as $feature => $allowList) {
            $parts[] = $feature . '=(' . implode(' ', $allowList) . ')';
        }
        $this->headers['Permissions-Policy'] = implode(', ', $parts);
        return $this;
    }

    public function setEnableHSTS(bool $enabled): self
    {
        $this->enableHSTS = $enabled;
        return $this;
    }

    public function isHSTSEnabled(): bool
    {
        return $this->enableHSTS ?? $this->isHstsEnabledByEnvironment();
    }

    protected function appendFrameAncestorsDirective(array $sources): void
    {
        $csp = $this->headers['Content-Security-Policy'] ?? $this->buildFallbackCsp(service('request'));
        $csp = preg_replace('/;?\s*frame-ancestors\s+[^;]+/i', '', $csp) ?? $csp;
        $csp = trim($csp, '; ');
        $directive = 'frame-ancestors ' . implode(' ', array_unique($sources));

        $this->headers['Content-Security-Policy'] = $csp === ''
            ? $directive
            : $csp . '; ' . $directive;
    }

    protected function buildFallbackCsp(RequestInterface $request): string
    {
        $config = config(ContentSecurityPolicy::class);

        helper('csp_nonce');

        $scriptSrc = [
            "'self'",
            sprintf("'nonce-%s'", sp_csp_nonce()),
            'https://browser.sentry-cdn.com',
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://unpkg.com',
        ];

        $styleSrc = [
            "'self'",
            'https://cdn.jsdelivr.net',
            'https://cdnjs.cloudflare.com',
            'https://fonts.googleapis.com',
            'https://unpkg.com',
        ];

        if ($config->shouldAllowInlineStyles()) {
            $styleSrc[] = "'unsafe-inline'";
        }

        $fontSrc = [
            "'self'",
            'data:',
            'https://fonts.gstatic.com',
            'https://cdnjs.cloudflare.com',
            'https://cdn.jsdelivr.net',
        ];

        $imgSrc = [
            "'self'",
            'data:',
            'blob:',
            'https://api.qrserver.com',
            'https://*.tile.openstreetmap.org',
            'https://tile.openstreetmap.org',
        ];

        $connectSrc = array_values(array_unique(array_filter(array_merge(
            [
                "'self'",
                'https://*.ingest.sentry.io',
            ],
            $this->buildRequestScopedConnectSources($request),
            $config->buildConfiguredConnectSources()
        ))));

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "manifest-src 'self'",
            'img-src ' . implode(' ', $imgSrc),
            'font-src ' . implode(' ', $fontSrc),
            'style-src ' . implode(' ', $styleSrc),
            'script-src ' . implode(' ', $scriptSrc),
            $config->shouldAllowInlineScriptAttributes()
                ? "script-src-attr 'unsafe-inline'"
                : "script-src-attr 'none'",
            'connect-src ' . implode(' ', $connectSrc),
            "form-action 'self'",
            'upgrade-insecure-requests',
        ];

        return implode('; ', $directives);
    }

    /** @return list<string> */
    protected function buildRequestScopedConnectSources(RequestInterface $request): array
    {
        $sources = [];
        $host = $request->getUri()->getHost();
        $scheme = $request->isSecure() ? 'wss' : 'ws';
        $configuredPort = trim((string) env('WEBSOCKET_PORT', '8080'));

        if ($host !== '') {
            if ($configuredPort !== '') {
                $sources[] = sprintf('%s://%s:%s', $scheme, $host, $configuredPort);
            }

            $sources[] = sprintf('%s://%s', $scheme, $host);
        }

        return array_values(array_unique($sources));
    }

    protected function shouldSendHsts(RequestInterface $request): bool
    {
        if ($this->enableHSTS === false) {
            return false;
        }

        if (! $this->isHstsEnabledByEnvironment()) {
            return false;
        }

        return $request->isSecure()
            || strtolower($request->getHeaderLine('X-Forwarded-Proto')) === 'https'
            || str_contains(strtolower($request->getHeaderLine('Forwarded')), 'proto=https')
            || $this->cloudflareVisitorIndicatesHttps($request);
    }

    protected function isHstsEnabledByEnvironment(): bool
    {
        return filter_var((string) env('SECURITY_HSTS_ENABLED', 'false'), FILTER_VALIDATE_BOOL)
            || filter_var((string) env('APP_HSTS_ENABLED', 'false'), FILTER_VALIDATE_BOOL);
    }

    protected function buildHstsValue(): string
    {
        $maxAge = max(300, (int) env('SECURITY_HSTS_MAX_AGE', '31536000'));
        $parts = ['max-age=' . $maxAge];

        if (filter_var((string) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', 'true'), FILTER_VALIDATE_BOOL)) {
            $parts[] = 'includeSubDomains';
        }

        if (filter_var((string) env('SECURITY_HSTS_PRELOAD', 'false'), FILTER_VALIDATE_BOOL)) {
            $parts[] = 'preload';
        }

        return implode('; ', $parts);
    }

    protected function cloudflareVisitorIndicatesHttps(RequestInterface $request): bool
    {
        $visitor = $request->getHeaderLine('CF-Visitor');

        if ($visitor === '') {
            return false;
        }

        $decoded = json_decode($visitor, true);

        return is_array($decoded) && strtolower((string) ($decoded['scheme'] ?? '')) === 'https';
    }

    protected function shouldDisableCache(RequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');

        if (str_starts_with($path, 'api/') || str_starts_with($path, 'auth/') || str_starts_with($path, 'qrcode/') || str_starts_with($path, 'biometric/')) {
            return true;
        }

        return session()->has('user_id');
    }
}
