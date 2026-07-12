<?php

namespace App\Services\Timesheet;

use CodeIgniter\Session\Session;
use Config\Services;

/**
 * Armazena o contexto transitório das tentativas de ponto que podem abrir
 * justificativa automática por falha técnica.
 */
class PendingPunchAttemptStore
{
    private const SESSION_KEY = 'pending_punch_context';
    private const TTL_SECONDS = 1800;
    private const MAX_ATTEMPTS = 20;

    private Session $session;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?? Services::session();
    }

    public function clearForEmployee(int $employeeId): void
    {
        $context = $this->getContext();
        if (($context['employee_id'] ?? null) !== $employeeId) {
            return;
        }

        $this->session->remove(self::SESSION_KEY);
    }

    public function getContextForEmployee(int $employeeId): array
    {
        $context = $this->getContext();
        if (($context['employee_id'] ?? null) !== $employeeId) {
            return [];
        }

        if ($this->isExpired($context)) {
            $this->session->remove(self::SESSION_KEY);
            return [];
        }

        return $context;
    }

    public function registerAttempt(int $employeeId, array $attempt, array $enabledMethods = []): array
    {
        $context = $this->getContextForEmployee($employeeId);
        if ($context === []) {
            $context = [
                'employee_id' => $employeeId,
                'created_at' => date('c'),
                'updated_at' => date('c'),
                'attempt_log' => [],
                'enabled_methods' => array_values(array_unique($enabledMethods)),
                'eligibility_snapshot' => null,
            ];
        }

        $attempt['timestamp'] = $attempt['timestamp'] ?? date('c');
        $attempt['status'] = isset($attempt['status']) ? (int) $attempt['status'] : null;
        $attempt['method'] = (string) ($attempt['method'] ?? 'unknown');
        $attempt['error_code'] = (string) ($attempt['error_code'] ?? 'service_unavailable');
        $attempt['message'] = (string) ($attempt['message'] ?? 'Falha ao registrar ponto.');

        $context['attempt_log'][] = $attempt;
        $context['attempt_log'] = $this->trimAttemptLog(
            $context['attempt_log'],
            $context['enabled_methods'] ?? []
        );

        if ($enabledMethods !== []) {
            $context['enabled_methods'] = $this->normalizeMethods($enabledMethods);
        }

        $context['updated_at'] = date('c');
        $this->session->set(self::SESSION_KEY, $context);

        return $context;
    }

    public function updateEligibility(int $employeeId, array $eligibility): void
    {
        $context = $this->getContextForEmployee($employeeId);
        if ($context === []) {
            return;
        }

        $context['eligibility_snapshot'] = $eligibility;
        $context['updated_at'] = date('c');
        $this->session->set(self::SESSION_KEY, $context);
    }


    /**
     * Preserva o histórico recente sem perder a cobertura mínima por método.
     *
     * Estratégia:
     * - mantém a tentativa mais recente de cada método habilitado já tentado
     * - preenche o restante com as tentativas mais novas até o limite total
     *
     * @param array<int, array<string, mixed>> $attemptLog
     * @param array<int, string> $enabledMethods
     * @return array<int, array<string, mixed>>
     */
    private function trimAttemptLog(array $attemptLog, array $enabledMethods): array
    {
        if (count($attemptLog) <= self::MAX_ATTEMPTS) {
            return array_values($attemptLog);
        }

        $enabledMethods = $this->normalizeMethods($enabledMethods);
        $preservedIndexes = [];

        for ($i = count($attemptLog) - 1; $i >= 0; $i--) {
            $method = (string) ($attemptLog[$i]['method'] ?? '');
            if ($method === '' || !in_array($method, $enabledMethods, true)) {
                continue;
            }

            if (!isset($preservedIndexes[$method])) {
                $preservedIndexes[$method] = $i;
            }
        }

        $selectedIndexes = array_values($preservedIndexes);

        for ($i = count($attemptLog) - 1; $i >= 0 && count($selectedIndexes) < self::MAX_ATTEMPTS; $i--) {
            if (in_array($i, $selectedIndexes, true)) {
                continue;
            }

            $selectedIndexes[] = $i;
        }

        sort($selectedIndexes);

        $trimmed = [];
        foreach ($selectedIndexes as $index) {
            if (isset($attemptLog[$index])) {
                $trimmed[] = $attemptLog[$index];
            }
        }

        return array_values($trimmed);
    }

    /**
     * @param array<int, string> $methods
     * @return array<int, string>
     */
    private function normalizeMethods(array $methods): array
    {
        $normalized = [];
        foreach ($methods as $method) {
            $method = trim((string) $method);
            if ($method === '') {
                continue;
            }

            if (!in_array($method, $normalized, true)) {
                $normalized[] = $method;
            }
        }

        return $normalized;
    }

    private function getContext(): array
    {
        $context = $this->session->get(self::SESSION_KEY);
        return is_array($context) ? $context : [];
    }

    private function isExpired(array $context): bool
    {
        $updatedAt = strtotime((string) ($context['updated_at'] ?? $context['created_at'] ?? ''));
        if ($updatedAt === false) {
            return true;
        }

        return $updatedAt < (time() - self::TTL_SECONDS);
    }
}
