<?php

namespace App\Services\Auth;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\Security\RateLimitService;
use Config\Services;

class ApiAuthService
{
    private const DEFAULT_API_SCOPES = ['api.read', 'api.write'];

    /** Validade do desafio de 2FA pendente (token opaco emitido após senha correta). */
    private const PENDING_2FA_TTL_SECONDS = 300;

    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;
    protected RateLimitService $rateLimitService;
    protected OAuth2Service $oauth2Service;
    protected TwoFactorManagerService $twoFactorManagerService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?AuditModel $auditModel = null,
        ?RateLimitService $rateLimitService = null,
        ?OAuth2Service $oauth2Service = null,
        ?TwoFactorManagerService $twoFactorManagerService = null
    ) {
        $this->employeeModel = $employeeModel ?? Services::employeeModel();
        $this->auditModel = $auditModel ?? Services::auditModel();
        $this->rateLimitService = $rateLimitService ?? Services::rateLimitService();
        $this->oauth2Service = $oauth2Service ?? Services::oauth2Service();
        $this->twoFactorManagerService = $twoFactorManagerService ?? Services::twoFactorManagerService();
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

        // CRIT-05 (auditoria): antes, o login via API/OAuth2 nunca checava
        // two_factor_enabled e emitia tokens completos só com e-mail+senha, contornando
        // por completo o 2FA para quem usa o app mobile. Agora, quando 2FA está ativo,
        // devolvemos apenas um token de desafio de curta duração — os tokens de acesso só
        // são emitidos depois de verifyTwoFactor() confirmar o TOTP/backup code.
        if ((bool) ($employee->two_factor_enabled ?? false)) {
            return $this->beginTwoFactorChallenge($employee, $scopes);
        }

        return $this->completeLogin($employee, $scopes, 'API_LOGIN_SUCCESS', "Login via API: {$employee->name}");
    }

    /**
     * Segunda etapa do login quando a conta tem 2FA habilitado: troca o token de desafio
     * emitido por login() + um código TOTP (ou backup code) válido pelos tokens de acesso
     * definitivos. O token de desafio é de uso único e expira em PENDING_2FA_TTL_SECONDS.
     */
    public function verifyTwoFactor(string $twoFactorToken, string $code, bool $useBackupCode = false): array
    {
        $twoFactorToken = trim($twoFactorToken);
        $code = trim($code);

        if ($twoFactorToken === '' || $code === '') {
            return [
                'success' => false,
                'code' => 'validation_error',
                'status' => 400,
                'message' => 'two_factor_token e code são obrigatórios.',
            ];
        }

        $ip = $this->rateLimitService->getClientIp();
        $limitInfo = $this->rateLimitService->attempt('api_2fa_verify_' . hash('sha256', $twoFactorToken), '2fa_verify', $ip);
        if (! $limitInfo['allowed']) {
            return [
                'success' => false,
                'code' => 'rate_limited',
                'status' => 429,
                'message' => $this->rateLimitService->getErrorMessage($limitInfo),
            ];
        }

        $pending = $this->getPendingTwoFactorChallenge($twoFactorToken);
        if ($pending === null) {
            return [
                'success' => false,
                'code' => 'invalid_or_expired_challenge',
                'status' => 401,
                'message' => 'Desafio de 2FA inválido ou expirado. Faça login novamente.',
            ];
        }

        $employee = $this->employeeModel->find((int) $pending['employee_id']);
        if (! $employee || ! ($employee->active ?? false) || ! (bool) ($employee->two_factor_enabled ?? false)) {
            $this->forgetPendingTwoFactorChallenge($twoFactorToken);
            return [
                'success' => false,
                'code' => 'invalid_or_expired_challenge',
                'status' => 401,
                'message' => 'Desafio de 2FA inválido ou expirado. Faça login novamente.',
            ];
        }

        $verified = $useBackupCode
            ? $this->twoFactorManagerService->verifyAndConsumeBackupCode($employee, $code)
            : $this->twoFactorManagerService->verifyTotpCode($employee, $code);

        if (! $verified) {
            $this->auditModel->log(
                (int) $employee->id,
                'API_LOGIN_2FA_FAILED',
                'employees',
                (int) $employee->id,
                null,
                null,
                "Código 2FA inválido via API: {$employee->name}",
                'warning'
            );

            return [
                'success' => false,
                'code' => 'invalid_two_factor_code',
                'status' => 401,
                'message' => 'Código de verificação inválido.',
            ];
        }

        // Uso único: o token de desafio não pode ser reaproveitado após sucesso ou falha
        // definitiva de outra sessão.
        $this->forgetPendingTwoFactorChallenge($twoFactorToken);
        $this->rateLimitService->reset('api_2fa_verify_' . hash('sha256', $twoFactorToken), '2fa_verify');

        return $this->completeLogin($employee, $pending['scopes'], 'API_LOGIN_2FA_SUCCESS', "2FA verificado via API: {$employee->name}");
    }

    private function completeLogin(object $employee, array $scopes, string $auditAction, string $auditDescription): array
    {
        $deviceFingerprint = OAuth2Service::generateDeviceFingerprint();
        $tokens = $this->oauth2Service->generateTokens((int) $employee->id, $deviceFingerprint, $scopes);

        try {
            $this->employeeModel->setLastLogin((int) $employee->id);
        } catch (\Throwable $e) {
            log_message('error', "Failed to update last_login for employee {$employee->id} via API: {$e->getMessage()}");
        }

        $this->auditModel->log(
            (int) $employee->id,
            $auditAction,
            'employees',
            (int) $employee->id,
            null,
            null,
            $auditDescription,
            'info'
        );

        return [
            'success' => true,
            'employee' => $employee,
            'tokens' => $tokens,
        ];
    }

    private function beginTwoFactorChallenge(object $employee, array $scopes): array
    {
        $token = bin2hex(random_bytes(32));

        \Config\Services::cache()->save(
            $this->pendingTwoFactorKey($token),
            [
                'employee_id' => (int) $employee->id,
                'scopes' => $scopes,
                'created_at' => time(),
            ],
            self::PENDING_2FA_TTL_SECONDS
        );

        $this->auditModel->log(
            (int) $employee->id,
            'API_LOGIN_2FA_PENDING',
            'employees',
            (int) $employee->id,
            null,
            null,
            "Login via API aguardando verificação 2FA: {$employee->name}",
            'info'
        );

        return [
            'success' => true,
            'requires_2fa' => true,
            'two_factor_token' => $token,
            'two_factor_expires_in' => self::PENDING_2FA_TTL_SECONDS,
        ];
    }

    private function getPendingTwoFactorChallenge(string $token): ?array
    {
        $data = \Config\Services::cache()->get($this->pendingTwoFactorKey($token));

        return is_array($data) ? $data : null;
    }

    private function forgetPendingTwoFactorChallenge(string $token): void
    {
        \Config\Services::cache()->delete($this->pendingTwoFactorKey($token));
    }

    private function pendingTwoFactorKey(string $token): string
    {
        // A chave de cache guarda o hash do token, nunca o valor em claro — mesmo padrão
        // usado para os tokens OAuth2 persistidos (OAuth2TokenCrypto).
        return 'api_2fa_pending_' . hash('sha256', $token);
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