<?php

namespace App\Services\Audit;

use App\Models\AuditModel;

class CanonicalAuditLogger
{
    public function __construct(private ?AuditModel $auditModel = null)
    {
        $this->auditModel ??= new AuditModel();
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    public function logEntityEvent(
        ?int $userId,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        string $level = 'info',
    ): bool {
        return $this->auditModel->insertCanonical([
            'user_id' => $userId,
            'action' => strtoupper($action),
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'description' => $description,
            'level' => $level,
        ]);
    }
}
