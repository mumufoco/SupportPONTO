<?php

namespace App\Services\Auth;

use App\Models\EmployeeModel;
use App\Models\SettingModel;

class RememberMeService
{
    private const COOKIE_NAME = 'remember_token';
    private const DEFAULT_TTL_SECONDS = 2592000; // 30 dias

    protected EmployeeModel $employeeModel;
    protected SettingModel $settingModel;

    public function __construct(?EmployeeModel $employeeModel = null, ?SettingModel $settingModel = null)
    {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->settingModel = $settingModel ?? new SettingModel();
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

    public function issue(int $userId): void
    {
        if (! $this->isEnabled()) {
            $this->clearPersistedToken($userId);
            $this->clear();
            return;
        }

        $ttl = $this->getTtlSeconds();
        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);

        try {
            $this->employeeModel->update($userId, [
                'remember_token' => $hashedToken,
                'remember_token_expires' => date('Y-m-d H:i:s', time() + $ttl),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Could not save remember-me token: ' . $e->getMessage());
        }

        $cookieConfig = config('Cookie');
        setcookie(
            self::COOKIE_NAME,
            $token,
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

        $token = $_COOKIE[self::COOKIE_NAME] ?? null;
        if (! is_string($token) || trim($token) === '') {
            return null;
        }

        $hashedToken = hash('sha256', $token);
        $user = $this->employeeModel
            ->where('remember_token', $hashedToken)
            ->where('remember_token_expires >', date('Y-m-d H:i:s'))
            ->where('active', true)
            ->first();

        return $user ?: null;
    }
}
