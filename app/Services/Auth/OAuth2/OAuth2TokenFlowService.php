<?php

namespace App\Services\Auth\OAuth2;

class OAuth2TokenFlowService
{
    public function __construct(
        private readonly OAuth2TokenRepository $repository,
        private readonly OAuth2TokenCrypto $crypto,
        private readonly OAuth2TokenConfig $config,
    ) {
    }

    public function generateTokens(int $employeeId, string $deviceFingerprint, array $scopes = []): array
    {
        $accessToken = $this->crypto->generateSecureToken($this->config->tokenLength());
        $refreshToken = $this->crypto->generateSecureToken($this->config->tokenLength());

        $accessExpiresAt = date('Y-m-d H:i:s', time() + $this->config->accessTokenLifetime());
        $refreshExpiresAt = date('Y-m-d H:i:s', time() + $this->config->refreshTokenLifetime());

        $accessTokenId = $this->repository->createAccessToken(
            $employeeId,
            $this->crypto->hashToken($accessToken),
            $deviceFingerprint,
            $scopes,
            $accessExpiresAt,
        );

        $this->repository->createRefreshToken(
            $employeeId,
            $accessTokenId,
            $this->crypto->hashToken($refreshToken),
            $deviceFingerprint,
            $refreshExpiresAt,
        );

        log_message('info', "OAuth tokens generated for employee ID: {$employeeId}, device: {$deviceFingerprint}");

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config->accessTokenLifetime(),
            'scope' => implode(' ', $scopes),
        ];
    }

    public function validateAccessToken(string $accessToken, ?string $deviceFingerprint = null): ?array
    {
        $token = $this->repository->findValidAccessToken($this->crypto->hashToken($accessToken), $deviceFingerprint);
        if (! $token) {
            return null;
        }

        $this->repository->touchAccessToken((int) $token->id);

        return [
            'id' => $token->id,
            'employee_id' => $token->employee_id,
            'device_fingerprint' => $token->device_fingerprint,
            'scopes' => json_decode((string) $token->scopes, true) ?? [],
            'expires_at' => $token->expires_at,
        ];
    }

    public function refreshAccessToken(string $refreshToken, string $deviceFingerprint): ?array
    {
        $token = $this->repository->findValidRefreshToken($this->crypto->hashToken($refreshToken), $deviceFingerprint);

        if (! $token) {
            log_message('warning', "Invalid refresh token attempt from device: {$deviceFingerprint}");
            return null;
        }

        $oldAccessToken = $this->repository->getAccessTokenById((int) $token->access_token_id);
        $scopes = $oldAccessToken ? json_decode((string) $oldAccessToken->scopes, true) ?? [] : [];

        $this->repository->revokeAccessToken((int) $token->access_token_id);
        $this->repository->revokeRefreshToken((int) $token->id);

        $newTokens = $this->generateTokens((int) $token->employee_id, $deviceFingerprint, $scopes);

        log_message('info', "OAuth tokens refreshed for employee ID: {$token->employee_id}");

        return $newTokens;
    }

    public function hasScope(array $tokenData, string $requiredScope): bool
    {
        $scopes = $tokenData['scopes'] ?? [];
        return in_array($requiredScope, $scopes, true) || in_array('*', $scopes, true);
    }

    public function getActiveTokens(int $employeeId): array
    {
        return array_map(static function ($token) {
            return [
                'id' => $token->id,
                'device_fingerprint' => $token->device_fingerprint,
                'scopes' => json_decode((string) $token->scopes, true) ?? [],
                'created_at' => $token->created_at,
                'last_used_at' => $token->last_used_at,
                'expires_at' => $token->expires_at,
            ];
        }, $this->repository->listActiveTokens($employeeId));
    }

    public function cleanupExpiredTokens(): int
    {
        $total = $this->repository->cleanupExpiredRevokedTokens(date('Y-m-d H:i:s'));
        if ($total > 0) {
            log_message('info', "Cleaned up {$total} expired OAuth tokens");
        }

        return $total;
    }

    public function revokeAccessToken(int $accessTokenId): bool
    {
        return $this->repository->revokeAccessToken($accessTokenId);
    }

    public function revokeRefreshTokenByAccessTokenId(int $accessTokenId): bool
    {
        return $this->repository->revokeRefreshByAccessTokenId($accessTokenId);
    }

    public function revokeRefreshToken(int $refreshTokenId): bool
    {
        return $this->repository->revokeRefreshToken($refreshTokenId);
    }

    public function revokeAllTokens(int $employeeId, ?string $exceptDeviceFingerprint = null): int
    {
        $count = $this->repository->revokeAllTokens($employeeId, $exceptDeviceFingerprint);
        log_message('info', "Revoked {$count} tokens for employee ID: {$employeeId}");

        return $count;
    }
}
