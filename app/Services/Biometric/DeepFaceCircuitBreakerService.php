<?php

namespace App\Services\Biometric;

/**
 * Circuit breaker simples e persistente para isolar a aplicação principal
 * quando a API DeepFace estiver offline, lenta ou retornando erros 5xx.
 */
class DeepFaceCircuitBreakerService
{
    private string $stateFile;
    private bool $enabled;
    private int $failureThreshold;
    private int $openSeconds;
    private int $halfOpenAfterSeconds;

    public function __construct(?string $stateFile = null)
    {
        $this->stateFile = $stateFile ?? rtrim(WRITEPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'deepface-circuit-breaker.json';
        $this->enabled = filter_var(env('DEEPFACE_CIRCUIT_BREAKER_ENABLED', true), FILTER_VALIDATE_BOOL);
        $this->failureThreshold = max(1, (int) env('DEEPFACE_CIRCUIT_FAILURE_THRESHOLD', 3));
        $this->openSeconds = max(15, (int) env('DEEPFACE_CIRCUIT_OPEN_SECONDS', 120));
        $this->halfOpenAfterSeconds = max(10, (int) env('DEEPFACE_CIRCUIT_HALF_OPEN_AFTER', $this->openSeconds));
    }

    public function beforeCall(string $operation): array
    {
        if (!$this->enabled) {
            return ['allowed' => true, 'state' => 'disabled'];
        }

        $state = $this->readState();
        $now = time();

        if (($state['state'] ?? 'closed') === 'open') {
            $openedAt = (int) ($state['opened_at'] ?? 0);
            if ($openedAt > 0 && ($now - $openedAt) >= $this->halfOpenAfterSeconds) {
                $state['state'] = 'half_open';
                $state['half_open_operation'] = $operation;
                $state['updated_at'] = $now;
                $this->writeState($state);

                return ['allowed' => true, 'state' => 'half_open'];
            }

            return [
                'allowed' => false,
                'state' => 'open',
                'retry_after' => max(1, $this->openSeconds - ($now - $openedAt)),
                'message' => 'Serviço de reconhecimento facial temporariamente indisponível. Tente novamente mais tarde.',
            ];
        }

        return ['allowed' => true, 'state' => (string) ($state['state'] ?? 'closed')];
    }

    public function recordSuccess(string $operation): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->writeState([
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null,
            'last_success_at' => time(),
            'last_operation' => $operation,
            'last_error' => null,
            'updated_at' => time(),
        ]);
    }

    public function recordFailure(string $operation, string $reason, int $statusCode = 0): void
    {
        if (!$this->enabled) {
            return;
        }

        $state = $this->readState();
        $failureCount = (int) ($state['failure_count'] ?? 0) + 1;
        $nextState = $failureCount >= $this->failureThreshold ? 'open' : 'closed';
        $now = time();

        $state = [
            'state' => $nextState,
            'failure_count' => $failureCount,
            'opened_at' => $nextState === 'open' ? ($state['opened_at'] ?? $now) : null,
            'last_failure_at' => $now,
            'last_operation' => $operation,
            'last_error' => mb_substr($reason, 0, 240),
            'last_status_code' => $statusCode,
            'updated_at' => $now,
        ];

        $previousState = (string) ($state['state'] ?? 'closed');
        $this->writeState($state);

        if ($nextState === 'open' && $previousState !== 'open') {
            log_message('warning', 'DeepFace circuit breaker opened after {count} failures. Last operation: {operation}. Last error: {error}', [
                'count' => $failureCount,
                'operation' => $operation,
                'error' => $reason,
            ]);
        }
    }

    public function state(): array
    {
        $state = $this->readState();
        $state['enabled'] = $this->enabled;
        $state['failure_threshold'] = $this->failureThreshold;
        $state['open_seconds'] = $this->openSeconds;
        $state['half_open_after_seconds'] = $this->halfOpenAfterSeconds;

        return $state;
    }

    public function reset(): void
    {
        $this->writeState([
            'state' => 'closed',
            'failure_count' => 0,
            'opened_at' => null,
            'last_error' => null,
            'updated_at' => time(),
        ]);
    }

    private function readState(): array
    {
        if (!is_file($this->stateFile) || !is_readable($this->stateFile)) {
            return ['state' => 'closed', 'failure_count' => 0, 'opened_at' => null];
        }

        $decoded = json_decode((string) @file_get_contents($this->stateFile), true);
        return is_array($decoded) ? $decoded : ['state' => 'closed', 'failure_count' => 0, 'opened_at' => null];
    }

    private function writeState(array $state): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($this->stateFile, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod($this->stateFile, 0666);
    }
}
