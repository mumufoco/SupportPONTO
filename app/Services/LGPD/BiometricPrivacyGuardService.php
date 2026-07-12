<?php

namespace App\Services\LGPD;

use App\Services\Biometric\BiometricDataPurgeService;

class BiometricPrivacyGuardService
{
    public function __construct(
        private ?BiometricDataPurgeService $purgeService = null,
        private ?PrivacyAuditLoggerService $auditLogger = null
    ) {
        $this->purgeService = $this->purgeService ?? new BiometricDataPurgeService();
        $this->auditLogger = $this->auditLogger ?? new PrivacyAuditLoggerService();
    }

    /**
     * Nunca exporta template bruto, hash completo ou caminho físico.
     *
     * @param array<int,array<string,mixed>|object> $records
     * @return array<int,array<string,mixed>>
     */
    public function redactForExport(array $records): array
    {
        return array_map(static function ($record): array {
            $row = is_array($record) ? (object) $record : $record;

            return [
                '@type' => 'BiometricRecord',
                'id' => $row->id ?? null,
                'biometricType' => $row->biometric_type ?? $row->record_type ?? null,
                'status' => $row->status ?? (($row->active ?? null) ? 'active' : 'inactive'),
                'createdAt' => $row->created_at ?? null,
                'updatedAt' => $row->updated_at ?? null,
                'sensitiveDataRedacted' => true,
                'redactionReason' => 'Templates, hashes completos e caminhos físicos não são exportados por segurança LGPD.',
            ];
        }, $records);
    }

    /**
     * @param array<int,string> $types
     * @return array<string,mixed>
     */
    public function purgeEmployeeBiometrics(int $employeeId, array $types, string $reason, ?int $actorId = null): array
    {
        $result = $this->purgeService->purgeEmployee($employeeId, $types, $reason);
        $this->auditLogger->log($actorId, 'LGPD_BIOMETRIC_PURGE', 'biometric_templates', $employeeId, null, [
            'types' => $types,
            'reason' => $reason,
            'result' => [
                'success' => $result['success'] ?? false,
                'deleted_records' => $result['deleted_records'] ?? 0,
                'deleted_files' => $result['deleted_files'] ?? 0,
            ],
        ], 'Expurgo de biometria executado por fluxo LGPD.', 'warning');

        return $result;
    }
}
