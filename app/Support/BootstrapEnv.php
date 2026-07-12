<?php

declare(strict_types=1);

namespace App\Support;

final class BootstrapEnv
{
    public static function get(string $key, ?string $default = null, array $aliases = []): ?string
    {
        $candidates = array_values(array_unique(array_filter(array_merge([$key], $aliases), static fn ($item) => is_string($item) && $item !== '')));

        foreach ($candidates as $candidate) {
            $value = null;

            if (function_exists('env')) {
                $envValue = env($candidate);
                if ($envValue !== null) {
                    $value = $envValue;
                }
            }

            if ($value === null && array_key_exists($candidate, $_ENV)) {
                $value = $_ENV[$candidate];
            }

            if ($value === null && array_key_exists($candidate, $_SERVER)) {
                $value = $_SERVER[$candidate];
            }

            if ($value === null) {
                $native = getenv($candidate);
                if ($native !== false) {
                    $value = $native;
                }
            }

            if ($value !== null) {
                $normalized = trim((string) $value);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return $default;
    }

    public static function bool(string $key, bool $default = false, array $aliases = []): bool
    {
        $value = self::get($key, null, $aliases);
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    public static function csv(string $key, array $default = [], array $aliases = []): array
    {
        $value = self::get($key, null, $aliases);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return array_values(array_filter(array_map(static fn (string $item): string => trim($item), explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }

    public static function int(string $key, ?int $default = null, array $aliases = []): ?int
    {
        $value = self::get($key, null, $aliases);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        return (int) $value;
    }

    public static function environment(string $default = 'production'): string
    {
        return self::get('APP_ENV', self::get('CI_ENVIRONMENT', $default), ['app.env']) ?? $default;
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    public static function baseUrl(?string $default = null): ?string
    {
        return self::get('app.baseURL', $default, ['APP_BASE_URL', 'APP_URL']);
    }

    public static function sessionSavePath(string $rootPath): string
    {
        $configured = self::get('session.savePath', null, ['SESSION_SAVE_PATH']);
        if ($configured === null) {
            return rtrim($rootPath, '/\\') . '/writable/session';
        }

        if (self::isAbsolutePath($configured)) {
            return $configured;
        }

        return rtrim($rootPath, '/\\') . '/' . ltrim($configured, '/\\');
    }

    public static function cookieSecure(?string $baseUrl = null): bool
    {
        $explicit = self::get('app.cookieSecure', null, ['session.cookieSecure', 'cookie.secure', 'COOKIE_SECURE']);
        if ($explicit !== null) {
            $normalized = strtolower(trim($explicit));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return self::isHttpsUrl($baseUrl ?? self::baseUrl());
    }


    public static function sessionMatchIp(bool $default = false): bool
    {
        return self::bool('session.matchIP', $default, ['SESSION_MATCH_IP']);
    }

    public static function encryptionKey(): ?string
    {
        return self::get('encryption.key', null, ['ENCRYPTION_KEY']);
    }

    public static function requiredSecrets(): array
    {
        return [
            'encryption.key' => self::encryptionKey(),
            'JWT_SECRET_KEY' => self::get('JWT_SECRET_KEY'),
            'QR_SECRET_KEY' => self::get('QR_SECRET_KEY'),
            'DEEPFACE_API_KEY' => self::get('DEEPFACE_API_KEY'),
        ];
    }

    public static function isHttpsUrl(?string $url): bool
    {
        if (!is_string($url) || trim($url) === '') {
            return false;
        }

        $scheme = parse_url(trim($url), PHP_URL_SCHEME);
        return is_string($scheme) && strtolower($scheme) === 'https';
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\\/]#', $path) === 1;
    }
}
