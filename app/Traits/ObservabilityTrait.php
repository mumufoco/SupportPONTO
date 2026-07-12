<?php

namespace App\Traits;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

trait ObservabilityTrait
{
    protected function getRequestId(?RequestInterface $request = null): string
    {
        if (isset($_SERVER['SUPPORTPONTO_REQUEST_ID']) && is_string($_SERVER['SUPPORTPONTO_REQUEST_ID'])) {
            return $_SERVER['SUPPORTPONTO_REQUEST_ID'];
        }

        $request ??= $this->request ?? null;
        $incoming = $request?->getHeaderLine('X-Request-ID');

        if (is_string($incoming) && trim($incoming) !== '') {
            return $_SERVER['SUPPORTPONTO_REQUEST_ID'] = substr(trim($incoming), 0, 120);
        }

        return $_SERVER['SUPPORTPONTO_REQUEST_ID'] = bin2hex(random_bytes(16));
    }

    protected function attachResponseContext(ResponseInterface $response, bool $sensitive = false): ResponseInterface
    {
        $requestId = $this->getRequestId();
        $response->setHeader('X-Request-ID', $requestId);
        $response->setHeader('X-Correlation-ID', function_exists('correlation_id') ? correlation_id() : $requestId);
        $response->setHeader('Vary', 'Accept, Authorization, Cookie');

        if ($sensitive) {
            $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->setHeader('Pragma', 'no-cache');
            $response->setHeader('Expires', '0');
        }

        return $response;
    }

    protected function apiSuccessPayload(array $data = [], string $message = 'OK', string $code = 'ok'): array
    {
        return [
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'request_id' => $this->getRequestId(),
                'correlation_id' => function_exists('correlation_id') ? correlation_id() : $this->getRequestId(),
                'timestamp' => gmdate(DATE_ATOM),
            ],
        ];
    }

    protected function apiErrorPayload(string $code, string $message, ?array $errors = null): array
    {
        $payload = [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'meta' => [
                'request_id' => $this->getRequestId(),
                'correlation_id' => function_exists('correlation_id') ? correlation_id() : $this->getRequestId(),
                'timestamp' => gmdate(DATE_ATOM),
            ],
        ];

        if ($errors !== null && $errors !== []) {
            $payload['errors'] = $this->sanitizeForLog($errors);
        }

        return $payload;
    }

    protected function logSecurityEvent(string $level, string $message, array $context = []): void
    {
        $payload = $this->sanitizeForLog(array_merge($this->requestContext(), $context));
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        log_message($level, $encoded === false ? $message : $message . ' | context=' . $encoded);
    }

    protected function requestContext(array $extra = []): array
    {
        $request = $this->request ?? null;
        $sessionUserId = property_exists($this, 'session') && $this->session ? $this->session->get('user_id') : null;
        $apiEmployeeId = Services::apiRequestAuthContext()->getEmployeeId();

        return array_merge([
            'request_id' => $this->getRequestId($request),
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : $this->getRequestId($request),
            'method' => $request?->getMethod(),
            'path' => $request?->getUri()?->getPath(),
            'ip' => $request?->getIPAddress(),
            'user_agent_hash' => $this->hashValue((string) ($request?->getUserAgent()?->getAgentString() ?? '')),
            'user_id' => $sessionUserId ?? $apiEmployeeId,
        ], $extra);
    }

    protected function sanitizeForLog(mixed $value, ?string $key = null): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $itemKey => $itemValue) {
                $sanitized[$itemKey] = $this->sanitizeForLog($itemValue, is_string($itemKey) ? $itemKey : null);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return $this->sanitizeForLog((array) $value, $key);
        }

        $normalizedKey = strtolower((string) $key);
        $isSensitiveKey = $normalizedKey !== '' && preg_match('/password|token|secret|authorization|cookie|fingerprint|biometric|face|photo|image|cpf|pis|ctps|rg|email|phone/', $normalizedKey);

        if ($isSensitiveKey) {
            return $this->maskValue($value, $normalizedKey);
        }

        if (is_string($value)) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $this->maskEmail($value);
            }

            if (strlen($value) > 180) {
                return '[omitted:' . strlen($value) . ' chars]';
            }
        }

        return $value;
    }

    protected function maskValue(mixed $value, string $key = ''): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_scalar($value)) {
            if (str_contains($key, 'email')) {
                return $this->maskEmail((string) $value);
            }

            return '[redacted:' . $this->hashValue((string) $value) . ']';
        }

        return '[redacted]';
    }

    protected function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '[redacted:' . $this->hashValue($email) . ']';
        }

        [$user, $domain] = explode('@', $email, 2);
        $visible = substr($user, 0, 1);

        return $visible . '***@' . $domain;
    }

    protected function hashValue(string $value): string
    {
        return substr(hash('sha256', $value), 0, 12);
    }
}
