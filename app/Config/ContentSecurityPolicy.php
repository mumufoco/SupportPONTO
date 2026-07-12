<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Stores the default settings for the ContentSecurityPolicy, if you
 * choose to use it. The values here will be read in and set as defaults
 * for the site. If needed, they can be overridden on a page-by-page basis.
 */
class ContentSecurityPolicy extends BaseConfig
{
    public bool $reportOnly = false;

    public ?string $reportURI = null;

    public bool $upgradeInsecureRequests = true;

    /** @var list<string>|string|null */
    public $defaultSrc = 'self';

    /** @var list<string>|string */
    public $scriptSrc = ['self', 'https://browser.sentry-cdn.com', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://unpkg.com'];

    /** @var list<string>|string */
    public $styleSrc = ['self', 'https://cdn.jsdelivr.net', 'https://cdnjs.cloudflare.com', 'https://fonts.googleapis.com', 'https://unpkg.com'];

    /** @var list<string>|string */
    public $imageSrc = ['self', 'data:', 'blob:', 'https://api.qrserver.com', 'https://*.tile.openstreetmap.org', 'https://tile.openstreetmap.org'];

    /** @var list<string>|string|null */
    public $baseURI = 'self';

    /** @var list<string>|string */
    public $childSrc = 'self';

    /** @var list<string>|string */
    public $connectSrc = ['self', 'https://*.ingest.sentry.io'];

    /** @var list<string>|string */
    public $fontSrc = ['self', 'data:', 'https://fonts.gstatic.com', 'https://cdnjs.cloudflare.com', 'https://cdn.jsdelivr.net'];

    /** @var list<string>|string */
    public $formAction = 'self';

    /** @var list<string>|string|null */
    public $frameAncestors = 'none';

    /** @var list<string>|string|null */
    public $frameSrc = 'none';

    /** @var list<string>|string|null */
    public $mediaSrc;

    /** @var list<string>|string */
    public $objectSrc = 'none';

    /** @var list<string>|string|null */
    public $manifestSrc = 'self';

    /** @var list<string>|string|null */
    public $pluginTypes;

    /** @var list<string>|string|null */
    public $sandbox;

    public string $styleNonceTag = '{csp-style-nonce}';

    public string $scriptNonceTag = '{csp-script-nonce}';

    public bool $autoNonce = true;

    /** @var array<string, string> */
    public array $permissionsPolicy = [
        'camera' => 'self',
        'microphone' => 'none',
        'geolocation' => 'none',
        'usb' => 'none',
        'payment' => 'none',
        'fullscreen' => 'self',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->reportOnly = filter_var((string) env('CSP_REPORT_ONLY', $this->reportOnly ? 'true' : 'false'), FILTER_VALIDATE_BOOL);
        $this->upgradeInsecureRequests = filter_var((string) env('CSP_UPGRADE_INSECURE_REQUESTS', $this->upgradeInsecureRequests ? 'true' : 'false'), FILTER_VALIDATE_BOOL);

        if ($this->shouldAllowInlineStyles()) {
            $this->styleSrc[] = 'unsafe-inline';
        }

        $this->connectSrc = array_values(array_unique(array_merge(
            (array) $this->connectSrc,
            $this->buildConfiguredConnectSources()
        )));
    }

    /** @return list<string> */
    public function buildConfiguredConnectSources(): array
    {
        $sources = [];

        $envKeys = [
            'CSP_CONNECT_SRC',
            'WEBSOCKET_PUBLIC_ORIGIN',
            'WEBSOCKET_PUBLIC_URL',
            'PUSH_PUBLIC_ORIGIN',
        ];

        foreach ($envKeys as $key) {
            $sources = array_merge($sources, $this->parseEnvList((string) env($key, '')));
        }

        return array_values(array_unique(array_filter($sources, static fn (string $value): bool => $value !== '')));
    }

    public function shouldAllowInlineStyles(): bool
    {
        return filter_var((string) env('CSP_ALLOW_INLINE_STYLE', 'true'), FILTER_VALIDATE_BOOL);
    }

    public function shouldAllowInlineScriptAttributes(): bool
    {
        return filter_var((string) env('CSP_ALLOW_INLINE_SCRIPT_ATTR', 'true'), FILTER_VALIDATE_BOOL);
    }

    /** @return list<string> */
    private function parseEnvList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[\s,]+/', $value) ?: [];

        return array_values(array_filter(array_map(static fn (string $item): string => trim($item), $parts)));
    }
}
