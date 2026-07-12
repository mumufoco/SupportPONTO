<?php

namespace App\Services\Employees;

use App\Models\AuditModel;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;
use App\Services\Auth\PasswordLifecycleService;
use Config\Services;

class EmployeeAccountService
{
    protected EmployeeModel $employeeModel;
    protected BiometricTemplateModel $biometricModel;
    protected UserConsentModel $consentModel;
    protected AuditModel $auditModel;
    protected PasswordLifecycleService $passwordLifecycleService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?BiometricTemplateModel $biometricModel = null,
        ?UserConsentModel $consentModel = null,
        ?AuditModel $auditModel = null,
        ?PasswordLifecycleService $passwordLifecycleService = null
    ) {
        $this->employeeModel = $employeeModel ?? Services::employeeModel();
        $this->biometricModel = $biometricModel ?? new BiometricTemplateModel();
        $this->consentModel = $consentModel ?? Services::userConsentModel();
        $this->auditModel = $auditModel ?? Services::auditModel();
        $this->passwordLifecycleService = $passwordLifecycleService ?? new PasswordLifecycleService();
    }

    public function updateProfile(int $employeeId, array $payload): array
    {
        $data = ['phone' => $payload['phone'] ?? null];

        $password = $payload['password'] ?? null;
        if (is_string($password) && strlen($password) >= 8) {
            $data['password'] = password_hash($password, PASSWORD_ARGON2ID);
        }

        $updated = $this->employeeModel->update($employeeId, $data);
        if (! $updated) {
            return ['success' => false, 'message' => 'Erro ao atualizar perfil.'];
        }

        $this->auditModel->log(
            $employeeId,
            'PROFILE_UPDATED',
            'employees',
            $employeeId,
            null,
            $data,
            'Perfil atualizado'
        );

        return ['success' => true, 'message' => 'Perfil atualizado com sucesso!'];
    }

    public function grantBiometricConsent(int $employeeId, string $ipAddress): array
    {
        if ($this->consentModel->hasConsent($employeeId, 'biometric_data')) {
            return ['success' => false, 'message' => 'Você já possui consentimento.', 'type' => 'info'];
        }

        $consentId = $this->consentModel->insert([
            'employee_id' => $employeeId,
            'consent_type' => 'biometric_data',
            'purpose' => 'Registro de ponto eletrônico através de reconhecimento facial e biometria digital',
            'legal_basis' => 'Consentimento (Art. 7º, I da LGPD)',
            'granted' => true,
            'granted_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'consent_text' => 'Autorizo o tratamento de meus dados biométricos (facial e digital) para fins de registro de ponto eletrônico conforme a LGPD.',
            'version' => '1.0',
        ]);

        if (! $consentId) {
            return ['success' => false, 'message' => 'Erro ao registrar consentimento.'];
        }

        $this->auditModel->log(
            $employeeId,
            'CONSENT_GRANTED',
            'user_consents',
            $consentId,
            null,
            ['consent_type' => 'biometric_data'],
            'Consentimento para dados biométricos concedido'
        );

        return ['success' => true, 'message' => 'Consentimento registrado com sucesso!'];
    }

    public function revokeBiometricConsent(int $employeeId): array
    {
        if (! $this->consentModel->revoke($employeeId, 'biometric_data')) {
            return ['success' => false, 'message' => 'Erro ao revogar consentimento.'];
        }

        $this->biometricModel
            ->where('employee_id', $employeeId)
            ->set(['active' => false])
            ->update();

        $this->employeeModel->update($employeeId, [
            'has_face_biometric' => false,
            'has_fingerprint_biometric' => false,
        ]);

        $this->auditModel->log(
            $employeeId,
            'CONSENT_REVOKED',
            'user_consents',
            null,
            null,
            ['consent_type' => 'biometric_data'],
            'Consentimento para dados biométricos revogado - biometrias desativadas'
        );

        return ['success' => true, 'message' => 'Consentimento revogado. Suas biometrias foram desativadas.'];
    }

    public function changePassword(object $currentUser, string $currentPassword, string $newPassword): array
    {
        if (! $this->employeeModel->verifyPassword($currentPassword, $currentUser->password)) {
            return ['success' => false, 'message' => 'Senha atual incorreta.'];
        }

        $this->passwordLifecycleService->updatePassword((int) $currentUser->id, $newPassword, [
            'clear_reset_tokens' => true,
            'audit_action' => 'PASSWORD_CHANGED',
            'audit_new_values' => ['changed_by' => $currentUser->id],
            'audit_description' => 'Senha alterada pelo usuário',
        ]);

        return ['success' => true, 'message' => 'Senha alterada com sucesso!'];
    }
}
