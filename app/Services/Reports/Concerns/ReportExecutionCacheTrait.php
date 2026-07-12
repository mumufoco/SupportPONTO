<?php

namespace App\Services\Reports\Concerns;

trait ReportExecutionCacheTrait
{
    protected function getCacheKey(string $type, array $filters): string
    {
        return 'report_' . $type . '_' . md5(json_encode($filters));
    }
    protected function getFromCache(string $key): ?array
    {
        $filepath = $this->cacheDir . $key . '.cache';
        if (! file_exists($filepath)) {
            return null;
        }

        if (time() - filemtime($filepath) > 3600) {
            unlink($filepath);

            return null;
        }

        return json_decode((string) file_get_contents($filepath), true);
    }
    protected function saveToCache(string $key, array $data): void
    {
        file_put_contents($this->cacheDir . $key . '.cache', json_encode($data));
    }

}
