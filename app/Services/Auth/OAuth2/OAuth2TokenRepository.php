<?php

namespace App\Services\Auth\OAuth2;

use CodeIgniter\Database\ConnectionInterface;

class OAuth2TokenRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function createAccessToken(int $employeeId, string $tokenHash, string $deviceFingerprint, array $scopes, string $expiresAt): int
    {
        $this->db->table('oauth_access_tokens')->insert([
            'employee_id' => $employeeId,
            'token_hash' => $tokenHash,
            'device_fingerprint' => $deviceFingerprint,
            'scopes' => json_encode($scopes),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    public function createRefreshToken(int $employeeId, int $accessTokenId, string $tokenHash, string $deviceFingerprint, string $expiresAt): void
    {
        $this->db->table('oauth_refresh_tokens')->insert([
            'employee_id' => $employeeId,
            'access_token_id' => $accessTokenId,
            'token_hash' => $tokenHash,
            'device_fingerprint' => $deviceFingerprint,
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function findValidAccessToken(string $tokenHash, ?string $deviceFingerprint = null): ?object
    {
        $builder = $this->db->table('oauth_access_tokens')
            ->where('token_hash', $tokenHash)
            ->where('revoked', false)
            ->where('expires_at >', date('Y-m-d H:i:s'));

        if ($deviceFingerprint !== null) {
            $builder->where('device_fingerprint', $deviceFingerprint);
        }

        return $builder->get()->getRow();
    }

    public function touchAccessToken(int $accessTokenId): void
    {
        $this->db->table('oauth_access_tokens')->where('id', $accessTokenId)->update(['last_used_at' => date('Y-m-d H:i:s')]);
    }

    public function findValidRefreshToken(string $tokenHash, string $deviceFingerprint): ?object
    {
        return $this->db->table('oauth_refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->where('device_fingerprint', $deviceFingerprint)
            ->where('revoked', false)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->getRow();
    }

    public function getAccessTokenById(int $accessTokenId): ?object
    {
        return $this->db->table('oauth_access_tokens')->where('id', $accessTokenId)->get()->getRow();
    }

    public function revokeAccessToken(int $accessTokenId): bool
    {
        return (bool) $this->db->table('oauth_access_tokens')
            ->where('id', $accessTokenId)
            ->update(['revoked' => true, 'revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeRefreshToken(int $refreshTokenId): bool
    {
        return (bool) $this->db->table('oauth_refresh_tokens')
            ->where('id', $refreshTokenId)
            ->update(['revoked' => true, 'revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeRefreshByAccessTokenId(int $accessTokenId): bool
    {
        return (bool) $this->db->table('oauth_refresh_tokens')
            ->where('access_token_id', $accessTokenId)
            ->where('revoked', false)
            ->update(['revoked' => true, 'revoked_at' => date('Y-m-d H:i:s')]);
    }

    public function revokeAllTokens(int $employeeId, ?string $exceptDeviceFingerprint = null): int
    {
        $builder = $this->db->table('oauth_access_tokens')
            ->where('employee_id', $employeeId)
            ->where('revoked', false);

        if ($exceptDeviceFingerprint !== null) {
            $builder->where('device_fingerprint !=', $exceptDeviceFingerprint);
        }

        $builder->update([
            'revoked' => true,
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        $affected = $this->db->affectedRows();

        $refreshBuilder = $this->db->table('oauth_refresh_tokens')
            ->where('employee_id', $employeeId)
            ->where('revoked', false);

        if ($exceptDeviceFingerprint !== null) {
            $refreshBuilder->where('device_fingerprint !=', $exceptDeviceFingerprint);
        }

        $refreshBuilder->update([
            'revoked' => true,
            'revoked_at' => date('Y-m-d H:i:s'),
        ]);

        return $affected;
    }

    public function cleanupExpiredRevokedTokens(string $now): int
    {
        $this->db->table('oauth_access_tokens')
            ->where('expires_at <', $now)
            ->where('revoked', true)
            ->delete();
        $accessCount = $this->db->affectedRows();

        $this->db->table('oauth_refresh_tokens')
            ->where('expires_at <', $now)
            ->where('revoked', true)
            ->delete();
        $refreshCount = $this->db->affectedRows();

        return $accessCount + $refreshCount;
    }

    public function listActiveTokens(int $employeeId): array
    {
        return $this->db->table('oauth_access_tokens')
            ->select('id, device_fingerprint, scopes, created_at, last_used_at, expires_at')
            ->where('employee_id', $employeeId)
            ->where('revoked', false)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResult();
    }
}
