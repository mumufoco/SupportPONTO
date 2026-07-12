<?php

declare(strict_types=1);

if (!function_exists('asset_version')) {
    /**
     * Retorna a versão do asset baseada em filemtime quando o arquivo existir.
     */
    function asset_version(string $path): ?string
    {
        $normalized = ltrim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
        $absolutePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalized;

        if (!is_file($absolutePath)) {
            return null;
        }

        $version = @filemtime($absolutePath);

        return $version === false ? null : (string) $version;
    }
}

if (!function_exists('asset_url')) {
    /**
     * Gera URL versionada para assets locais usando filemtime como cache busting.
     */
    function asset_url(string $path): string
    {
        $pathPart = parse_url($path, PHP_URL_PATH) ?? $path;
        $normalizedPath = ltrim($pathPart, '/');
        $url = base_url($normalizedPath);

        $query = [];
        parse_str((string) parse_url($path, PHP_URL_QUERY), $query);

        $version = asset_version($normalizedPath);
        if ($version !== null) {
            $query['v'] = $version;
        }

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}
