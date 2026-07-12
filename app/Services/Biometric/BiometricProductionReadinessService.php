<?php

declare(strict_types=1);

namespace App\Services\Biometric;

use App\Models\BiometricTemplateModel;
use App\Services\Biometric\DeepFace\DeepFaceApiClient;
use App\Support\BootstrapEnv;
use App\Support\SensitiveDataSanitizer;

class BiometricProductionReadinessService
{
    private BiometricTemplateModel $templateModel;
    private DeepFaceApiClient $apiClient;

    public function __construct()
    {
        $this->templateModel = new BiometricTemplateModel();
        $this->apiClient = new DeepFaceApiClient(new \App\Models\SettingModel());
    }

    public function diagnostics(bool $withConnections = true): array
    {
        $activeTemplates = $this->templateModel
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->findAll();

        $fileDiagnostics = $this->faceFileDiagnostics($activeTemplates);
        $apiDiagnostics = $this->deepFaceDiagnostics($withConnections);

        $checks = [
            'api' => $apiDiagnostics,
            'storage' => $fileDiagnostics['storage'],
            'templates' => $fileDiagnostics['templates'],
            'orphans' => $fileDiagnostics['orphans'],
            'settings' => $this->settingsDiagnostics(),
        ];

        $status = 'ok';
        foreach ($checks as $check) {
            if (($check['status'] ?? 'error') === 'error') {
                $status = 'error';
                break;
            }
            if (($check['status'] ?? 'ok') === 'warning') {
                $status = 'warning';
            }
        }

        return [
            'status' => $status,
            'timestamp' => gmdate(DATE_ATOM),
            'checks' => $checks,
            'summary' => [
                'active_face_templates' => count($activeTemplates),
                'missing_local_files' => $fileDiagnostics['summary']['missing_local_files'],
                'orphan_files' => $fileDiagnostics['summary']['orphan_files'],
                'api_timeout_seconds' => $this->apiClient->getTimeout(),
                'api_retry_attempts' => $this->apiClient->getRetryAttempts(),
            ],
        ];
    }

    public function cleanupOrphanFaceFiles(bool $dryRun = true): array
    {
        $diag = $this->faceFileDiagnostics($this->templateModel
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->findAll());

        $deleted = [];
        foreach ($diag['orphan_paths'] as $path) {
            if ($dryRun) {
                continue;
            }

            if (is_file($path) && @unlink($path)) {
                $deleted[] = $path;
                $this->removeEmptyParentDirectories(dirname($path));
            }
        }

        return [
            'success' => true,
            'dry_run' => $dryRun,
            'orphan_candidates' => $diag['orphan_paths'],
            'deleted' => $deleted,
            'deleted_count' => count($deleted),
        ];
    }

    private function settingsDiagnostics(): array
    {
        $apiUrl = (string) (BootstrapEnv::get('DEEPFACE_API_URL', '') ?: '');
        $apiKey = (string) (BootstrapEnv::get('DEEPFACE_API_KEY', '') ?: '');

        $issues = [];
        if ($apiUrl === '') {
            $issues[] = 'DEEPFACE_API_URL ausente';
        }
        if ($apiKey === '') {
            $issues[] = 'DEEPFACE_API_KEY ausente';
        }
        if ($this->apiClient->getTimeout() < 10) {
            $issues[] = 'timeout muito baixo para produção';
        }

        return [
            'status' => empty($issues) ? 'ok' : 'warning',
            'label' => 'biometric_settings',
            'message' => empty($issues) ? 'Parâmetros biométricos mínimos presentes.' : implode('; ', $issues),
            'meta' => [
                'api_url' => SensitiveDataSanitizer::sanitizeForLogs($apiUrl, 'api_url'),
                'timeout' => $this->apiClient->getTimeout(),
                'retry_attempts' => $this->apiClient->getRetryAttempts(),
            ],
        ];
    }

