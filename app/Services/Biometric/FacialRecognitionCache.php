<?php

namespace App\Services\Biometric;

use CodeIgniter\Cache\CacheInterface;

/**
 * Facial Recognition Cache Service
 *
 * Implements intelligent caching for facial recognition results to:
 * - Avoid redundant API calls to DeepFace
 * - Reduce recognition latency (~2s saved per cached recognition)
 * - Implement LRU (Least Recently Used) eviction
 * - Track cache hit/miss metrics
 */
class FacialRecognitionCache
{
    protected CacheInterface $cache;
    protected string $cachePrefix = 'facial_recognition_';
    protected int $cacheTTL = 300; // 5 minutes
    protected int $maxCacheEntries = 1000; // LRU limit
    protected int $failedAttemptTTL = 3600; // 1 hour for failed recognitions

    // Metrics
    protected string $metricsKey = 'facial_recognition_metrics';

    public function __construct()
    {
        $this->cache = \Config\Services::cache();
    }

    /**
     * Get cached recognition result
     *
     * @param string $imageHash SHA-256 hash of the image
     * @return array|null Cached result or null if not found
     */
    public function get(string $imageHash): ?array
    {
        $cacheKey = $this->cachePrefix . $imageHash;
        $result = $this->cache->get($cacheKey);

        if ($result !== null) {
            $this->incrementMetric('hits');
            log_message('debug', "Facial recognition cache HIT: {$imageHash}");

            // Update last accessed time for LRU
            $this->touchEntry($imageHash);

            return $result;
        }

        $this->incrementMetric('misses');
        log_message('debug', "Facial recognition cache MISS: {$imageHash}");

        return null;
    }

    /**
     * Store recognition result in cache
     *
     * @param string $imageHash SHA-256 hash of the image
     * @param array $result Recognition result from DeepFace
     * @param bool $isSuccess Whether recognition was successful
     * @return bool Success
     */
    public function set(string $imageHash, array $result, bool $isSuccess = true): bool
    {
        // Enforce LRU limit
        $this->enforceLRU();

        $cacheKey = $this->cachePrefix . $imageHash;

        // Use different TTL for successful vs failed recognitions
        $ttl = $isSuccess ? $this->cacheTTL : $this->failedAttemptTTL;

        // Add metadata
        $cacheData = [
            'result' => $result,
            'cached_at' => time(),
            'success' => $isSuccess,
        ];

        $saved = $this->cache->save($cacheKey, $cacheData, $ttl);

        if ($saved) {
            // Track this entry for LRU
            $this->trackEntry($imageHash);

            $this->incrementMetric('stores');
            log_message('info', "Facial recognition result cached: {$imageHash} (TTL: {$ttl}s)");
        }

        return $saved;
    }

    /**
     * Delete a specific cache entry
     *
     * @param string $imageHash
     * @return bool Success
     */
    public function delete(string $imageHash): bool
    {
        $cacheKey = $this->cachePrefix . $imageHash;

        $deleted = $this->cache->delete($cacheKey);

        if ($deleted) {
            $this->untrackEntry($imageHash);
        }

        return $deleted;
    }

    /**
     * Invalidate all cache entries for an employee
     *
     * Called when employee's facial template is updated
     *
     * @param int $employeeId
     * @return int Number of entries invalidated
     */
    public function invalidateEmployee(int $employeeId): int
    {
        // Get all tracked entries
        $entries = $this->getTrackedEntries();

        $invalidated = 0;

        foreach ($entries as $hash => $data) {
            // Check if this entry belongs to the employee
            $cacheKey = $this->cachePrefix . $hash;
            $cached = $this->cache->get($cacheKey);

            if ($cached && isset($cached['result']['employee_id']) && $cached['result']['employee_id'] === $employeeId) {
                $this->delete($hash);
                $invalidated++;
            }
        }

        log_message('info', "Invalidated {$invalidated} facial recognition cache entries for employee {$employeeId}");

        return $invalidated;
    }

    /**
     * Clear all facial recognition cache
     *
     * @return bool Success
     */
    public function clear(): bool
    {
        $entries = $this->getTrackedEntries();

        foreach ($entries as $hash => $data) {
            $this->delete($hash);
        }

        // Clear tracking
        $this->cache->delete($this->cachePrefix . 'tracked');

        // Reset metrics
        $this->cache->delete($this->metricsKey);

        log_message('info', 'Facial recognition cache cleared');

        return true;
    }

