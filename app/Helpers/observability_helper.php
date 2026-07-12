<?php

/**
 * Helper de observabilidade e logging estruturado.
 */

if (!function_exists('correlation_id')) {
    function correlation_id(): string
    {
        static $generatedId = null;

        if (defined('CORRELATION_ID')) {
            return CORRELATION_ID;
        }

        if (!empty($_SERVER['SUPPORTPONTO_REQUEST_ID']) && is_string($_SERVER['SUPPORTPONTO_REQUEST_ID'])) {
            return $_SERVER['SUPPORTPONTO_REQUEST_ID'];
        }

        if ($generatedId === null) {
            $generatedId = !empty($_SERVER['SUPPORTPONTO_GENERATED_REQUEST_ID']) && is_string($_SERVER['SUPPORTPONTO_GENERATED_REQUEST_ID'])
                ? $_SERVER['SUPPORTPONTO_GENERATED_REQUEST_ID']
                : bin2hex(random_bytes(16));

            $_SERVER['SUPPORTPONTO_GENERATED_REQUEST_ID'] = $generatedId;
        }

        return $generatedId;
    }
}

if (!function_exists('supportponto_sanitize_log_context')) {
    function supportponto_sanitize_log_context(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (preg_match('/password|token|secret|authorization|cookie|fingerprint|biometric|face|photo|image|cpf|pis|ctps|rg|email|phone/', $normalizedKey)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = supportponto_sanitize_log_context($value);
                continue;
            }

            if (is_object($value)) {
                $sanitized[$key] = '[object:' . get_class($value) . ']';
                continue;
            }

            if (is_string($value) && strlen($value) > 250) {
                $sanitized[$key] = '[omitted:' . strlen($value) . ' chars]';
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}

if (!function_exists('log_structured')) {
    function log_structured(string $level, string $event, array $context = []): void
    {
        $payload = array_merge([
            'level'          => $level,
            'event'          => $event,
            'correlation_id' => correlation_id(),
            'timestamp'      => date('c'),
            'environment'    => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
        ], supportponto_sanitize_log_context($context));

        log_message($level, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('supportponto_log_event')) {
    function supportponto_log_event(string $level, string $domain, string $event, array $context = []): void
    {
        log_structured($level, trim($domain . '.' . $event, '.'), $context);
    }
}

if (!function_exists('supportponto_log_exception')) {
    function supportponto_log_exception(string $domain, string $event, Throwable $e, array $context = [], string $level = 'error'): void
    {
        supportponto_log_event($level, $domain, $event, array_merge($context, [
            'exception_class' => get_class($e),
            'exception_message' => $e->getMessage(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
        ]));
    }
}

if (!function_exists('log_punch_event')) {
    function log_punch_event(string $event, int $employeeId, int $nsr, array $extra = []): void
    {
        supportponto_log_event('info', 'timesheet', $event, array_merge([
            'employee_id' => $employeeId,
            'nsr' => $nsr,
        ], $extra));
    }
}

if (!function_exists('log_security_event')) {
    function log_security_event(string $event, array $context = [], string $level = 'warning'): void
    {
        supportponto_log_event($level, 'security', $event, array_merge([
            'ip' => (string) (\Config\Services::request()->getIPAddress() ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
        ], $context));
    }
}


if (!function_exists('supportponto_safe_error_log')) {
    function supportponto_safe_error_log(string $message): void
    {
        try {
            $dir = defined('WRITEPATH') ? WRITEPATH . 'logs/' : dirname(__DIR__, 2) . '/writable/logs/';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $file = rtrim($dir, '/\\') . '/bootstrap-' . date('Y-m-d') . '.log';
            @error_log('[' . date('c') . '] ' . $message . PHP_EOL, 3, $file);
        } catch (Throwable $e) {
            @error_log('[SupportPONTO bootstrap] ' . $message);
        }
    }
}

if (!function_exists('supportponto_bootstrap_log')) {
    function supportponto_bootstrap_log(string $event, array $context = [], string $level = 'error'): void
    {
        $payload = [
            'level' => $level,
            'event' => 'bootstrap.' . $event,
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : bin2hex(random_bytes(8)),
            'timestamp' => date('c'),
            'context' => supportponto_sanitize_log_context($context),
        ];

        supportponto_safe_error_log(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('supportponto_public_error_reference')) {
    function supportponto_public_error_reference(): string
    {
        return strtoupper(substr(correlation_id(), 0, 12));
    }
}

if (!function_exists('supportponto_public_error_message')) {
    function supportponto_public_error_message(string $message = 'Operação indisponível no momento.'): string
    {
        return trim($message) . ' Ref: ' . supportponto_public_error_reference();
    }
}

if (!function_exists('supportponto_operational_event')) {
    function supportponto_operational_event(string $level, string $domain, string $event, array $context = []): void
    {
        try {
            (new \App\Services\Observability\OperationalTelemetryService())->record($level, $domain, $event, $context);
        } catch (Throwable $e) {
            supportponto_log_event('warning', 'observability', 'operational_event_failed', [
                'exception_class' => get_class($e),
                'event' => $event,
            ]);
        }
    }
}
