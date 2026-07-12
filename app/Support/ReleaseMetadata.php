<?php

declare(strict_types=1);

namespace App\Support;

final class ReleaseMetadata
{
    /**
     * @return array<string,mixed>
     */
    public static function read(): array
    {
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        // Defaults neutros — NÃO devem fixar artifact_type/installer (conceito legado de
        // source-package vs release-package, removido na limpeza completa entre v1.1.498
        // e v1.1.500). Fixar essas chaves aqui as reinjetava via array_merge mesmo quando
        // release.json deixava de declará-las, mascarando a simplificação do modelo.
        $default = [
            'version' => '1.1.500',
            'release' => 'v1.1.500',
            'package' => 500,
            'name' => 'SupportPONTO',
            'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'production',
            'generated_at' => '2026-06-06T00:00:00+00:00',
            'deployable' => true,
        ];

        $releaseFile = rtrim(ROOTPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'release.json';
        if (!is_file($releaseFile)) {
            return $cached = $default;
        }

        $decoded = json_decode((string) file_get_contents($releaseFile), true);
        if (!is_array($decoded)) {
            return $cached = $default;
        }

        return $cached = array_merge($default, $decoded);
    }

    public static function version(): string
    {
        return (string) (self::read()['version'] ?? '1.1.500');
    }

    public static function releaseLabel(): string
    {
        $metadata = self::read();
        $version = (string) ($metadata['version'] ?? '1.1.500');
        $label = (string) ($metadata['release'] ?? '');

        return str_starts_with($label, 'v') ? $label : 'v' . $version;
    }

    public static function package(): int
    {
        $metadata = self::read();
        $package = $metadata['package'] ?? null;
        if (is_numeric($package)) {
            return (int) $package;
        }

        return (int) preg_replace('/^1\.1\./', '', self::version());
    }
}
