<?php

namespace App\Services\Auth;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\Security\RateLimitService;
use Config\Services;

class ApiAuthService
{
    private const DEFAULT_API_SCOPES = ['api.read', 'api.write'];

    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;
    protected RateLimitService $rateLimitService;
    protected OAuth2Service $oauth2Service;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?AuditModel $auditModel = null,
        ?RateLimitService $rateLimitService = null,
        ?OAuth2Service $oauth2Service = null
    ) {
        $this->employeeModel = $employeeModel ?? Services::employeeModel();
        $this->auditModel = $auditModel ?? Services::auditModel();
        $this->rateLimitService = $rateLimitService ?? Services::rateLimitService();
        $this->oauth2Service = $oauth2Service ?? Services::oauth2Service();
    }

    public function login(string $email, string $password, array $scopes = []): array
    {
        $email = mb_strtolower(trim($email));
        $scopes = $this->normalizeScopes($scopes);
        $rateLimitFailure = $this->consumeLoginRateLimits($email);

        if ($rateLimitFailure !== null) {
            return $rateLimitFailure;
        }

        $employee = $this->employeeModel->findByEmail($email);
        if (! $employee || ! $employee->active || ! $this->employeeModel->verifyPassword($password, (string) $employee->password)) {
            return $this->buildInvalidCredentialsResponse($email, $employee);
        }

        if (in_array($employee->must_change_password ?? null, [true, 't', '1', 1], true)) {
            return [
                'success' => false,
                'code' => 'password_change_required',
                'status' => 423,
                'message' => 'Troca obrigatória de senha pendente no primeiro acesso.',
            ];
        }

        $this->rehashPasswordIfNeeded($employee, $password);
        $this->clearLoginRateLimits($email);

        $deviceFingerprint = OAuth2Service::generateDeviceFingerprint();
        $tokens = $this->oauth2Service->generateTokens((int) $employee->id, $deviceFingerprint, $scopes);

        try {
            $this->employeeModel->setLastLogin((int) $employee->id);
        } catch (\Throwable $e) {
            log_message('error', "Failed to update last_login for employee {$employee->id} via API: {$e->getMessage()}");
        }

        $this->auditModel->log(
            (int) $employee->id,
            'API_LOGIN_SUCCESS',
            'employees',
            (int) $employee->id,
            null,
            null,
            "Login via API: {$employee->name}",
            'info'
        );

        return [
            'success' => true,
            'employee' => $employee,
            'tokens' => $tokens,
        ];
    }

    public function logout(int $employeeId, int $accessTokenId, string $employeeName): void
    {
        $this->oauth2Service->revokeAccessToken($accessTokenId);
        $this->oauth2Service->revokeRefreshTokenByAccessTokenId($accessTokenId);

        $this->auditModel->log(
            $employeeId,
            'API_LOGOUT',
            'employees',
            $employeeId,
            null,
            null,
            "Logout via API: {$employeeName}",
            'info'
        );
    }

    public function refresh(string $refreshToken, string $deviceFingerprint): ?array
    {
        return $this->oauth2Service->refreshAccessToken($refreshToken, $deviceFingerprint);
    }

    public function resolveScopes(?string $scope): array
    {
        if (! is_string($scope) || trim($scope) === '') {
            return self::DEFAULT_API_SCOPES;
        }

        return $this->normalizeScopes(preg_split('/\s+/', trim($scope)) ?: []);
    }

    protected function normalizeScopes(array $scopes): array
    {
        $normalized = array_values(array_filter(
            array_map(static fn($scope) => is_string($scope) ? trim($scope) : '', $scopes),
            static fn(string $scope): bool => $scope !== ''
        ));

        return $normalized !== [] ? $normalized : self::DEFAULT_API_SCOPES;
    }

    protected function consumeLoginRateLimits(string $email): ?array
    {
        $ip = $this->rateLimitService->getClientIp();

        foreach ($this->getLoginRateLimitKeys($email, $ip) as $key) {
            $limitInfo = $this->rateLimitService->attempt($key, 'login', $ip);
            if (! $limitInfo['allowed']) {
                return [
                    'success' => false,
                    'code' => 'rate_limited',
                    'status' => 429,
                    'message' => 'Muitas tentativas. Aguarde antes de tentar novamente.',
                ];
            }
        }

        return null;
    }

    protected function clearLoginRateLimits(string $email): void
    {
        $ip = $this->rateLimitService->getClientIp();

        foreach ($this->getLoginRateLimitKeys($email, $ip) as $key) {
            $this->rateLimitService->reset($key, 'login');
        }
    }

    protected function getLoginRateLimitKeys(string $email, string $ip): array
    {
        return [
            $this->rateLimitService->generateKeyForPath('api/auth/login', $ip),
            'api_auth_login_account_' . md5($email),
        ];
    }

    protected function buildInvalidCredentialsResponse(string $email, ?object $employee): array
    {
        $reason = ! $employee ? 'missing_user' : (! $employee->active ? 'inactive_user' : 'invalid_password');

        $this->auditModel->log(
            null,
            'API_LOGIN_FAILED',
            'employees',
            null,
            null,
            ['email_hash' => hash('sha256', $email), 'reason' => $reason],
            'Tentativa de login via API falhou',
            'warning'
        );

        return [
            'success' => false,
            'code' => 'invalid_credentials',
            'status' => 401,
            'message' => 'Credenciais inválidas.',
            'reason' => $reason,
        ];
    }

    protected function rehashPasswordIfNeeded(object $employee, string $password): void
    {
        if (! password_needs_rehash((string) $employee->password, PASSWORD_ARGON2ID)) {
            return;
        }

        $this->employeeModel->update((int) $employee->id, [
            'password' => password_hash($password, PASSWORD_ARGON2ID),
        ]);
    }
}