<?php

namespace App\Services\Biometric\Fingerprint;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;

class FingerprintTemplateStateService
{
    private BiometricTemplateModel $biometricModel;
    private EmployeeModel $employeeModel;
    private UserConsentModel $consentModel;

    public function __construct(BiometricTemplateModel $biometricModel, EmployeeModel $employeeModel, UserConsentModel $consentModel)
    {
        $this->biometricModel = $biometricModel;
        $this->employeeModel = $employeeModel;
        $this->consentModel = $consentModel;
    }

    public function employeeExists(int $employeeId): bool
    {
        return $this->employeeModel->find($employeeId) !== null;
    }

    public function hasValidConsent(int $employeeId): bool
    {
        return $this->consentModel->hasConsent($employeeId, 'biometric_fingerprint')
            || $this->consentModel->hasConsent($employeeId, 'biometric_data');
    }

    public function activeTemplatesCount(int $employeeId): int
    {
        return $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'fingerprint')
            ->where('is_active', true)
            ->countAllResults();
    }

    public function hasFingerPositionTemplate(int $employeeId, string $fingerPosition): bool
    {
        return (bool) $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'fingerprint')
            ->where('finger_position', $fingerPosition)
            ->where('is_active', true)
            ->first();
    }

    public function setEmployeeFingerprintFlag(int $employeeId, bool $enabled): void
    {
        $this->employeeModel->update($employeeId, ['has_fingerprint_biometric' => $enabled]);
    }

    public function activeTemplatesForEmployee(int $employeeId): array
    {
        return $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'fingerprint')
            ->where('is_active', true)
            ->findAll();
    }

    public function activeTemplatesForIdentification(?string $department = null): array
    {
        $builder = $this->biometricModel->where('biometric_type', 'fingerprint')->where('is_active', true);

        if ($department !== null && $department !== '') {
            $builder->join('employees', 'employees.id = biometric_templates.employee_id')
                ->where('employees.department', $department);
        }

        return $builder->findAll();
    }

    public function findTemplate(int $templateId): mixed
    {
        return $this->biometricModel->find($templateId);
    }

    public function deactivateTemplate(int $templateId): bool
    {
        return (bool) $this->biometricModel->update($templateId, ['is_active' => false, 'deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function touchTemplateUsage(int $templateId): void
    {
        $template = $this->biometricModel->find($templateId);
        if (!$template || !is_object($template)) {
            return;
        }

        $this->biometricModel->update($templateId, [
            'last_used_at' => date('Y-m-d H:i:s'),
            'usage_count' => ((int) ($template->usage_count ?? 0)) + 1,
        ]);
    }

    public function insertTemplate(array $data): int
    {
        return (int) $this->biometricModel->insert($data);
    }
}
