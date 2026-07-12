<?php

namespace App\Services\Auth\OAuth2;

use App\Support\BootstrapEnv;

class OAuth2TokenConfig
{
    public function __construct(
        private int $accessTokenLifetime = 3600,
        private int $refreshTokenLifetime = 2592000,
        private int $tokenLength = 32,
    ) {
    }

    public static function fromEnvironment(): self
    {
        $access = BootstrapEnv::get('OAUTH_ACCESS_TOKEN_LIFETIME', null, ['oauth.accessTokenLifetime']);
        $refresh = BootstrapEnv::get('OAUTH_REFRESH_TOKEN_LIFETIME', null, ['oauth.refreshTokenLifetime']);

        return new self(
            (is_numeric($access) ? (int) $access : 3600),
            (is_numeric($refresh) ? (int) $refresh : 2592000),
            32,
        );
    }

    public function accessTokenLifetime(): int
    {
        return $this->accessTokenLifetime;
    }

    public function refreshTokenLifetime(): int
    {
        return $this->refreshTokenLifetime;
    }

    public function tokenLength(): int
    {
        return $this->tokenLength;
    }

    public function setAccessTokenLifetime(int $seconds): void
    {
        $this->accessTokenLifetime = $seconds;
    }

    public function setRefreshTokenLifetime(int $seconds): void
    {
        $this->refreshTokenLifetime = $seconds;
    }
}
