<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditModel;
use App\Support\SensitiveDataSanitizer;
use Config\Services;

/**
 * Facade de trilha de auditoria corporativa.
 *
 * Objetivo: centralizar eventos críticos com contrato único de entidade,
 * contexto, origem e request id. Falhas de auditoria nunca devem quebrar a
 * operação principal, mas sempre são registradas no log técnico.
 */
class AuditTrailService
{
    public function __construct(private ?AuditModel $auditModel = null)
    {
        $this->auditModel ??= Services::auditModel();
    }

    /**
     * @param array<string,mixed>|null $oldValues
     * @param array<string,mixed>|null $newValues
     * @param array<string,mixed> $context
     */
    public function record(
        string $action,
        ?int $actorId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        string $level = 'info',
        array $context = [],
        ?string $source = null,
        ?string $actorType = null,
    ): bool {
        try {
            return $this->auditModel->log(
                $actorId,
                AuditEventCatalog::normalize($action),
                $entityType,
                $entityId,
                $oldValues,
                $newValues,
                $description,
                $level,
                $this->safeContext($context),
                $source,
                $actorType
            );
        } catch (\Throwable $e) {
            log_message('error', '[AuditTrailService] Falha ao registrar evento: ' . $e->getMessage());
            return false;
        }
    }

    /** @param array<string,mixed> $context */
    public function system(string $action, string $description, array $context = [], string $level = 'info'): bool
    {
        return $this->record($action, null, 'system', null, null, null, $description, $level, $context, is_cli() ? 'cli' : 'system', 'system');
    }

    /** @param array<string,mixed> $context */
    public function security(?int $actorId, string $action, string $description, array $context = [], string $level = 'warning'): bool
    {
        return $this->record($action, $actorId, 'security', $actorId, null, null, $description, $level, $context, null, $actorId === null ? 'anonymous' : 'user');
    }

    /** @param array<string,mixed> $context */
    public function employeeEvent(string $action, int $actorId, int $employeeId, ?array $oldValues, ?array $newValues, string $description, array $context = []): bool
    {
        return $this->record($action, $actorId, 'employees', $employeeId, $oldValues, $newValues, $description, 'info', $context);
    }

    /** @param array<string,mixed> $context */
    public function timesheetEvent(string $action, int $actorId, int $punchId, ?array $newValues, string $description, array $context = []): bool
    {
        return $this->record($action, $actorId, 'time_punches', $punchId, null, $newValues, $description, 'info', $context);
    }

    /** @param array<string,mixed> $context */
    private function safeContext(array $context): array
    {
        $sanitized = SensitiveDataSanitizer::sanitizeForLogs($context);
        return is_array($sanitized) ? $sanitized : [];
    }
}
