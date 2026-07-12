<?php

declare(strict_types=1);

use App\Support\ReleaseMetadata;

if (!function_exists('app_release_metadata')) {
    /**
     * Retorna os metadados centralizados da release atual.
     *
     * @return array<string, mixed>
     */
    function app_release_metadata(): array
    {
        static $release;

        if ($release !== null) {
            return $release;
        }

        return $release = ReleaseMetadata::read();
    }
}

if (!function_exists('app_version')) {
    /**
     * Retorna a versão atual do sistema.
     */
    function app_version(bool $prefixV = true): string
    {
        $metadata = app_release_metadata();
        $version = (string) ($metadata['version'] ?? ReleaseMetadata::version());

        return $prefixV ? 'v' . $version : $version;
    }
}

if (!function_exists('app_release_value')) {
    /**
     * Recupera uma chave específica dos metadados de release.
     *
     * @return mixed|null
     */
    function app_release_value(string $key, $default = null)
    {
        $metadata = app_release_metadata();

        return $metadata[$key] ?? $default;
    }
}
