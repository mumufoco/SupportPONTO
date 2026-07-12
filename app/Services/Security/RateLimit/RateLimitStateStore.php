<?php

namespace App\Services\Security\RateLimit;

use CodeIgniter\Cache\CacheInterface;

class RateLimitStateStore
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getBucket(string $cacheKey): ?array
    {
        $raw = $this->cache->get($cacheKey);
        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return [
                'count' => (int) ($raw['count'] ?? 0),
                'reset_at' => (int) ($raw['reset_at'] ?? 0),
            ];
        }

        return [
            'count' => (int) $raw,
            'reset_at' => 0,
        ];
    }

    public function saveBucket(string $cacheKey, array $bucket, int $ttlSeconds): bool
    {
        return $this->cache->save($cacheKey, $bucket, $ttlSeconds);
    }

    public function deleteBucket(string $cacheKey): bool
    {
        return $this->cache->delete($cacheKey);
    }

    public function clean(): bool
    {
        return $this->cache->clean();
    }

    public function resetSeconds(string $cacheKey): int
    {
        $info = $this->cache->getMetadata($cacheKey);
        if ($info && isset($info['expire'])) {
            return max(0, (int) $info['expire'] - time());
        }

        return 0;
    }
}
