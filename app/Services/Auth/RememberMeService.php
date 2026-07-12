<?php

namespace App\Services\Auth;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;

/**
 * Cookie "lembrar-me" com esquema série+validador (Barry Jaspan) — ver auditoria
 * BAIXO-03. remember_token_series identifica o dispositivo logado e permanece
 * estável entre rotações; remember_token (o validador) é hasheado e trocado a cada
 * uso. Reapresentar um validador antigo para uma série existente é o indício
 * clássico de cookie roubado, e é tratado como tal (revoga a série inteira + audita),
 * em vez de indistinguível de "cookie comum inválido/expirado".
 */
class RememberMeService
{
    private const COOKIE_NAME = 'remember_token';
    private const DEFAULT_TTL_SECONDS = 2592000; // 30 dias

    protected EmployeeModel $employeeModel;
    protected SettingModel $settingModel;
    protected AuditModel $auditModel;

    /** Série resolvida pela última chamada bem-sucedida a resolveUserFromCookie(), para issue() reaproveitar na rotação. */
    private ?string $currentSeries = null;

    public function __construct(?EmployeeModel $employeeModel = null, ?SettingModel $settingModel = null, ?AuditModel $auditModel = null)
    {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->settingModel = $settingModel ?? new SettingModel();
        $this->auditModel = $auditModel ?? new AuditModel();
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settingModel->get('enable_remember_me', true);
    }

    public function getTtlSeconds(): int
    {
        $ttl = (int) $this->settingModel->get('remember_me_duration', self::DEFAULT_TTL_SECONDS);

        if ($ttl < 86400) {
            return 86400;
        }

        if ($ttl > 31536000) {
            return 31536000;
        }

        return $ttl;
    }

    /**
     * Emite um novo cookie "lembrar-me". Em login novo, gera uma série nova. Ao
     * rotacionar um cookie já validado por resolveUserFromCookie() nesta mesma
     * instância, reaproveita a série existente (para que uma reapresentação futura do
     * validador antigo ainda seja detectável como reuso da mesma série).
     */
    public function issue(int $userId, ?string $series = null): void
    {
        if (! $this->isEnabled()) {
            $this->clearPersistedToken($userId);
            $this->clear();
            return;
        }

        $series ??= $this->currentSeries ?? bin2hex(random_bytes(16));

        $ttl = $this->getTtlSeconds();
        $validator = bin2hex(random_bytes(32));
        $hashedValidator = hash('sha256', $validator);

        try {
            $this->employeeModel->update($userId, [
                'remember_token' => $hashedValidator,
                'remember_token_series' => $series,
                'remember_token_expires' => date('Y-m-d H:i:s', time() + $ttl),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Could not save remember-me token: ' . $e->getMessage());
            return;
        }

        $cookieConfig = config('Cookie');
        setcookie(
            self::COOKIE_NAME,
            $series . '.' . $validator,
            [
                'expires' => time() + $ttl,
                'path' => $cookieConfig->path,
                'domain' => $cookieConfig->domain,
                'secure' => $cookieConfig->secure,
                'httponly' => $cookieConfig->httponly,
                'samesite' => $cookieConfig->samesite,
            ]
        );
    }

    public function clear(): void
    {
        $cookieConfig = config('Cookie');
        setcookie(
            self::COOKIE_NAME,
            '',
            [
                'expires' => time() - 3600,
                'path' => $cookieConfig->path,
                'domain' => $cookieConfig->domain,
                'secure' => $cookieConfig->secure,
                'httponly' => $cookieConfig->httponly,
                'samesite' => $cookieConfig->samesite,
            ]
        );
    }

    public function clearPersistedToken(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $this->employeeModel->update($userId, [
                'remember_token' => null,
                'remember_token_series' => null,
                'remember_token_expires' => null,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Could not clear remember-me token: ' . $e->getMessage());
        }
    }

    public function resolveUserFromCookie(): ?object
    {
        if (! $this->isEnabled()) {
            $this->clear();
            return null;
        }

        $cookieValue = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (! is_string($cookieValue) || trim($cookieValue) === '') {
            return null;
        }

        [$series, $validator] = array_pad(explode('.', trim($cookieValue), 2), 2, null);
        if ($series === null || $validator === null || $series === '' || $validator === '') {
            return null;
        }

        $user = $this->employeeModel
            ->where('remember_token_series', $series)
            ->where('active', true)
            ->first();

        if (! $user) {
            // Série desconhecida: cookie comum inválido/expirado, nada suspeito.
            return null;
        }

        $expired = empty($user->remember_token_expires) || strtotime((string) $user->remember_token_expires) < time();
        $validatorMatches = ! empty($user->remember_token) && hash_equals((string) $user->remember_token, hash('sha256', $validator));

        if (! $validatorMatches) {
            // Série existe mas o validador não bate: reuso de um token já rotacionado
            // — indício de cookie roubado. Revoga a série inteira (nega tanto o
            // possível atacante quanto o dono legítimo, que precisa logar de novo) e
            // audita, em vez de simplesmente negar como se fosse um cookie comum.
            $this->clearPersistedToken((int) $user->id);
            $this->auditModel->log(
                (int) $user->id,
                'REMEMBER_ME_REUSE_DETECTED',
                'employees',
                (int) $user->id,
                null,
                ['series' => $series],
                'Possível roubo de cookie "lembrar-me": token já rotacionado foi reapresentado. Sessão lembrada revogada.',
                'warning'
            );

            return null;
        }

        if ($expired) {
            return null;
        }

        $this->currentSeries = $series;

        return $user;
    }
}
