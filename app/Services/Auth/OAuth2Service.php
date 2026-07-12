<?php
// ARQ-03 FIX: Este arquivo é o núcleo real do serviço.
// A nomenclatura "LegacyCore" é um artefato histórico — não indica código obsoleto.
// Roadmap: renomear para *ServiceCore e a subclasse pública para *Service
// na próxima refatoração maior. (Ver SECURITY_AUDIT_IMPLEMENTATION.md)


namespace App\Services\Auth;

use App\Services\Auth\OAuth2\OAuth2DeviceFingerprint;
use App\Services\Auth\OAuth2\OAuth2TokenConfig;
use App\Services\Auth\OAuth2\OAuth2TokenCrypto;
use App\Services\Auth\OAuth2\OAuth2TokenFlowService;
use App\Services\Auth\OAuth2\OAuth2TokenRepository;
use CodeIgniter\Config\Services;

/**
 * Núcleo legado OAuth2.
 *
 * Mantém API pública para compatibilidade e delega emissão/validação/refresh/
 * revogação/persistência para componentes especializados.
 */
class OAuth2Service
{
    private OAuth2TokenConfig $config;
    private OAuth2TokenFlowService $flow;

    public function __construct()
    {
        $this->config = OAuth2TokenConfig::fromEnvironment();

        $repository = new OAuth2TokenRepository(\Config\Database::connect());
        $crypto = new OAuth2TokenCrypto();
        $this->flow = new OAuth2TokenFlowService($repository, $crypto, $this->config);
    }

    public function generateTokens(int $employeeId, string $deviceFingerprint, array $scopes = []): array
    {
        return $this->flow->generateTokens($employeeId, $deviceFingerprint, $scopes);
    }

    public function validateAccessToken(string $accessToken, ?string $deviceFingerprint = null): ?array
    {
        return $this->flow->validateAccessToken($accessToken, $deviceFingerprint);
    }

    public function refreshAccessToken(string $refreshToken, string $deviceFingerprint): ?array
    {
        return $this->flow->refreshAccessToken($refreshToken, $deviceFingerprint);
    }

    public function revokeAccessToken(int $accessTokenId): bool
    {
        return $this->flow->revokeAccessToken($accessTokenId);
    }

    public function revokeRefreshTokenByAccessTokenId(int $accessTokenId): bool
    {
        return $this->flow->revokeRefreshTokenByAccessTokenId($accessTokenId);
    }

    public function revokeRefreshToken(int $refreshTokenId): bool
    {
        return $this->flow->revokeRefreshToken($refreshTokenId);
    }

    public function revokeAllTokens(int $employeeId, ?string $exceptDeviceFingerprint = null): int
    {
        return $this->flow->revokeAllTokens($employeeId, $exceptDeviceFingerprint);
    }

    public function cleanupExpiredTokens(): int
    {
        return $this->flow->cleanupExpiredTokens();
    }

    public function getActiveTokens(int $employeeId): array
    {
        return $this->flow->getActiveTokens($employeeId);
    }

    public function hasScope(array $tokenData, string $requiredScope): bool
    {
        return $this->flow->hasScope($tokenData, $requiredScope);
    }

    public function setAccessTokenLifetime(int $seconds): void
    {
        $this->config->setAccessTokenLifetime($seconds);
    }

    public function setRefreshTokenLifetime(int $seconds): void
    {
        $this->config->setRefreshTokenLifetime($seconds);
    }

    public function getAccessTokenLifetime(): int
    {
        return $this->config->accessTokenLifetime();
    }

    public function getRefreshTokenLifetime(): int
    {
        return $this->config->refreshTokenLifetime();
    }

    public static function generateDeviceFingerprint(): string
    {
        return OAuth2DeviceFingerprint::generate();
    }
}
