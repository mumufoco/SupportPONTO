<?php

namespace App\Services\Warning;

class WarningPdfStorageService
{
    private const RELATIVE_DIRECTORY = 'uploads/warnings/pdfs/';

    public function __construct(private readonly string $writePath = WRITEPATH)
    {
        helper('file_upload');
    }

    public function outputDirectory(): string
    {
        return rtrim($this->writePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::RELATIVE_DIRECTORY);
    }

    /**
     * @return array<int,string>
     */
    public function allowedRoots(): array
    {
        $base = $this->outputDirectory();

        return [realpath($base) ?: $base];
    }

    public function ensureDirectory(): void
    {
        $directory = $this->outputDirectory();
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        @chmod($directory, 0750);
    }

    public function buildStoredPath(string $filename): string
    {
        return self::RELATIVE_DIRECTORY . ltrim($filename, "/\\");
    }

    public function normalizeStoredPath(?string $storedPath): ?string
    {
        $storedPath = trim((string) $storedPath);
        if ($storedPath === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $storedPath);
        $normalized = ltrim($normalized, '/');
        if (! str_starts_with($normalized, self::RELATIVE_DIRECTORY)) {
            return null;
        }

        if (str_contains($normalized, '../') || str_contains($normalized, '/..') || str_contains($normalized, "\0")) {
            return null;
        }

        $basename = basename($normalized);
        if ($basename === '' || ! preg_match('/\.pdf$/i', $basename)) {
            return null;
        }

        return $normalized;
    }

    public function resolveAbsolutePath(?string $storedPath): ?string
    {
        $normalized = $this->normalizeStoredPath($storedPath);
        if ($normalized === null) {
            return null;
        }

        $candidate = rtrim($this->writePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);

        return supportponto_safe_download_path($candidate, $this->allowedRoots());
    }

    public function toStoredPath(?string $absolutePath): ?string
    {
        $absolutePath = trim((string) $absolutePath);
        if ($absolutePath === '') {
            return null;
        }

        $safe = supportponto_safe_download_path($absolutePath, $this->allowedRoots());
        if ($safe === null) {
            return null;
        }

        $normalizedSafe = str_replace('\\', '/', $safe);
        $normalizedWrite = rtrim(str_replace('\\', '/', rtrim($this->writePath, DIRECTORY_SEPARATOR)), '/');
        $prefix = $normalizedWrite . '/';
        if (! str_starts_with($normalizedSafe, $prefix)) {
            return null;
        }

        return ltrim(substr($normalizedSafe, strlen($prefix)), '/');
    }

    public function deleteStoredFile(?string $storedPath): void
    {
        $absolute = $this->resolveAbsolutePath($storedPath);
        if ($absolute !== null && is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
