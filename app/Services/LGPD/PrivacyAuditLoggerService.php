<?php

namespace App\Services\LGPD;

use App\Models\AuditModel;

class PrivacyAuditLoggerService
{
    private AuditModel $auditModel;

    public function __construct(?AuditModel $auditModel = null)
    {
        $this->auditModel = $auditModel ?? new AuditModel();
    }

    /**
     * @param array<string,mixed>|null $oldValues
     * @param array<string,mixed>|null $newValues
     */
    public function log(?int $actorId, string $action, string $entity, ?int $entityId, ?array $oldValues, ?array $newValues, string $description, string $severity = 'info'): void
    {
        $payload = $newValues ?? [];
        $payload['privacy_event'] = true;
        $payload['logged_at'] = date('c');

        $this->auditModel->log($actorId, $action, $entity, $entityId, $oldValues, $payload, $description, $severity);
    }
}
