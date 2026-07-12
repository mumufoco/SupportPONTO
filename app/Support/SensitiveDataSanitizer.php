<?php

declare(strict_types=1);

namespace App\Support;

final class SensitiveDataSanitizer
{
    /** @var array<int,string> */
    private const SENSITIVE_KEYS = [
        'password',
        'password_plain',
        'current_password',
        'new_password',
        'confirm_password',
        'token',
        'refresh_token',
        'access_token',
        'jwt',
        'secret',
        'secret_key',
        'jwt_secret_key',
        'qr_secret_key',
        'deepface_api_key',
        'authorization',
        'cookie',
        'set-cookie',
        'template_data',
        'face_encoding',
        'biometric_data',
        'photo',
        'image',
        'image_base64',
        'photo_base64',
        'user_agent',
        'ip_address',
        'file_path',
        'face_path',
    ];

    public static function sanitizeForLogs(mixed $value, ?string $key = null): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $itemKey => $itemValue) {
                $normalizedKey = is_string($itemKey) ? $itemKey : null;
                $result[$itemKey] = self::sanitizeForLogs($itemValue, $normalizedKey);
            }
            return $result;
        }

        if (is_object($value)) {
            $result = [];
            foreach (get_object_vars($value) as $itemKey => $itemValue) {
                $result[$itemKey] = self::sanitizeForLogs($itemValue, (string) $itemKey);
            }
            return $result;
        }

        if (! is_string($value)) {
            return $value;
        }

        if ($key !== null && self::isSensitiveKey($key)) {
            return self::redactForKey($value, $key);
        }

        return self::sanitizeFreeText($value);
    }

    public static function sanitizeForTelemetry(mixed $value): mixed
    {
        return self::sanitizeForLogs($value);
    }

    public static function maskPath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $trimmed);
        $parts = array_values(array_filter(explode('/', $normalized), static fn ($part) => $part !== ''));

        if ($parts === []) {
            return '[redacted-path]';
        }

        $count = count($parts);
        if ($count === 1) {
            return '***/' . $parts[0];
        }

        return '***/' . $parts[$count - 2] . '/' . $parts[$count - 1];
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(trim($key));
        return in_array($normalized, self::SENSITIVE_KEYS, true)
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'biometric')
            || str_contains($normalized, 'template')
            || str_contains($normalized, 'encoding')
            || str_contains($normalized, 'photo')
            || str_contains($normalized, 'image')
            || str_contains($normalized, 'cpf')
            || str_contains($normalized, 'cnpj')
            || str_contains($normalized, 'path');
    }

    private static function redactForKey(string $value, string $key): string
    {
        $normalized = strtolower(trim($key));

        if (str_contains($normalized, 'email')) {
            return self::maskEmail($value);
        }
        if (str_contains($normalized, 'cpf')) {
            return self::maskCpf($value);
        }
        if (str_contains($normalized, 'cnpj')) {
            return self::maskCnpj($value);
        }
        if (str_contains($normalized, 'path')) {
            return self::maskPath($value);
        }
        if (str_contains($normalized, 'ip')) {
            return self::maskIp($value);
        }
        if (str_contains($normalized, 'user_agent')) {
            return '[redacted-user-agent]';
        }

        return self::redactOpaque($value);
    }

    private static function sanitizeFreeText(string $value): string
    {
        $sanitized = preg_replace_callback(
            '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            static fn (array $m): string => self::maskEmail($m[0]),
            $value
        ) ?? $value;

        $sanitized = preg_replace_callback(
            '/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/',
            static fn (array $m): string => self::maskCpf($m[0]),
            $sanitized
        ) ?? $sanitized;

        $sanitized = preg_replace_callback(
            '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/',
            static fn (array $m): string => self::maskCnpj($m[0]),
            $sanitized
        ) ?? $sanitized;

        $sanitized = preg_replace_callback(
            '/(?:[A-Za-z]:\\|\/)[^\s\"\']+/u',
            static fn (array $m): string => self::maskPath($m[0]),
            $sanitized
        ) ?? $sanitized;

        if (preg_match('/^[A-Za-z0-9+\/=]{64,}$/', $sanitized) === 1) {
            return self::redactOpaque($sanitized);
        }

        return $sanitized;
    }

    private static function redactOpaque(string $value): string
    {
        $length = strlen($value);
        if ($length <= 8) {
            return '[redacted]';
        }

        return substr($value, 0, 3) . str_repeat('*', max(4, $length - 6)) . substr($value, -3);
    }

    private static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '' || ! str_contains($email, '@')) {
            return '[redacted-email]';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localMasked = strlen($local) <= 2
            ? substr($local, 0, 1) . '*'
            : substr($local, 0, 2) . str_repeat('*', max(2, strlen($local) - 2));

        return $localMasked . '@' . $domain;
    }

    private static function maskCpf(string $cpf): string
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($digits) !== 11) {
            return '[redacted-cpf]';
        }

        return substr($digits, 0, 3) . '.***.***-**';
    }

    private static function maskCnpj(string $cnpj): string
    {
        $digits = preg_replace('/\D+/', '', $cnpj) ?? '';
        if (strlen($digits) !== 14) {
            return '[redacted-cnpj]';
        }

        return substr($digits, 0, 2) . '.***.***/****-**';
    }

    private static function maskIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '[redacted-ip]';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.*.*';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return ($parts[0] ?? '****') . ':' . ($parts[1] ?? '****') . ':****:****';
        }

        return '[redacted-ip]';
    }
}
