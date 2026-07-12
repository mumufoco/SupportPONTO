<?php

namespace App\Services\Biometric;

use App\Models\AuditModel;
use Exception;

class FingerprintAuditService
{
    protected AuditModel $auditModel;

    public function __construct()
    {
        $this->auditModel = new AuditModel();
    }

    public function log(
        ?int $userId,
        string $action,
        ?string $tableName = null,
        ?int $recordId = null,
        ?string $description = null,
        string $level = 'info'
    ): void {
        try {
            $this->auditModel->log($userId, $action, $tableName, $recordId, null, [], $description, $level);
        } catch (Exception $e) {
            log_message('error', 'Fingerprint audit logging error: ' . $e->getMessage());
        }
    }
}
