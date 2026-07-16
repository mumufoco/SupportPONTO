<?php

namespace App\Services\Security\RateLimit;

use App\Support\BootstrapEnv;

class RateLimitPolicyService
{
    private array $limits;
    private array $whitelist;

    public function __construct()
    {
        $this->limits = [
            'login' => ['max_attempts' => 5, 'decay_minutes' => 15],
            'api' => ['max_attempts' => 60, 'decay_minutes' => 1],
            'password_reset' => ['max_attempts' => 3, 'decay_minutes' => 60],
            '2fa_verify' => ['max_attempts' => 5, 'decay_minutes' => 10],
            'register' => ['max_attempts' => 5, 'decay_minutes' => 15],
            'biometric' => ['max_attempts' => 10, 'decay_minutes' => 1],
            'oauth_token' => ['max_attempts' => 5, 'decay_minutes' => 15],
            'critical_action' => ['max_attempts' => 5, 'decay_minutes' => 10],
            'general' => ['max_attempts' => 100, 'decay_minutes' => 1],
        ];

        $this->applyConfiguredLoginLimit();
        $this->whitelist = $this->loadWhitelist();
    }

    /**
     * Sobrescreve o limite de tentativas de login com os valores configurados
     * em Admin > Configuracoes > Autenticacao (max_login_attempts / lockout_duration),
     * quando definidos. Mantem o default hardcoded como fallback -- nunca falha
     * a construcao do servico se as settings nao estiverem disponiveis.
     */
    private function applyConfiguredLoginLimit(): void
    {
        try {
            $settings = \Config\Services::settings(false);
            $maxAttempts = (int) ($settings->get('max_login_attempts') ?? 0);
            $lockoutSeconds = (int) ($settings->get('lockout_duration') ?? 0);

            if ($maxAttempts <= 0 && $lockoutSeconds <= 0) {
                return;
            }

            $current = $this->limits['login'];
            $this->limits['login'] = [
                'max_attempts' => $maxAttempts > 0 ? $maxAttempts : $current['max_attempts'],
                'decay_minutes' => $lockoutSeconds > 0 ? max(1, (int) round($lockoutSeconds / 60)) : $current['decay_minutes'],
            ];
        } catch (\Throwable $e) {
            // Settings indisponiveis (ex.: durante bootstrap/CLI sem DB) -- mantem o default hardcoded.
        }
    }

    public function getLimit(string $type): array
    {
        return $this->limits[$type] ?? $this->limits['general'];
    }

    public function setLimit(string $type, int $maxAttempts, int $decayMinutes): void
    {
        $this->limits[$type] = ['max_attempts' => $maxAttempts, 'decay_minutes' => $decayMinutes];
    }

    public function getRawLimit(string $type): ?array
    {
        return $this->limits[$type] ?? null;
    }

    public function isWhitelisted(string $ip): bool
    {
        return in_array($ip, $this->whitelist, true);
    }

    public function getWhitelist(): array
    {
        return $this->whitelist;
    }

    public function addToWhitelist(string $ip): void
    {
        if (!in_array($ip, $this->whitelist, true)) {
            $this->whitelist[] = $ip;
        }
    }

    public function removeFromWhitelist(string $ip): void
    {
        $this->whitelist = array_values(array_filter($this->whitelist, static fn(string $item) => $item !== $ip));
    }

    public function formatErrorMessage(array $limitInfo): string
    {
        if (!empty($limitInfo['whitelisted'])) {
            return 'IP whitelisted - no rate limit';
        }

        $resetIn = (int) ($limitInfo['reset_at'] ?? time()) - time();
        $minutes = max(1, (int) ceil($resetIn / 60));

        return "Muitas tentativas. Tente novamente em {$minutes} minuto(s).";
    }

    private function loadWhitelist(): array
    {
        $whitelist = BootstrapEnv::get('RATE_LIMIT_WHITELIST', null, ['rateLimit.whitelist']);
        $result = ['127.0.0.1', '::1'];

        if ($whitelist) {
            $result = array_merge($result, array_map('trim', explode(',', $whitelist)));
        }

        return array_values(array_unique($result));
    }
}
