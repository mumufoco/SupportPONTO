<?php

namespace App\Services\Security\RateLimit;

class RateLimitKeyService
{
    public function bucketKey(string $key, string $limitType): string
    {
        return 'rate_limit_' . $limitType . '_' . md5($key);
    }

    public function keyForPath(string $path, string $ip): string
    {
        return str_replace('/', '_', $path) . '_' . md5($ip);
    }
}
