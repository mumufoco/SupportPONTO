<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\Security\RateLimitService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\Session\Session;
use Config\Services;

class SessionSecurityService
{
    private const REGISTRY_FILE = WRITEPATH . 'cache/session_registry.json';
    private const DEFAULT_STEP_UP_TTL = 900;

    private EmployeeModel $employeeModel;
    private AuditModel $auditModel;
    private RateLimitService $rateLimitService;

    public function __construct(?EmployeeModel $employeeModel = null, ?AuditModel $auditModel = null, ?RateLimitService $rateLimitService = null)
    {
        $this->employeeModel = $employeeModel ?? Services::employeeModel();
        $this->auditModel = $auditModel ?? Services::auditModel();
        $this->rateLimitService = $rateLimitService ?? Services::rateLimitService();
    }

    public function registerCurrentSession(object $user, Session $session, RequestInterface $request): string
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if ($sessionKey === '') {
            $sessionKey = bin2hex(random_bytes(16));
            $session->set('session_key', $sessionKey);
        }

        $session->set([
            'session_started_at' => date('Y-m-d H:i:s'),
            'recent_password_confirmed_at' => null,
        ]);

        $registry = $this->readRegistry();
        $userId = (int) ($user->id ?? 0);
        $registry[$userId] ??= [];
        $registry[$userId][$sessionKey] = [
            'session_key' => $sessionKey,
            'started_at' => (string) $session->get('session_started_at'),
            'last_activity' => date('Y-m-d H:i:s'),
            'ip' => (string) ($request->getIPAddress() ?? ''),
            'user_agent' => substr((string) $request->getUserAgent()->getAgentString(), 0, 255),
            'fingerprint' => (string) ($session->get('session_fingerprint') ?? ''),
            'role' => (string) ($user->role ?? ''),
            'active' => true,
            'remember_me' => (bool) $session->get('remember_me'),
        ];

        $this->writeRegistry($registry);

