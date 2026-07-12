<?php

namespace App\Traits;

use App\Models\AuditModel;

/**
 * Auditable Trait
 *
 * Provides automatic audit logging for model operations
 *
 * Usage:
 * class MyModel extends Model {
 *     use Auditable;
 *
 *     protected $auditExclude = ['password', 'token']; // Optional: fields to exclude from audit
 *     protected $auditLevel = 'info'; // Optional: default audit level
 * }
 */
trait Auditable
{
    /**
     * Fields to exclude from audit logging
     * @var array
     */
    protected $auditExclude = ['password', 'password_hash', 'remember_token', 'reset_token'];

    /**
     * Default audit level
     * @var string
     */
    protected $auditLevel = 'info';

    /**
     * After insert callback
     * Automatically logs record creation
     */
    protected function auditAfterInsert(array $data): array
    {
        if (!$this->shouldAudit()) {
            return $data;
        }

        try {
            $auditModel = new AuditModel();

            $insertedData = $data['data'] ?? [];
            $insertedId = $data['id'] ?? null;

            // Get current user ID from session
            $userId = $this->getCurrentUserId();

            // Clean sensitive data
            $cleanData = $this->cleanAuditData($insertedData);

            $auditModel->log(
                $userId,
                'CREATE',
                $this->table,
                $insertedId,
                null,
                $cleanData,
                $this->getAuditDescription('CREATE', $insertedId),
                $this->auditLevel
            );

        } catch (\Exception $e) {
            // Don't fail the operation if audit logging fails
            log_message('error', 'Audit logging failed in afterInsert: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * After update callback
     * Automatically logs record updates with old/new values
     */
    protected function auditAfterUpdate(array $data): array
    {
        if (!$this->shouldAudit()) {
            return $data;
        }

        try {
            $auditModel = new AuditModel();

            $updatedData = $data['data'] ?? [];
            $affectedIds = $data['id'] ?? [];

            // Handle single ID or array of IDs
            if (!is_array($affectedIds)) {
                $affectedIds = [$affectedIds];
            }

            $userId = $this->getCurrentUserId();

            foreach ($affectedIds as $entityId) {
                // Get old values before update
                $oldRecord = $this->find($entityId);
                $oldValues = $oldRecord ? (array)$oldRecord : [];

                // Clean sensitive data
                $cleanOldValues = $this->cleanAuditData($oldValues);
                $cleanNewValues = $this->cleanAuditData($updatedData);

                // Only log if there are actual changes
                $changes = $this->getChanges($cleanOldValues, $cleanNewValues);

                if (!empty($changes)) {
                    $auditModel->log(
                        $userId,
                        'UPDATE',
                        $this->table,
                        $entityId,
                        $changes['old'],
                        $changes['new'],
                        $this->getAuditDescription('UPDATE', $entityId, $changes),
                        $this->auditLevel
                    );
                }
            }

        } catch (\Exception $e) {
            log_message('error', 'Audit logging failed in afterUpdate: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * After delete callback
     * Automatically logs record deletion
     */
    protected function auditAfterDelete(array $data): array
    {
        if (!$this->shouldAudit()) {
            return $data;
        }

        try {
            $auditModel = new AuditModel();

            $affectedIds = $data['id'] ?? [];
            $purge = $data['purge'] ?? false;

            if (!is_array($affectedIds)) {
                $affectedIds = [$affectedIds];
            }

            $userId = $this->getCurrentUserId();

            foreach ($affectedIds as $entityId) {
                // Try to get the record before it's deleted (for soft deletes)
                $oldRecord = null;

                if (!$purge && $this->useSoftDeletes) {
                    $oldRecord = $this->withDeleted()->find($entityId);
                }

                $oldValues = $oldRecord ? (array)$oldRecord : [];
                $cleanOldValues = $this->cleanAuditData($oldValues);

                $action = $purge ? 'PURGE' : 'DELETE';

                $auditModel->log(
                    $userId,
                    $action,
                    $this->table,
                    $entityId,
                    $cleanOldValues,
                    null,
                    $this->getAuditDescription($action, $entityId),
                    $purge ? 'warning' : $this->auditLevel
                );
            }

        } catch (\Exception $e) {
            log_message('error', 'Audit logging failed in afterDelete: ' . $e->getMessage());
        }

        return $data;
    }

    /**
     * Check if should audit this operation
     */
    protected function shouldAudit(): bool
    {
        // Skip audit for audit_logs table itself to avoid infinite loop
        if ($this->table === 'audits') {
            return false;
        }

        // Skip if explicitly disabled via property
        if (property_exists($this, 'enableAudit') && !$this->enableAudit) {
            return false;
        }

        return true;
    }

    /**
     * Get current user ID from session
     */
    protected function getCurrentUserId(): ?int
    {
        $session = session();

        // Get user ID from standardized session key
        $userId = $session->get('user_id') ?? null;

        return $userId ? (int)$userId : null;
    }

    /**
     * Clean audit data (remove sensitive fields)
     */
    protected function cleanAuditData(array $data): array
    {
        $cleaned = [];

        foreach ($data as $key => $value) {
            // Skip excluded fields
            if (in_array($key, $this->auditExclude)) {
                continue;
            }

            // Skip binary data
            if (is_resource($value)) {
                continue;
            }

            // Mask sensitive-looking fields
            if ($this->isSensitiveField($key)) {
                $cleaned[$key] = '[REDACTED]';
            } else {
                $cleaned[$key] = $value;
            }
        }

        return $cleaned;
    }

    /**
     * Check if field is sensitive
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        $sensitivePatterns = [
            '/password/i',
            '/token/i',
            '/secret/i',
            '/key/i',
            '/credential/i',
        ];

        foreach ($sensitivePatterns as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get changes between old and new values
     */
    protected function getChanges(array $oldValues, array $newValues): array
    {
        $changes = [
            'old' => [],
            'new' => [],
        ];

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            // Check if value actually changed
            if ($oldValue !== $newValue) {
                $changes['old'][$key] = $oldValue;
                $changes['new'][$key] = $newValue;
            }
        }

        return $changes;
    }

    /**
     * Get audit description
     */
    protected function getAuditDescription(string $action, ?int $entityId, ?array $changes = null): string
    {
        $tableName = ucfirst(str_replace('_', ' ', $this->table));

        $description = match($action) {
            'CREATE' => "Novo registro criado em {$tableName} (ID: {$entityId})",
            'UPDATE' => "Registro atualizado em {$tableName} (ID: {$entityId})",
            'DELETE' => "Registro deletado em {$tableName} (ID: {$entityId})",
            'PURGE' => "Registro removido permanentemente de {$tableName} (ID: {$entityId})",
            default => "Ação {$action} em {$tableName} (ID: {$entityId})",
        };

        // Add changed fields info for updates
        if ($action === 'UPDATE' && $changes && !empty($changes['new'])) {
            $fields = array_keys($changes['new']);
            $fieldsList = implode(', ', $fields);
            $description .= " - Campos alterados: {$fieldsList}";
        }

        return $description;
    }

    /**
     * Register callbacks
     * This method should be called in the model's __construct or initialize
     */
    protected function registerAuditCallbacks(): void
    {
        $this->beforeInsert[] = 'auditBeforeInsert';
        $this->afterInsert[] = 'auditAfterInsert';
        $this->afterUpdate[] = 'auditAfterUpdate';
        $this->afterDelete[] = 'auditAfterDelete';
    }

    /**
     * Before insert callback (for pre-processing if needed)
     */
    protected function auditBeforeInsert(array $data): array
    {
        // Can be used for additional pre-processing
        // Currently just passes through
        return $data;
    }
}