    private function deepFaceDiagnostics(bool $withConnections): array
    {
        if (! $withConnections) {
            $configuredUrl = $this->apiClient->getApiUrl();
            return [
                'status' => $configuredUrl !== '' ? 'ok' : 'warning',
                'label' => 'deepface_api',
                'message' => $configuredUrl !== '' ? 'DeepFace configurado; diagnóstico remoto não solicitado.' : 'Diagnóstico remoto não executado.',
                'meta' => [
                    'api_url' => SensitiveDataSanitizer::sanitizeForLogs($configuredUrl, 'api_url'),
                    'timeout' => $this->apiClient->getTimeout(),
                    'retry_attempts' => $this->apiClient->getRetryAttempts(),
                ],
            ];
        }

        $startedAt = microtime(true);
        $health = $this->apiClient->health();
        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        $status = ($health['success'] ?? false) ? 'ok' : 'error';
        $message = ($health['success'] ?? false)
            ? 'DeepFace operacional.'
            : (($health['error'] ?? null) ?: 'DeepFace indisponível.');

        return [
            'status' => $status,
            'label' => 'deepface_api',
            'message' => $message,
            'meta' => [
                'api_url' => SensitiveDataSanitizer::sanitizeForLogs($this->apiClient->getApiUrl(), 'api_url'),
                'timeout' => $this->apiClient->getTimeout(),
                'retry_attempts' => $this->apiClient->getRetryAttempts(),
                'latency_ms' => $latencyMs,
                'version' => $health['version'] ?? null,
                'models_loaded' => $health['models_loaded'] ?? null,
            ],
        ];
    }

    /**
     * @param array<int, object|array<string,mixed>> $templates
     */
    private function faceFileDiagnostics(array $templates): array
    {
        $storagePath = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces';
        $storageExists = is_dir($storagePath);
        $storageWritable = $storageExists && is_writable($storagePath);

        $expectedPaths = [];
        $missingFiles = [];
        foreach ($templates as $template) {
            $filePath = is_object($template) ? (string) ($template->file_path ?? '') : (string) ($template['file_path'] ?? '');
            if ($filePath === '') {
                continue;
            }
            $expectedPaths[] = $filePath;
            if (! is_file($filePath)) {
                $missingFiles[] = $filePath;
            }
        }

        $actualFiles = [];
        if ($storageExists) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($storagePath, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $actualFiles[] = $file->getPathname();
                }
            }
        }

        $orphanFiles = array_values(array_diff($actualFiles, $expectedPaths));

        return [
            'storage' => [
                'status' => ($storageExists && $storageWritable) ? 'ok' : 'error',
                'label' => 'face_storage',
                'message' => ($storageExists && $storageWritable) ? 'Storage facial gravável.' : 'Storage facial ausente ou sem permissão de escrita.',
                'meta' => ['path' => SensitiveDataSanitizer::maskPath($storagePath)],
            ],
            'templates' => [
                'status' => empty($missingFiles) ? 'ok' : 'warning',
                'label' => 'face_templates',
                'message' => empty($missingFiles) ? 'Templates ativos com arquivos locais presentes.' : 'Há templates ativos sem arquivo local correspondente.',
                'meta' => ['missing_files' => array_map(static fn (string $path): string => SensitiveDataSanitizer::maskPath($path), $missingFiles)],
            ],
            'orphans' => [
                'status' => empty($orphanFiles) ? 'ok' : 'warning',
                'label' => 'face_storage_orphans',
                'message' => empty($orphanFiles) ? 'Nenhum arquivo facial órfão detectado.' : 'Há arquivos faciais sem vínculo ativo no banco.',
                'meta' => ['orphan_files' => array_map(static fn (string $path): string => SensitiveDataSanitizer::maskPath($path), $orphanFiles)],
            ],
            'orphan_paths' => $orphanFiles,
            'summary' => [
                'missing_local_files' => count($missingFiles),
                'orphan_files' => count($orphanFiles),
            ],
        ];
    }

    private function removeEmptyParentDirectories(string $directory): void
    {
        $base = rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'faces';
        $current = $directory;

        while (str_starts_with($current, $base) && $current !== $base) {
            $files = @scandir($current);
            if (!is_array($files) || count($files) > 2) {
                break;
            }
            @rmdir($current);
            $current = dirname($current);
        }
    }
}
