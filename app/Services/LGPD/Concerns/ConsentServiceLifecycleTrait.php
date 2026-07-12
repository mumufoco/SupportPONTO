<?php

namespace App\Services\LGPD\Concerns;

trait ConsentServiceLifecycleTrait
{
    public function grant(
        int $employeeId,
        string $consentType,
        string $purpose,
        string $consentText,
        ?string $legalBasis = null,
        string $version = '1.0'
    ): array {
        try {
            // Validate employee exists
            $employee = $this->employeeModel->find($employeeId);
            if (!$employee) {
                return [
                    'success' => false,
                    'message' => 'Funcionário não encontrado',
                    'consent_id' => null,
                ];
            }

            // Validate consent type
            $validTypes = [
                'biometric_face',
                'biometric_fingerprint',
                'geolocation',
                'data_processing',
                'marketing',
                'data_sharing'
            ];

            if (!in_array($consentType, $validTypes)) {
                return [
                    'success' => false,
                    'message' => 'Tipo de consentimento inválido',
                    'consent_id' => null,
                ];
            }

            // Grant consent using model
            $result = $this->consentModel->grant(
                $employeeId,
                $consentType,
                $purpose,
                $consentText,
                $legalBasis,
                $version
            );

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Erro ao registrar consentimento',
                    'consent_id' => null,
                ];
            }

            // Get the newly created consent
            $consent = $this->consentModel->getActiveConsent($employeeId, $consentType);

            // Audit log
            $this->writeAudit(
                $employeeId,
                'GRANT_CONSENT',
                'user_consents',
                $consent->id ?? null,
                null,
                [
                    'consent_type' => $consentType,
                    'purpose' => $purpose,
                    'version' => $version,
                    'granted_at' => date('Y-m-d H:i:s'),
                ],
                "Consentimento '{$consentType}' concedido por {$employee->name}",
                'info'
            );

            // Send notification to DPO/Admin
            $this->notifyConsentGranted($employee, $consentType, $consent);

            return [
                'success' => true,
                'message' => 'Consentimento registrado com sucesso',
                'consent_id' => $consent->id ?? null,
            ];

        } catch (\Exception $e) {
            log_message('error', 'ConsentService::grant() error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erro ao processar consentimento. A ocorrência foi registrada para análise.',
                'consent_id' => null,
            ];
        }
    }
    public function revoke(
        int $employeeId,
        string $consentType,
        ?string $reason = null
    ): array {
        try {
            // Get current consent
            $consent = $this->consentModel->getActiveConsent($employeeId, $consentType);

            if (!$consent) {
                return [
                    'success' => false,
                    'message' => 'Consentimento não encontrado ou já revogado',
                    'deleted_records' => 0,
                ];
            }

            $employee = $this->employeeModel->find($employeeId);

            // Store old values for audit
            $oldValues = [
                'consent_type' => $consent->consent_type,
                'granted' => $consent->granted,
                'granted_at' => $consent->granted_at,
            ];

            // Revoke consent
            $result = $this->consentModel->revoke($employeeId, $consentType, $reason);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Erro ao revogar consentimento',
                    'deleted_records' => 0,
                ];
            }

            $deletedRecords = 0;

            // Delete biometric data if applicable (LGPD Art. 16)
            if (in_array($consentType, ['biometric_face', 'biometric_fingerprint'])) {
                $deletedRecords = $this->deleteBiometricData($employeeId, $consentType);
            }

            // Audit log
            $this->writeAudit(
                $employeeId,
                'REVOKE_CONSENT',
                'user_consents',
                $consent->id,
                $oldValues,
                [
                    'granted' => false,
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'reason' => $reason,
                    'lawful_basis_review_required' => in_array($consentType, ['biometric_face', 'biometric_fingerprint'], true),
                    'deleted_biometric_records' => $deletedRecords,
                ],
                "Consentimento '{$consentType}' revogado por {$employee->name}. Motivo: " . ($reason ?? 'Não informado'),
                'warning'
            );

            // Send notification to DPO/Admin
            $this->notifyConsentRevoked($employee, $consentType, $reason, $deletedRecords);

            return [
                'success' => true,
                'message' => 'Consentimento revogado com sucesso',
                'deleted_records' => $deletedRecords,
            ];

        } catch (\Exception $e) {
            log_message('error', 'ConsentService::revoke() error: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erro ao revogar consentimento. A ocorrência foi registrada para análise.',
                'deleted_records' => 0,
            ];
        }
    }
    protected function deleteBiometricData(int $employeeId, string $consentType): int
    {
        $recordType = match($consentType) {
            'biometric_face' => 'face',
            'biometric_fingerprint' => 'fingerprint',
            default => null,
        };

        if (!$recordType) {
            return 0;
        }

        // Get all biometric records of this type
        $records = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('record_type', $recordType)
            ->findAll();

        $count = count($records);

        // Delete records (hard delete for biometric data)
        $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('record_type', $recordType)
            ->delete();

        // Audit each deletion
        foreach ($records as $record) {
            $this->writeAudit(
                $employeeId,
                'DELETE_BIOMETRIC',
                'biometric_records',
                $record->id,
                [
                    'record_type' => $record->record_type,
                    'template_hash' => substr($record->template_hash, 0, 20) . '...',
                ],
                null,
                "Dado biométrico deletado após revogação de consentimento '{$consentType}'",
                'warning'
            );
        }

        log_message('info', "Deleted {$count} biometric records (type: {$recordType}) for employee {$employeeId}");

        return $count;
    }

}
