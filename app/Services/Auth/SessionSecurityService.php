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

        $userId = (int) ($user->id ?? 0);
        $startedAt = (string) $session->get('session_started_at');
        $ip = (string) ($request->getIPAddress() ?? '');
        $userAgent = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);
        $fingerprint = (string) ($session->get('session_fingerprint') ?? '');
        $role = (string) ($user->role ?? '');
        $rememberMe = (bool) $session->get('remember_me');

        $this->withRegistryLock(function (array $registry) use ($userId, $sessionKey, $startedAt, $ip, $userAgent, $fingerprint, $role, $rememberMe): array {
            $registry[$userId] ??= [];
            $registry[$userId][$sessionKey] = [
                'session_key' => $sessionKey,
                'started_at' => $startedAt,
                'last_activity' => date('Y-m-d H:i:s'),
                'ip' => $ip,
                'user_agent' => $userAgent,
                'fingerprint' => $fingerprint,
                'role' => $role,
                'active' => true,
                'remember_me' => $rememberMe,
            ];

            return $registry;
        });

        return $sessionKey;
    }

    public function touchCurrentSession(int $userId, Session $session, RequestInterface $request): void
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if ($userId <= 0 || $sessionKey === '') {
            return;
        }

        $ip = (string) ($request->getIPAddress() ?? '');
        $userAgent = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);

        $this->withRegistryLock(function (array $registry) use ($userId, $sessionKey, $ip, $userAgent): array {
            if (! isset($registry[$userId][$sessionKey])) {
                return $registry;
            }

            $registry[$userId][$sessionKey]['last_activity'] = date('Y-m-d H:i:s');
            $registry[$userId][$sessionKey]['ip'] = $ip;
            $registry[$userId][$sessionKey]['user_agent'] = $userAgent;

            return $registry;
        });
    }

    public function isCurrentSessionAllowed(int $userId, Session $session): bool
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if ($userId <= 0 || $sessionKey === '') {
            return false;
        }

        $registry = $this->readRegistryLocked();
        return (bool) ($registry[$userId][$sessionKey]['active'] ?? false);
    }

    public function forgetCurrentSession(?int $userId, Session $session): void
    {
        $sessionKey = (string) ($session->get('session_key') ?? '');
        if (($userId ?? 0) <= 0 || $sessionKey === '') {
            return;
        }

        $this->withRegistryLock(function (array $registry) use ($userId, $sessionKey): array {
            unset($registry[$userId][$sessionKey]);
            if (($registry[$userId] ?? []) === []) {
                unset($registry[$userId]);
            }

            return $registry;
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function listSessionsForUser(int $userId, ?string $currentSessionKey = null): array
    {
        $registry = $this->readRegistryLocked();
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

        $revoked = 0;
        $this->withRegistryLock(function (array $registry) use ($userId, $currentSessionKey, &$revoked): array {
            foreach (($registry[$userId] ?? []) as $sessionKey => $data) {
                if ($sessionKey === $currentSessionKey) {
                    continue;
                }

                unset($registry[$userId][$sessionKey]);
                $revoked++;
            }

            return $registry;
        });

        if ($revoked > 0) {
            $this->auditModel->log($userId, 'SESSIONS_REVOKED', 'employees', $userId, null, ['revoked_count' => $revoked], 'Outras sessões encerradas pelo usuário', 'warning');
        }

        return $revoked;
    }

    public function revokeSessionByKey(int $userId, string $sessionKey, ?string $actedBy = null): bool
    {
        if ($userId <= 0 || $sessionKey === '') {
            return false;
        }

        $existed = false;
        $this->withRegistryLock(function (array $registry) use ($userId, $sessionKey, &$existed): array {
            if (! isset($registry[$userId][$sessionKey])) {
                return $registry;
            }

            $existed = true;
            unset($registry[$userId][$sessionKey]);
            if (($registry[$userId] ?? []) === []) {
                unset($registry[$userId]);
            }

            return $registry;
        });

        if (! $existed) {
            return false;
        }

        $this->auditModel->log($userId, 'SESSION_REVOKED', 'employees', $userId, null, ['session_key' => $sessionKey, 'acted_by' => $actedBy], 'Sessão específica revogada', 'warning');
        return true;
    }

    /**
     * Revoga TODAS as sessões ativas de um usuário (inclusive a atual, se houver) —
     * diferente de revokeOtherSessions(), que preserva a sessão de quem está chamando.
     *
     * ALTO-02 (auditoria): usado quando um colaborador é desativado ou tem o papel
     * (role) alterado por um admin/RH — sem isso, quem já estivesse logado continuava
     * com acesso pleno (inclusive privilégios do papel antigo) até a sessão expirar
     * naturalmente, mesmo após uma ação administrativa explícita de bloqueio.
     */
    public function revokeAllSessionsForUser(int $userId, ?string $reason = null, ?string $actedBy = null): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $revoked = 0;
        $this->withRegistryLock(function (array $registry) use ($userId, &$revoked): array {
            $revoked = count($registry[$userId] ?? []);
            unset($registry[$userId]);

            return $registry;
        });

        if ($revoked > 0) {
            $this->auditModel->log(
                $userId,
                'SESSIONS_FORCE_REVOKED',
                'employees',
                $userId,
                null,
                ['revoked_count' => $revoked, 'reason' => $reason, 'acted_by' => $actedBy],
                $reason ?? 'Todas as sessões do usuário foram encerradas administrativamente',
                'warning'
            );
        }

        return $revoked;
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

    /**
     * Executa $mutator sob um lock exclusivo (flock) que cobre TODO o ciclo
     * leitura -> modificação -> escrita do registro de sessões, não só a escrita final.
     *
     * ALTO-03 (auditoria): antes, readRegistry()/writeRegistry() eram chamadas
     * separadas — o LOCK_EX do file_put_contents só protegia a escrita física do
     * arquivo, não o intervalo entre ler e decidir o que escrever. Sob concorrência
     * (ex.: revogar uma sessão exatamente quando uma requisição daquela mesma sessão
     * está em touchCurrentSession()), o resultado de uma operação podia sobrescrever o
     * da outra silenciosamente, inclusive "ressuscitando" uma sessão recém-revogada.
     *
     * @param callable(array<int,array<string,array<string,mixed>>>):array<int,array<string,array<string,mixed>>> $mutator
     */
    private function withRegistryLock(callable $mutator): void
    {
        $handle = $this->openRegistryHandle();
        if ($handle === null) {
            return;
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                return;
            }

            $registry = $this->decodeRegistryHandle($handle);
            $registry = $mutator($registry);

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Leitura protegida por lock compartilhado (LOCK_SH) — bloqueia até qualquer
     * escrita em andamento (withRegistryLock) terminar, evitando ler o arquivo no meio
     * de uma reescrita (torn read).
     *
     * @return array<int,array<string,array<string,mixed>>>
     */
    private function readRegistryLocked(): array
    {
        $handle = $this->openRegistryHandle();
        if ($handle === null) {
            return [];
        }

        try {
            if (! flock($handle, LOCK_SH)) {
                return [];
            }

            return $this->decodeRegistryHandle($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @return resource|null */
    private function openRegistryHandle()
    {
        $path = self::REGISTRY_FILE;
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        // 'c+' cria o arquivo se não existir, sem truncar um já existente, e permite
        // leitura+escrita — necessário para o ciclo lock -> ler -> truncar -> escrever.
        $handle = @fopen($path, 'c+');

        return $handle !== false ? $handle : null;
    }

    /** @param resource $handle */
    private function decodeRegistryHandle($handle): array
    {
        rewind($handle);
        $content = stream_get_contents($handle);
        if ($content === false || $content === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
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
