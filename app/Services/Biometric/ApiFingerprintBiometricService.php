<?php

namespace App\Services\Biometric;

use App\Enums\Role;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;
use App\Services\Biometric\BiometricDataPurgeService;

class ApiFingerprintBiometricService
{
    private FingerprintService $fingerprintService;
    private UserConsentModel $consentModel;
    private BiometricTemplateModel $biometricModel;
    private EmployeeModel $employeeModel;
    private BiometricDataPurgeService $biometricDataPurgeService;

    public function __construct()
    {
        $this->fingerprintService = new FingerprintService();
        $this->consentModel = new UserConsentModel();
        $this->biometricModel = new BiometricTemplateModel();
        $this->employeeModel = new EmployeeModel();
        $this->biometricDataPurgeService = new BiometricDataPurgeService();
    }

    public function enroll(object $currentEmployee, int $employeeId, string $fingerprintData, string $fingerPosition, string $captureMethod, ?string $device = null): array
    {
        if ($employeeId !== (int) $currentEmployee->id && !$this->canManageOtherEmployeesBiometrics($currentEmployee)) {
            return ['success' => false, 'status' => 403, 'code' => 'forbidden', 'message' => 'Acesso negado.'];
        }

        $result = $this->fingerprintService->enroll(
            $employeeId,
            $fingerprintData,
            $fingerPosition,
            $captureMethod,
            [
                'device' => $device,
                'enrolled_by' => $currentEmployee->id,
                'enrolled_by_name' => $currentEmployee->name,
            ]
        );

        if (!$result['success']) {
            $statusCode = 400;
            $code = 'biometric_enroll_failed';
            if (isset($result['consent_required'])) {
                $statusCode = 403;
                $code = 'consent_required';
            } elseif (isset($result['duplicate'])) {
                $statusCode = 409;
                $code = 'biometric_duplicate';
            }

            return ['success' => false, 'status' => $statusCode, 'code' => $code, 'message' => $result['message']];
        }

        return ['success' => true, 'status' => 201, 'message' => 'Biometria cadastrada com sucesso.', 'data' => $result];
    }

    public function verify(object $currentEmployee, int $employeeId, string $fingerprintData): array
    {
        // ALTO-05 (auditoria): verify() era a única rota do módulo de digital sem checagem
        // de posse do employee_id — permitia testar a digital de qualquer employee_id
        // arbitrário, usando o endpoint como oráculo de identificação biométrica sem o
        // consentimento específico do titular para esse uso.
        if ($employeeId !== (int) $currentEmployee->id && !$this->canManageOtherEmployeesBiometrics($currentEmployee)) {
            return ['success' => false, 'status' => 403, 'code' => 'forbidden', 'message' => 'Acesso negado.'];
        }

        $result = $this->fingerprintService->verify($employeeId, $fingerprintData);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'biometric_verify_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'message' => 'Biometria validada com sucesso.', 'data' => $result];
    }

    public function identify(string $fingerprintData, ?string $department = null): array
    {
        $options = [];
        if ($department) {
            $options['department'] = $department;
        }

        $result = $this->fingerprintService->identify($fingerprintData, $options);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'biometric_identify_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'message' => 'Biometria processada com sucesso.', 'data' => $result];
    }

    public function listTemplates(object $currentEmployee, ?int $employeeId): array
    {
        $targetEmployeeId = $employeeId ?: (int) $currentEmployee->id;
        if ($targetEmployeeId !== (int) $currentEmployee->id && !$this->canManageOtherEmployeesBiometrics($currentEmployee)) {
            return ['success' => false, 'status' => 403, 'code' => 'forbidden', 'message' => 'Você só pode visualizar seus próprios templates'];
        }

        $templates = $this->biometricModel
            ->where('employee_id', $targetEmployeeId)
            ->where('biometric_type', 'fingerprint')
            ->where('is_active', true)
            ->findAll();

        $data = [];
        foreach ($templates as $template) {
            $data[] = [
                'id' => $template->id,
                'finger_position' => $template->finger_position,
                'quality_score' => $template->quality_score,
                'capture_method' => $template->capture_method,
                'capture_device' => $template->capture_device,
                'enrolled_at' => $template->enrolled_at,
                'last_used_at' => $template->last_used_at,
                'usage_count' => $template->usage_count,
            ];
        }

        return ['success' => true, 'data' => ['templates' => $data, 'count' => count($data)]];
    }

    public function deleteTemplate(object $currentEmployee, int $templateId): array
    {
        $template = $this->biometricModel->find($templateId);
        if (!$template) {
            return ['success' => false, 'status' => 404, 'code' => 'template_not_found', 'message' => 'Template não encontrado'];
        }

        if ((int) $template->employee_id !== (int) $currentEmployee->id && !$this->canManageOtherEmployeesBiometrics($currentEmployee)) {
            return ['success' => false, 'status' => 403, 'code' => 'forbidden', 'message' => 'Você só pode excluir seus próprios templates'];
        }

        $result = $this->fingerprintService->removeTemplate($templateId, (int) $template->employee_id);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'template_delete_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'message' => $result['message'] ?? 'Template excluído com sucesso'];
    }

    public function grantConsent(int $employeeId): array
    {
        if ($this->consentModel->hasConsent($employeeId, 'biometric_fingerprint')) {
            return ['success' => false, 'status' => 400, 'code' => 'consent_exists', 'message' => 'Você já concedeu consentimento para uso de biometria digital'];
        }

        $granted = $this->consentModel->grant(
            $employeeId,
            'biometric_fingerprint',
            'Registro de ponto eletrônico através de reconhecimento de impressão digital',
            'Autorizo o tratamento de meus dados biométricos (impressão digital) para fins de registro de ponto eletrônico, conforme a Lei Geral de Proteção de Dados (LGPD).',
            'Consentimento (Art. 7º, I da LGPD)',
            '1.0'
        );

        if (!$granted) {
            return ['success' => false, 'status' => 500, 'code' => 'consent_persist_failed', 'message' => 'Erro ao registrar consentimento'];
        }

        return ['success' => true, 'status' => 201, 'message' => 'Consentimento registrado com sucesso'];
    }

    public function revokeConsent(int $employeeId): array
    {
        $revoked = $this->consentModel->revoke($employeeId, 'biometric_fingerprint');
        if (!$revoked) {
            return ['success' => false, 'status' => 400, 'code' => 'consent_revoke_failed', 'message' => 'Erro ao revogar consentimento ou consentimento não encontrado'];
        }

        $purge = $this->biometricDataPurgeService->purgeEmployee($employeeId, ['fingerprint'], 'fingerprint_consent_revoked');

        return [
            'success' => true,
            'message' => 'Consentimento revogado. Seus dados biométricos foram removidos.',
            'data' => [
                'deleted_records' => $purge['deleted_records'] ?? 0,
                'deleted_files' => $purge['deleted_files'] ?? 0,
            ],
        ];
    }



    private function canManageOtherEmployeesBiometrics(object $currentEmployee): bool
    {
        try {
            $role = Role::normalize((string) ($currentEmployee->role ?? Role::Funcionario->value));
        } catch (\ValueError) {
            $role = Role::Funcionario;
        }

        return in_array($role, [Role::Admin, Role::Gestor, Role::RH], true);
    }

    public function consentStatus(int $employeeId): array
    {
        $hasConsent = $this->consentModel->hasConsent($employeeId, 'biometric_fingerprint')
            || $this->consentModel->hasConsent($employeeId, 'biometric_data');

        return ['success' => true, 'data' => ['has_consent' => $hasConsent]];
    }
}
