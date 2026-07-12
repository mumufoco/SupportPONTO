<?php

namespace App\Services\Queue;

use Config\Queue as QueueConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class TemporaryFileCleanupService
{
    private QueueConfig $config;

    public function __construct(?QueueConfig $config = null)
    {
        $this->config = $config ?? config('Queue');
    }

    /**
     * Remove arquivos temporários e resultados antigos criados por jobs.
     *
     * @return array{success:bool,dry_run:bool,scanned:int,deleted:int,freed_bytes:int,errors:list<string>}
     */
    public function cleanup(bool $dryRun = true, ?int $temporaryTtlHours = null, ?int $resultTtlHours = null): array
    {
        $temporaryTtlHours = max(1, $temporaryTtlHours ?? $this->config->temporaryFilesTtlHours);
        $resultTtlHours = max(1, $resultTtlHours ?? $this->config->resultFilesTtlHours);

        $targets = [
            ['path' => WRITEPATH . 'cache', 'ttl' => $temporaryTtlHours, 'prefixes' => ['sp_', 'ci_session', 'php']],
            ['path' => WRITEPATH . 'uploads/tmp', 'ttl' => $temporaryTtlHours, 'prefixes' => []],
            ['path' => WRITEPATH . 'reports', 'ttl' => $resultTtlHours, 'prefixes' => []],
            ['path' => WRITEPATH . 'exports/reports', 'ttl' => $resultTtlHours, 'prefixes' => []],
            ['path' => sys_get_temp_dir(), 'ttl' => $temporaryTtlHours, 'prefixes' => ['sp_face_', 'supportponto_']],
        ];

        $scanned = 0;
        $deleted = 0;
        $freed = 0;
        $errors = [];

        foreach ($targets as $target) {
            $result = $this->cleanupDirectory(
                (string) $target['path'],
                (int) $target['ttl'],
                $target['prefixes'],
                $dryRun
            );

            $scanned += $result['scanned'];
            $deleted += $result['deleted'];
            $freed += $result['freed_bytes'];
            $errors = array_merge($errors, $result['errors']);
        }

        return [
            'success' => $errors === [],
            'dry_run' => $dryRun,
            'scanned' => $scanned,
            'deleted' => $deleted,
            'freed_bytes' => $freed,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<string> $allowedPrefixes
     * @return array{scanned:int,deleted:int,freed_bytes:int,errors:list<string>}
     */
    private function cleanupDirectory(string $directory, int $ttlHours, array $allowedPrefixes, bool $dryRun): array
    {
        if (! is_dir($directory) || ! is_readable($directory)) {
            return ['scanned' => 0, 'deleted' => 0, 'freed_bytes' => 0, 'errors' => []];
        }

        $threshold = time() - ($ttlHours * 3600);
        $scanned = 0;
        $deleted = 0;
        $freed = 0;
        $errors = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $name = $fileInfo->getFilename();
                $scanned++;

                if ($allowedPrefixes !== [] && ! $this->startsWithAny($name, $allowedPrefixes)) {
                    continue;
                }

                if ($fileInfo->getMTime() > $threshold) {
                    continue;
                }

                $size = max(0, (int) $fileInfo->getSize());
                if (! $dryRun) {
                    if (! @unlink($path)) {
                        $errors[] = 'Não foi possível remover arquivo temporário: ' . $path;
                        continue;
                    }
                }

                $deleted++;
                $freed += $size;
            }
        } catch (Throwable $exception) {
            $errors[] = sprintf('Falha ao limpar %s: %s', $directory, $exception->getMessage());
        }

        return ['scanned' => $scanned, 'deleted' => $deleted, 'freed_bytes' => $freed, 'errors' => $errors];
    }

    /**
     * @param list<string> $prefixes
     */
    private function startsWithAny(string $value, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($value, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
