<?php

namespace App\Services\Auth;

use App\Support\BootstrapEnv;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\Security\RateLimitService;
use App\Services\Auth\SessionSecurityService;
use Config\Services;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Session\Session;

class WebAuthService
{
    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;
    protected RateLimitService $rateLimitService;
    protected RememberMeService $rememberMeService;
    protected SessionSecurityService $sessionSecurityService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?AuditModel $auditModel = null,
        ?RateLimitService $rateLimitService = null,
        ?RememberMeService $rememberMeService = null,
        ?SessionSecurityService $sessionSecurityService = null
    ) {
        $this->employeeModel = $employeeModel ?? Services::employeeModel();
        $this->auditModel = $auditModel ?? Services::auditModel();
        $this->rateLimitService = $rateLimitService ?? Services::rateLimitService();
        $this->rememberMeService = $rememberMeService ?? Services::rememberMeService();
        $this->sessionSecurityService = $sessionSecurityService ?? Services::sessionSecurityService();
    }

    public function authenticate(string $email, string $password, bool $remember, Session $session, RequestInterface $request): array
    {
        $email = mb_strtolower(trim($email));
        $rateLimitFailure = $this->consumeLoginRateLimits($email);

        if ($rateLimitFailure !== null) {
            return $rateLimitFailure;
        }

        $user = $this->employeeModel->findByEmail($email);
        if (! $this->isValidActiveUser($user, $password)) {
            return $this->buildInvalidCredentialsResponse($email, $user);
        }

        $this->clearLoginRateLimits($email);
        $this->establishSession($user, $session, $request);
        $this->rehashPasswordIfNeeded($user, $password);
        $this->updateLastLogin((int) $user->id);

        $session->set('remember_me', false);

        if ($remember && $this->rememberMeService->isEnabled()) {
            $this->rememberMeService->issue((int) $user->id);
            $session->set('remember_me', true);
        } else {
            $this->rememberMeService->clearPersistedToken((int) $user->id);
        }


        $this->auditModel->log(
            (int) $user->id,
            'LOGIN',
            'employees',
            (int) $user->id,
            null,
            null,
            'Login bem-sucedido',
            'info'
        );

        $requiresTwoFactor = (bool) ($user->two_factor_enabled ?? false);
        if ($requiresTwoFactor) {
            $this->markSessionAsPendingTwoFactor($user, $session);
        } else {
            $session->remove(['2fa_pending_user_id', '2fa_pending_redirect']);
            $session->set('2fa_verified', false);
        }

        return [
            'success' => true,
            'user' => $user,
            'must_change_password' => in_array($user->must_change_password ?? null, [true, 't', '1', 1], true),
            'requires_2fa' => $requiresTwoFactor,
        ];
    }

    public function restoreFromRememberMe(Session $session, RequestInterface $request): bool
    {
        if ($session->get('user_id')) {
            return true;
        }

        $user = $this->rememberMeService->resolveUserFromCookie();
        if (! $user) {
            return false;
        }

        $this->establishSession($user, $session, $request);
        $session->set('remember_me', true);
        $this->rememberMeService->issue((int) $user->id);

        if ((bool) ($user->two_factor_enabled ?? false)) {
            $this->markSessionAsPendingTwoFactor($user, $session);
        }

        $this->auditModel->log(
            (int) $user->id,
            'LOGIN_REMEMBER_ME',
            'employees',
            (int) $user->id,
            null,
            null,
            'Sessão restaurada via remember-me',
            'info'
        );

        return true;
    }

    public function logout(?int $userId, ?string $userName, Session $session): void
    {
        if ($userId !== null) {
            $this->auditModel->log(
                $userId,
                'LOGOUT',
                'employees',
                $userId,
                null,
                null,
                "Logout: {$userName}",
                'info'
            );
        }

        if ($userId !== null) {
            $this->rememberMeService->clearPersistedToken($userId);
        }

        $session->remove('remember_token');
        $session->remove('remember_me');
        $this->rememberMeService->clear();
        $this->sessionSecurityService->forgetCurrentSession($userId, $session);
        $session->destroy();
    }

    protected function isValidActiveUser(?object $user, string $password): bool
    {
        return $user !== null
            && (bool) ($user->active ?? false)
            && $this->employeeModel->verifyPassword($password, (string) ($user->password ?? ''));
    }

    protected function buildInvalidCredentialsResponse(string $email, ?object $user): array
    {
        $reason = ! $user ? 'missing_user' : (! $user->active ? 'inactive_user' : 'invalid_password');

        $this->auditModel->log(
            $user->id ?? null,
            'LOGIN_FAILED',
            'employees',
            $user->id ?? null,
            null,
            ['email_hash' => hash('sha256', $email), 'reason' => $reason],
            'Tentativa de login inválida',
            'warning'
        );

        return [
            'success' => false,
            'message' => 'E-mail ou senha inválidos.',
            'with_input' => true,
        ];
    }

    public function isRememberMeEnabled(): bool
    {
        return $this->rememberMeService->isEnabled();
    }

    protected function establishSession(object $user, Session $session, RequestInterface $request): void
    {
        $session->regenerate(true);

        $session->set([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'user_department' => $user->department ?? null,
            'user_active' => (bool) $user->active,
            'must_change_password' => in_array($user->must_change_password ?? null, [true, 't', '1', 1], true),
            'last_activity' => time(),
            'session_fingerprint' => $this->buildSessionFingerprint($request),
            'logged_in' => true,
            'remember_me' => false,
            '2fa_verified' => false,
            'employee' => (array) $user,
        ]);

        $this->sessionSecurityService->registerCurrentSession($user, $session, $request);
    }


    protected function markSessionAsPendingTwoFactor(object $user, Session $session): void
    {
        $session->set([
            '2fa_pending_user_id' => (int) $user->id,
            '2fa_verified' => false,
        ]);
    }

    protected function consumeLoginRateLimits(string $email): ?array
    {
        $ip = $this->rateLimitService->getClientIp();

        foreach ($this->getLoginRateLimitKeys($email, $ip) as $key) {
            $limitInfo = $this->rateLimitService->attempt($key, 'login', $ip);
            if (! $limitInfo['allowed']) {
                $this->auditModel->log(
                    null,
                    'LOGIN_BLOCKED',
                    'employees',
                    null,
                    null,
                    ['email_hash' => hash('sha256', $email), 'key' => $key],
                    'Tentativa de login bloqueada por rate limit',
                    'warning'
                );

                return [
                    'success' => false,
                    'message' => 'Muitas tentativas de login. Aguarde alguns minutos antes de tentar novamente.',
                    'with_input' => true,
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
            $this->rateLimitService->generateKeyForPath('auth/login', $ip),
            'auth_login_account_' . md5($email),
        ];
    }

    protected function rehashPasswordIfNeeded(object $user, string $password): void
    {
        if (! password_needs_rehash((string) $user->password, PASSWORD_ARGON2ID)) {
            return;
        }

        $this->employeeModel->update((int) $user->id, [
            'password' => password_hash($password, PASSWORD_ARGON2ID),
        ]);
    }

    protected function updateLastLogin(int $userId): void
    {
        try {
            $this->employeeModel->setLastLogin($userId);
        } catch (\Throwable $e) {
            log_message('error', "Failed to update last_login for user {$userId}: {$e->getMessage()}");
        }
    }

    protected function buildSessionFingerprint(RequestInterface $request): string
    {
        $userAgent = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);
        $matchIp = BootstrapEnv::sessionMatchIp(false);
        $ip = $matchIp ? (string) ($request->getIPAddress() ?? '') : '';

        return hash('sha256', $userAgent . '|' . $ip);
    }
}