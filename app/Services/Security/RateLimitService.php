<?php

namespace App\Services\Security;

use App\Services\Security\RateLimit\RateLimitKeyService;
use App\Services\Security\RateLimit\RateLimitPolicyService;
use App\Services\Security\RateLimit\RateLimitStateStore;
use CodeIgniter\Config\Services;

class RateLimitService
{
    protected RateLimitPolicyService $policy;
    protected RateLimitKeyService $keyService;
    protected RateLimitStateStore $stateStore;

    public function __construct()
    {
        $this->policy = new RateLimitPolicyService();
        $this->keyService = new RateLimitKeyService();
        $this->stateStore = new RateLimitStateStore(Services::cache());
    }

    public function isWhitelisted(string $ip): bool
    {
        return $this->policy->isWhitelisted($ip);
    }

    public function attempt(string $key, string $limitType = 'general', ?string $ip = null): array
    {
        $ip = $ip ?? $this->getClientIp();

        if ($this->isWhitelisted($ip)) {
            return [
                'allowed' => true,
                'remaining' => 999999,
                'reset_at' => time() + 3600,
                'attempts' => 0,
                'max_attempts' => 999999,
                'whitelisted' => true,
            ];
        }

        $config = $this->policy->getLimit($limitType);
        $maxAttempts = $config['max_attempts'];
        $windowSeconds = $config['decay_minutes'] * 60;
        $cacheKey = $this->keyService->bucketKey($key, $limitType);

        try {
            $now = time();
            $bucket = $this->stateStore->getBucket($cacheKey);

            if ($bucket === null || ($bucket['reset_at'] > 0 && $bucket['reset_at'] <= $now)) {
                $bucket = ['count' => 1, 'reset_at' => $now + $windowSeconds];
            } else {
                $bucket['count']++;
                $bucket['reset_at'] = $bucket['reset_at'] > 0 ? $bucket['reset_at'] : ($now + $windowSeconds);
            }

            $this->stateStore->saveBucket($cacheKey, $bucket, max(1, $bucket['reset_at'] - $now));

            $allowed = $bucket['count'] <= $maxAttempts;
            $remaining = max(0, $maxAttempts - $bucket['count']);

            if (!$allowed) {
                log_message('warning', "Rate limit exceeded for key: {$key}, type: {$limitType}, IP: {$ip}, attempts: {$bucket['count']}/{$maxAttempts}");
            }

            return [
                'allowed' => $allowed,
                'remaining' => $remaining,
                'reset_at' => $bucket['reset_at'],
                'attempts' => $bucket['count'],
                'max_attempts' => $maxAttempts,
            ];
        } catch (\Exception $e) {
            log_message('error', "Rate limit cache error: {$e->getMessage()} - Key: {$key}, Type: {$limitType}, IP: {$ip}");

            $failClosed = $this->shouldFailClosed($limitType);

            return [
                'allowed' => ! $failClosed,
                'remaining' => $failClosed ? 0 : $maxAttempts,
                'reset_at' => time() + $windowSeconds,
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'error' => true,
                'error_message' => $failClosed ? 'Rate limit unavailable for critical endpoint' : $e->getMessage(),
            ];
        }
    }

    public function check(string $key, string $limitType = 'general', ?string $ip = null): bool
    {
        try {
            $ip = $ip ?? $this->getClientIp();
            if ($this->isWhitelisted($ip)) {
                return true;
            }

            $config = $this->policy->getLimit($limitType);
            $cacheKey = $this->keyService->bucketKey($key, $limitType);
            $bucket = $this->stateStore->getBucket($cacheKey);

            if ($bucket === null) {
                return true;
            }

            if ($bucket['reset_at'] > 0 && $bucket['reset_at'] <= time()) {
                return true;
            }

            return $bucket['count'] < $config['max_attempts'];
        } catch (\Exception $e) {
            log_message('error', "Rate limit check failed for key: {$key}, error: {$e->getMessage()}");
            return ! $this->shouldFailClosed($limitType);
        }
    }

    public function remaining(string $key, string $limitType = 'general', ?string $ip = null): int
    {
        try {
            $ip = $ip ?? $this->getClientIp();
            $config = $this->policy->getLimit($limitType);
            $maxAttempts = $config['max_attempts'];

            if ($this->isWhitelisted($ip)) {
                return 999999;
            }

            $bucket = $this->stateStore->getBucket($this->keyService->bucketKey($key, $limitType));
            if ($bucket === null || ($bucket['reset_at'] > 0 && $bucket['reset_at'] <= time())) {
                return $maxAttempts;
            }

            return max(0, $maxAttempts - $bucket['count']);
        } catch (\Exception $e) {
            log_message('error', "Rate limit remaining check failed for key: {$key}, error: {$e->getMessage()}");
            return $this->policy->getLimit($limitType)['max_attempts'];
        }
    }

    public function reset(string $key, string $limitType = 'general'): bool
    {
        try {
            $cacheKey = $this->keyService->bucketKey($key, $limitType);

            if ($this->stateStore->getBucket($cacheKey) === null) {
                log_message('debug', "Rate limit bucket does not exist for key: {$key}, type: {$limitType} - nothing to reset");
                return true;
            }

            $result = $this->stateStore->deleteBucket($cacheKey);
            if (!$result) {
                log_message('warning', "Failed to reset rate limit bucket for key: {$key}, type: {$limitType}");
            }

            return $result;
        } catch (\Exception $e) {
            log_message('error', "Exception during rate limit reset: {$e->getMessage()} - Key: {$key}, Type: {$limitType}");
            return false;
        }
    }

    public function clearAll(): bool
    {
        return $this->stateStore->clean();
    }

    public function getResetTime(string $key, string $limitType = 'general'): int
    {
        return $this->stateStore->resetSeconds($this->keyService->bucketKey($key, $limitType));
    }

    public function generateKeyForPath(string $path, ?string $ip = null): string
    {
        return $this->keyService->keyForPath($path, $ip ?? $this->getClientIp());
    }

    public function getClientIp(): string
    {
        $request = Services::request();
        $ip = (string) ($request->getIPAddress() ?? '');

        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }

        return '0.0.0.0';
    }

    protected function shouldFailClosed(string $limitType): bool
    {
        return in_array($limitType, ['login', 'register', 'password_reset', '2fa_verify', 'biometric', 'oauth_token', 'critical_action'], true);
    }

    public function addToWhitelist(string $ip): void
    {
        $this->policy->addToWhitelist($ip);
    }

    public function removeFromWhitelist(string $ip): void
    {
        $this->policy->removeFromWhitelist($ip);
    }

    public function getWhitelist(): array
    {
        return $this->policy->getWhitelist();
    }

    public function setLimit(string $type, int $maxAttempts, int $decayMinutes): void
    {
        $this->policy->setLimit($type, $maxAttempts, $decayMinutes);
    }

    public function getLimit(string $type): ?array
    {
        return $this->policy->getRawLimit($type);
    }

    public function getHeaders(array $limitInfo): array
    {
        return [
            'X-RateLimit-Limit' => (string) ($limitInfo['max_attempts'] ?? 0),
            'X-RateLimit-Remaining' => (string) ($limitInfo['remaining'] ?? 0),
            'X-RateLimit-Reset' => (string) ($limitInfo['reset_at'] ?? 0),
        ];
    }

    public function isLimited(string $key, string $limitType = 'general', ?string $ip = null): bool
    {
        return !$this->check($key, $limitType, $ip);
    }

    public function getErrorMessage(array $limitInfo): string
    {
        return $this->policy->formatErrorMessage($limitInfo);
    }
}
