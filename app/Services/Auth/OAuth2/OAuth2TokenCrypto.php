<?php

namespace App\Services\Auth\OAuth2;

class OAuth2TokenCrypto
{
    public function generateSecureToken(int $tokenLength): string
    {
        return bin2hex(random_bytes($tokenLength));
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