    /**
     * Get cache metrics
     *
     * @return array Cache statistics
     */
    public function getMetrics(): array
    {
        $metrics = $this->cache->get($this->metricsKey) ?? [
            'hits' => 0,
            'misses' => 0,
            'stores' => 0,
        ];

        $total = $metrics['hits'] + $metrics['misses'];
        $hitRate = $total > 0 ? ($metrics['hits'] / $total) * 100 : 0;

        $entries = $this->getTrackedEntries();

        return [
            'hits' => $metrics['hits'],
            'misses' => $metrics['misses'],
            'stores' => $metrics['stores'],
            'hit_rate' => round($hitRate, 2),
            'total_entries' => count($entries),
            'max_entries' => $this->maxCacheEntries,
            'cache_ttl' => $this->cacheTTL,
            'failed_attempt_ttl' => $this->failedAttemptTTL,
        ];
    }

    /**
     * Reset metrics
     *
     * @return bool Success
     */
    public function resetMetrics(): bool
    {
        return $this->cache->delete($this->metricsKey);
    }

    // ==================== LRU Implementation ====================

    /**
     * Track a cache entry for LRU
     *
     * @param string $hash
     * @return void
     */
    protected function trackEntry(string $hash): void
    {
        $entries = $this->getTrackedEntries();

        $entries[$hash] = [
            'created_at' => time(),
            'accessed_at' => time(),
            'access_count' => 1,
        ];

        $this->cache->save($this->cachePrefix . 'tracked', $entries, 0); // No expiration
    }

    /**
     * Update last accessed time (for LRU)
     *
     * @param string $hash
     * @return void
     */
    protected function touchEntry(string $hash): void
    {
        $entries = $this->getTrackedEntries();

        if (isset($entries[$hash])) {
            $entries[$hash]['accessed_at'] = time();
            $entries[$hash]['access_count']++;

            $this->cache->save($this->cachePrefix . 'tracked', $entries, 0);
        }
    }

    /**
     * Remove entry from tracking
     *
     * @param string $hash
     * @return void
     */
    protected function untrackEntry(string $hash): void
    {
        $entries = $this->getTrackedEntries();

        if (isset($entries[$hash])) {
            unset($entries[$hash]);
            $this->cache->save($this->cachePrefix . 'tracked', $entries, 0);
        }
    }

    /**
     * Get all tracked entries
     *
     * @return array
     */
    protected function getTrackedEntries(): array
    {
        return $this->cache->get($this->cachePrefix . 'tracked') ?? [];
    }

    /**
     * Enforce LRU limit
     *
     * Evicts least recently used entries when limit is reached
     *
     * @return int Number of entries evicted
     */
    protected function enforceLRU(): int
    {
        $entries = $this->getTrackedEntries();

        if (count($entries) < $this->maxCacheEntries) {
            return 0;
        }

        // Sort by last accessed time (oldest first)
        uasort($entries, function ($a, $b) {
            return $a['accessed_at'] <=> $b['accessed_at'];
        });

        // Calculate how many to evict (10% of max)
        $evictCount = (int)ceil($this->maxCacheEntries * 0.1);
        $evicted = 0;

        foreach (array_slice($entries, 0, $evictCount, true) as $hash => $data) {
            $this->delete($hash);
            $evicted++;
        }

        log_message('info', "LRU eviction: removed {$evicted} old entries");

        return $evicted;
    }

    // ==================== Metrics Tracking ====================

    /**
     * Increment a metric counter
     *
     * @param string $metric Metric name (hits, misses, stores)
     * @return void
     */
    protected function incrementMetric(string $metric): void
    {
        $metrics = $this->cache->get($this->metricsKey) ?? [
            'hits' => 0,
            'misses' => 0,
            'stores' => 0,
        ];

        if (isset($metrics[$metric])) {
            $metrics[$metric]++;
            $this->cache->save($this->metricsKey, $metrics, 0); // No expiration
        }
    }

    /**
     * Generate hash from image path or content
     *
     * @param string $imagePathOrContent Path to image file or image content
     * @param bool $isPath Whether input is a path (true) or content (false)
     * @return string SHA-256 hash
     */
    public static function hashImage(string $imagePathOrContent, bool $isPath = true): string
    {
        if ($isPath) {
            if (!file_exists($imagePathOrContent)) {
                throw new \RuntimeException("Image file not found: {$imagePathOrContent}");
            }
            return hash_file('sha256', $imagePathOrContent);
        }

        return hash('sha256', $imagePathOrContent);
    }
}