        return $sessionKey;
    }

    public function touchCurrentSession(int $userId, Session $session, RequestInterface $request): void
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if ($userId <= 0 || $sessionKey === '') {
            return;
        }

        $registry = $this->readRegistry();
        if (! isset($registry[$userId][$sessionKey])) {
            return;
        }

        $registry[$userId][$sessionKey]['last_activity'] = date('Y-m-d H:i:s');
        $registry[$userId][$sessionKey]['ip'] = (string) ($request->getIPAddress() ?? '');
        $registry[$userId][$sessionKey]['user_agent'] = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);
        $this->writeRegistry($registry);
    }

    public function isCurrentSessionAllowed(int $userId, Session $session): bool
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if ($userId <= 0 || $sessionKey === '') {
            return false;
        }

        $registry = $this->readRegistry();
        return (bool) ($registry[$userId][$sessionKey]['active'] ?? false);
    }

    public function forgetCurrentSession(?int $userId, Session $session): void
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if (($userId ?? 0) <= 0 || $sessionKey === '') {
            return;
        }

        $registry = $this->readRegistry();
        unset($registry[$userId][$sessionKey]);
        if (($registry[$userId] ?? []) === []) {
            unset($registry[$userId]);
        }
        $this->writeRegistry($registry);
    }

    /** @return array<int, array<string, mixed>> */
    public function listSessionsForUser(int $userId, ?string $currentSessionKey = null): array
    {
        $registry = $this->readRegistry();
        $sessions = array_values($registry[$userId] ?? []);
        usort($sessions, static fn(array $a, array $b): int => strcmp((string) ($b['last_activity'] ?? ''), (string) ($a['last_activity'] ?? '')));

        foreach ($sessions as &$session) {
            $session['is_current'] = $currentSessionKey !== null && ($session['session_key'] ?? null) === $currentSessionKey;
            $session['device_label'] = $this->guessDeviceLabel((string) ($session['user_agent'] ?? ''));
        }

        return $sessions;
    }

    public function revokeOtherSessions(int $userId, Session $session): int
    {
        $currentSessionKey = (string) ($session->get('session_key') ?? '');
        if ($userId <= 0 || $currentSessionKey === '') {
            return 0;
        }

        $registry = $this->readRegistry();
        $revoked = 0;

        foreach (($registry[$userId] ?? []) as $sessionKey => $data) {
            if ($sessionKey === $currentSessionKey) {
                continue;
            }

            unset($registry[$userId][$sessionKey]);
            $revoked++;
        }

        if ($revoked > 0) {
            $this->writeRegistry($registry);
            $this->auditModel->log($userId, 'SESSIONS_REVOKED', 'employees', $userId, null, ['revoked_count' => $revoked], 'Outras sessões encerradas pelo usuário', 'warning');
        }

        return $revoked;
    }

    public function revokeSessionByKey(int $userId, string $sessionKey, ?string $actedBy = null): bool
    {
        if ($userId <= 0 || $sessionKey === '') {
            return false;
        }

        $registry = $this->readRegistry();
        if (! isset($registry[$userId][$sessionKey])) {
            return false;
        }

        unset($registry[$userId][$sessionKey]);
        if (($registry[$userId] ?? []) === []) {
            unset($registry[$userId]);
        }
        $this->writeRegistry($registry);

        $this->auditModel->log($userId, 'SESSION_REVOKED', 'employees', $userId, null, ['session_key' => $sessionKey, 'acted_by' => $actedBy], 'Sessão específica revogada', 'warning');
        return true;
    }

    public function confirmPasswordForCriticalAction(object $employee, string $currentPassword, Session $session): array
    {
        if ($currentPassword === '') {
            return ['success' => false, 'message' => 'Informe sua senha atual para confirmar a ação crítica.'];
        }

        $limitKey = $this->criticalActionRateLimitKey((int) ($employee->id ?? 0));
        $limitInfo = $this->rateLimitService->attempt($limitKey, 'critical_action');
        if (! ($limitInfo['allowed'] ?? false)) {
            $message = 'Muitas tentativas de confirmação de ação crítica. Aguarde alguns minutos antes de tentar novamente.';
            $this->auditModel->log((int) $employee->id, 'CRITICAL_ACTION_CONFIRMATION_RATE_LIMITED', 'employees', (int) $employee->id, null, [
                'attempts' => $limitInfo['attempts'] ?? null,
                'max_attempts' => $limitInfo['max_attempts'] ?? null,
                'reset_at' => $limitInfo['reset_at'] ?? null,
            ], $message, 'warning');

            return ['success' => false, 'message' => $message];
        }

        if (! password_verify($currentPassword, (string) ($employee->password ?? ''))) {
            $this->auditModel->log((int) $employee->id, 'CRITICAL_ACTION_CONFIRMATION_FAILED', 'employees', (int) $employee->id, null, [
                'attempts' => $limitInfo['attempts'] ?? null,
                'remaining' => $limitInfo['remaining'] ?? null,
            ], 'Falha na confirmação de senha para ação crítica', 'warning');
            return ['success' => false, 'message' => 'Senha atual inválida.'];
        }

        $this->rateLimitService->reset($limitKey, 'critical_action');
        $session->set('recent_password_confirmed_at', time());
        $this->auditModel->log((int) $employee->id, 'CRITICAL_ACTION_CONFIRMED', 'employees', (int) $employee->id, null, null, 'Senha confirmada para ação crítica', 'info');

        return ['success' => true, 'message' => 'Confirmação válida registrada.'];
    }

    public function hasFreshStepUp(Session $session): bool
    {
        $confirmedAt = (int) ($session->get('recent_password_confirmed_at') ?? 0);
        $ttl = (int) env('security.stepUpTtl', self::DEFAULT_STEP_UP_TTL);

        return $confirmedAt > 0 && $ttl > 0 && (time() - $confirmedAt) <= $ttl;
    }

    public function ensureCriticalActionAllowed(object $employee, RequestInterface $request, Session $session): array
    {
        if ($this->hasFreshStepUp($session)) {
            return ['success' => true];
        }

        $password = trim((string) ($request->getPost('current_password') ?? $request->getHeaderLine('X-Current-Password')));
        return $this->confirmPasswordForCriticalAction($employee, $password, $session) + ['requires_confirmation' => true];
    }

    private function readRegistry(): array
    {
        $path = self::REGISTRY_FILE;
        if (! is_file($path)) {
            return [];
        }

        $content = (string) @file_get_contents($path);
        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeRegistry(array $registry): void
    {
        $path = self::REGISTRY_FILE;
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        @file_put_contents($path, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    private function criticalActionRateLimitKey(int $userId): string
    {
        $ip = $this->rateLimitService->getClientIp();

        return 'critical_action_confirm_' . $userId . '_' . md5($ip);
    }

    private function guessDeviceLabel(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        return match (true) {
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'iphone'), str_contains($ua, 'ipad'), str_contains($ua, 'ios') => 'iOS',
            str_contains($ua, 'edg') => 'Edge',
            str_contains($ua, 'chrome') => 'Chrome',
            str_contains($ua, 'firefox') => 'Firefox',
            str_contains($ua, 'safari') => 'Safari',
            default => 'Dispositivo web',
        };
    }
}
