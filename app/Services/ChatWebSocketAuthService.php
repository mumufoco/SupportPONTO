<?php

namespace App\Services;

use App\Services\Auth\AuthService;
use App\Services\Auth\OAuth2Service;

class ChatWebSocketAuthService
{
    private const TOKEN_USE = 'websocket';
    private const WS_TOKEN_TTL = 900;

    public function __construct(
        private ?AuthService $authService = null,
        private ?OAuth2Service $oauth2Service = null,
    ) {
        $this->authService ??= new AuthService();
        $this->oauth2Service ??= new OAuth2Service();
    }

    public function issueBrowserToken(array $employee): string
    {
        $secretKey = env('JWT_SECRET_KEY');
        if (! is_string($secretKey) || trim($secretKey) === '') {
            throw new \RuntimeException('JWT_SECRET_KEY must be configured.');
        }

        $now = time();
        $payload = [
            'iss' => 'supportponto',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + self::WS_TOKEN_TTL,
            'sub' => (int) ($employee['id'] ?? 0),
            'user_id' => (int) ($employee['id'] ?? 0),
            'employee_id' => (int) ($employee['id'] ?? 0),
            'email' => (string) ($employee['email'] ?? ''),
            'name' => (string) ($employee['name'] ?? ''),
            'role' => (string) ($employee['role'] ?? 'funcionario'),
            'token_use' => self::TOKEN_USE,
            'scopes' => ['chat:connect'],
        ];

        return \Firebase\JWT\JWT::encode($payload, trim($secretKey), 'HS256');
    }

    public function validate(string $token): ?array
    {
        $token = trim(preg_replace('/^Bearer\s+/i', '', $token) ?? '');
        if ($token === '') {
            return null;
        }

        $oauthToken = $this->oauth2Service->validateAccessToken($token);
        if (is_array($oauthToken)) {
            return [
                'auth_type' => 'oauth2',
                'user_id' => (int) ($oauthToken['employee_id'] ?? 0),
                'employee_id' => (int) ($oauthToken['employee_id'] ?? 0),
                'scopes' => (array) ($oauthToken['scopes'] ?? []),
                'token_id' => (int) ($oauthToken['id'] ?? 0),
            ];
        }

        $decoded = $this->authService->validateJWT($token);
        if (! $decoded) {
            return null;
        }

        if (($decoded->token_use ?? null) !== self::TOKEN_USE) {
            return null;
        }

        return [
            'auth_type' => 'session_jwt',
            'user_id' => (int) ($decoded->user_id ?? $decoded->sub ?? 0),
            'employee_id' => (int) ($decoded->employee_id ?? $decoded->sub ?? 0),
            'scopes' => (array) ($decoded->scopes ?? ['chat:connect']),
            'token_id' => null,
        ];
    }
}
