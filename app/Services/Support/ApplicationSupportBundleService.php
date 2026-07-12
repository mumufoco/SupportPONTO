<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Services\Health\SystemHealthCheckService;
use App\Services\Observability\OperationalTelemetryService;
use App\Support\ReleaseMetadata;
use App\Support\SensitiveDataSanitizer;
use ZipArchive;
use Throwable;

/**
 * Gera pacote de suporte da aplicação em produção, sem segredos.
 * Diferente do bundle do instalador, este cobre o sistema em operação.
 */
class ApplicationSupportBundleService
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?: (WRITEPATH . 'support-bundles'), DIRECTORY_SEPARATOR);
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }

        $bundleId = 'app-support-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $workDir = $this->directory . DIRECTORY_SEPARATOR . $bundleId;
        @mkdir($workDir, 0775, true);

        $files = [];
        $this->writeJson($workDir . '/manifest.json', $this->manifest($bundleId));
        $files[] = 'manifest.json';

        $this->writeJson($workDir . '/health-detailed.json', $this->safeCall(fn () => (new SystemHealthCheckService())->detailedHealth()));
        $files[] = 'health-detailed.json';

        $this->writeJson($workDir . '/release.json', $this->readJsonFile(ROOTPATH . 'release.json'));
        $files[] = 'release.json';

        $this->writeJson($workDir . '/public-version.json', $this->readJsonFile(FCPATH . 'version.json'));
        $files[] = 'public-version.json';

        $this->writeJson($workDir . '/environment-summary.json', $this->environmentSummary());
        $files[] = 'environment-summary.json';

        $this->writeText($workDir . '/latest-app-log-tail.txt', implode(PHP_EOL, $this->latestLogTail()) . PHP_EOL);
        $files[] = 'latest-app-log-tail.txt';

        $this->writeText($workDir . '/operational-events-tail.ndjson', implode(PHP_EOL, (new OperationalTelemetryService())->tail(300)) . PHP_EOL);
        $files[] = 'operational-events-tail.ndjson';

        foreach ($this->optionalFiles() as $source => $target) {
            if (is_file($source) && is_readable($source)) {
                $content = (string) file_get_contents($source);
                if (str_ends_with($target, '.json')) {
                    $decoded = json_decode($content, true);
                    $this->writeJson($workDir . '/' . $target, is_array($decoded) ? $decoded : ['raw' => $content]);
                } else {
                    $this->writeText($workDir . '/' . $target, $this->sanitizeText($content));
                }
                $files[] = $target;
            }
        }

        $zipPath = $this->directory . DIRECTORY_SEPARATOR . $bundleId . '.zip';
        $zipCreated = $this->zipDirectory($workDir, $zipPath);

        $report = [
            'success' => $zipCreated,
            'bundle_id' => $bundleId,
            'bundle_path' => $zipCreated ? $zipPath : null,
            'work_directory' => $workDir,
            'files' => $files,
            'generated_at' => gmdate(DATE_ATOM),
            'secrets_sanitized' => true,
        ];

        $this->writeJson($this->directory . DIRECTORY_SEPARATOR . 'application-support-bundle-last.json', $report);

        return $report;
    }

    /** @return array<string,mixed> */
    private function manifest(string $bundleId): array
    {
        return [
            'bundle_id' => $bundleId,
            'type' => 'application_support_bundle',
            'application' => 'SupportPONTO',
            'release' => ReleaseMetadata::read(),
            'generated_at' => gmdate(DATE_ATOM),
            'request_id' => $_SERVER['SUPPORTPONTO_REQUEST_ID'] ?? null,
            'correlation_id' => function_exists('correlation_id') ? correlation_id() : null,
            'contains_secrets' => false,
            'sanitization' => 'SensitiveDataSanitizer::sanitizeForLogs',
        ];
    }

    /** @return array<string,mixed> */
    private function environmentSummary(): array
    {
        return SensitiveDataSanitizer::sanitizeForLogs([
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : 'unknown',
            'timezone' => date_default_timezone_get(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'extensions' => [
                'pgsql' => extension_loaded('pgsql'),
                'pdo_pgsql' => extension_loaded('pdo_pgsql'),
                'zip' => extension_loaded('zip'),
                'intl' => extension_loaded('intl'),
                'mbstring' => extension_loaded('mbstring'),
                'fileinfo' => extension_loaded('fileinfo'),
            ],
            'paths' => [
                'root' => ROOTPATH,
                'fcp' => FCPATH,
                'writable' => WRITEPATH,
            ],
        ]);
    }

    /** @return array<string,string> */
    private function optionalFiles(): array
    {
        return [
            WRITEPATH . 'installer/schema-health-last.json' => 'installer-schema-health-last.json',
            WRITEPATH . 'installer/migration-compatibility-last.json' => 'installer-migration-compatibility-last.json',
            WRITEPATH . 'installer/biometric-doctor-last.json' => 'installer-biometric-doctor-last.json',
            ROOTPATH . 'install/runtime/dependencies.catalog.json' => 'dependencies.catalog.json',
        ];
    }

    /** @return list<string> */
    private function latestLogTail(): array
    {
        $latest = null;
        $latestTime = 0;
        foreach (glob(WRITEPATH . 'logs/log-*.log') ?: [] as $file) {
            $mtime = @filemtime($file) ?: 0;
            if ($mtime >= $latestTime) {
                $latestTime = $mtime;
                $latest = $file;
            }
        }
        if ($latest === null || ! is_readable($latest)) {
            return [];
        }

        $lines = @file($latest, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! is_array($lines)) {
            return [];
        }

        return array_map(fn (string $line): string => $this->sanitizeText($line), array_slice($lines, -400));
    }

    /** @return array<string,mixed> */
    private function readJsonFile(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['available' => false, 'path' => SensitiveDataSanitizer::maskPath($path)];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return SensitiveDataSanitizer::sanitizeForLogs(is_array($decoded) ? $decoded : ['available' => true, 'invalid_json' => true]);
    }

    /** @return array<string,mixed> */
    private function safeCall(callable $callback): array
    {
        try {
            $result = $callback();
            return SensitiveDataSanitizer::sanitizeForLogs(is_array($result) ? $result : ['result' => $result]);
        } catch (Throwable $e) {
            return ['status' => 'error', 'message' => 'Falha ao coletar diagnóstico.', 'exception' => get_class($e)];
        }
    }

    /** @param array<string,mixed> $data */
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode(SensitiveDataSanitizer::sanitizeForLogs($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }

    private function writeText(string $path, string $content): void
    {
        file_put_contents($path, $this->sanitizeText($content));
    }

    private function sanitizeText(string $content): string
    {
        return (string) SensitiveDataSanitizer::sanitizeForLogs($content);
    }

    private function zipDirectory(string $directory, string $zipPath): bool
    {
        if (! class_exists(ZipArchive::class)) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                $zip->addFile($file, basename($file));
            }
        }

        return $zip->close();
    }
}
