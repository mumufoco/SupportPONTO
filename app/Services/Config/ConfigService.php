<?php

namespace App\Services\Config;

use App\Models\SettingModel;
use CodeIgniter\Cache\CacheInterface;

/**
 * Configuration Service with Caching
 *
 * Provides cached access to system settings for better performance.
 * Settings are cached for 1 hour to avoid repeated database queries.
 */
class ConfigService
{
    protected SettingModel $settingModel;
    protected CacheInterface $cache;
    protected int $cacheTTL = 3600; // 1 hour

    protected string $cachePrefix = 'config_';

    public function __construct()
    {
        $this->settingModel = new SettingModel();
        $this->cache = \Config\Services::cache();
    }

    /**
     * Get configuration value by key
     *
     * Checks cache first, falls back to database if not cached
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null)
    {
        // Try cache first
        $cacheKey = $this->cachePrefix . $key;
        $value = $this->cache->get($cacheKey);

        if ($value !== null) {
            log_message('debug', "Config cache HIT: {$key}");
            return $value;
        }

        log_message('debug', "Config cache MISS: {$key}");

        // Not in cache, fetch from database
        $setting = $this->settingModel->where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        $value = $setting->value;

        // Store in cache
        $this->cache->save($cacheKey, $value, $this->cacheTTL);

        return $value;
    }

    /**
     * Get multiple configuration values at once
     *
     * More efficient than multiple get() calls
     *
     * @param array $keys List of configuration keys
     * @param array $defaults Default values indexed by key
     * @return array Configuration values indexed by key
     */
    public function getMany(array $keys, array $defaults = []): array
    {
        $result = [];
        $missingKeys = [];

        // Check cache for each key
        foreach ($keys as $key) {
            $cacheKey = $this->cachePrefix . $key;
            $value = $this->cache->get($cacheKey);

            if ($value !== null) {
                $result[$key] = $value;
            } else {
                $missingKeys[] = $key;
            }
        }

        // Fetch missing keys from database in single query
        if (!empty($missingKeys)) {
            $settings = $this->settingModel->whereIn('key', $missingKeys)->findAll();

            foreach ($settings as $setting) {
                $value = $setting->value;
                $result[$setting->key] = $value;

                // Cache it
                $cacheKey = $this->cachePrefix . $setting->key;
                $this->cache->save($cacheKey, $value, $this->cacheTTL);
            }

            // Fill in defaults for keys not found
            foreach ($missingKeys as $key) {
                if (!isset($result[$key])) {
                    $result[$key] = $defaults[$key] ?? null;
                }
            }
        }

        return $result;
    }

    /**
     * Get all configuration values
     *
     * @return array All settings indexed by key
     */
    public function getAll(): array
    {
        // Try cache first
        $cacheKey = $this->cachePrefix . 'all';
        $settings = $this->cache->get($cacheKey);

        if ($settings !== null) {
            return $settings;
        }

        // Fetch all from database
        $allSettings = $this->settingModel->findAll();

        $settings = [];
        foreach ($allSettings as $setting) {
            $settings[$setting->key] = $setting->value;
        }

        // Cache all settings
        $this->cache->save($cacheKey, $settings, $this->cacheTTL);

        return $settings;
    }

    /**
     * Set configuration value
     *
     * Updates database and invalidates cache
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return bool Success
     */
    public function set(string $key, $value): bool
    {
        // Update or insert in database
        $existing = $this->settingModel->where('key', $key)->first();

        if ($existing) {
            $success = $this->settingModel->update($existing->id, ['value' => $value]);
        } else {
            $success = $this->settingModel->insert(['key' => $key, 'value' => $value]);
        }

        if ($success) {
            // Invalidate cache for this key and 'all'
            $this->invalidate($key);
        }

        return (bool)$success;
    }

    /**
     * Set multiple configuration values at once
     *
     * @param array $settings Settings indexed by key
     * @return bool Success
     */
    public function setMany(array $settings): bool
    {
        $success = true;

        foreach ($settings as $key => $value) {
            if (!$this->set($key, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Delete configuration value
     *
     * @param string $key Configuration key
     * @return bool Success
     */
    public function delete(string $key): bool
    {
        $success = $this->settingModel->where('key', $key)->delete();

        if ($success) {
            $this->invalidate($key);
        }

        return (bool)$success;
    }

    /**
     * Invalidate cache for a specific key
     *
     * @param string $key Configuration key
     * @return bool Success
     */
    public function invalidate(string $key): bool
    {
        // Invalidate specific key
        $cacheKey = $this->cachePrefix . $key;
        $this->cache->delete($cacheKey);

        // Invalidate 'all' cache
        $this->cache->delete($this->cachePrefix . 'all');

        log_message('info', "Config cache invalidated: {$key}");

        return true;
    }

    /**
     * Clear all configuration cache
     *
     * @return bool Success
     */
    public function clearCache(): bool
    {
        // CodeIgniter cache doesn't support wildcard delete
        // So we need to delete known keys individually

        // Clear 'all' cache
        $this->cache->delete($this->cachePrefix . 'all');

        // Optionally, clear all cache (aggressive)
        // $this->cache->clean();

        log_message('info', 'Config cache cleared');

        return true;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache hit/miss statistics
     */
    public function getCacheStats(): array
    {
        // This is a simplified version
        // Real implementation would track hits/misses over time

        $allSettings = $this->settingModel->countAllResults();
        $cacheInfo = $this->cache->getCacheInfo();

        return [
            'total_settings' => $allSettings,
            'cache_info' => $cacheInfo,
            'cache_ttl' => $this->cacheTTL,
        ];
    }

    /**
     * Warm up cache (preload frequently accessed settings)
     *
     * @param array|null $keys Specific keys to warm up, or null for all
     * @return int Number of settings cached
     */
    public function warmCache(?array $keys = null): int
    {
        if ($keys === null) {
            // Warm up all settings
            $settings = $this->getAll();
            return count($settings);
        }

        // Warm up specific keys
        $this->getMany($keys);
        return count($keys);
    }
}
