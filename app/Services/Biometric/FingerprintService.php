<?php

namespace App\Services\Biometric;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;
use App\Services\Biometric\Fingerprint\FingerprintEnrollmentService;
use App\Services\Biometric\Fingerprint\FingerprintIdentificationService;
use App\Services\Biometric\Fingerprint\FingerprintTemplateManagementService;
use App\Services\Biometric\Fingerprint\FingerprintTemplateStateService;
use App\Services\Biometric\Fingerprint\FingerprintVerificationService;
use Exception;

class FingerprintService
{
    protected FingerprintSettingsService $settingsService;
    protected FingerprintTemplateExtractionService $extractionService;
    protected FingerprintTemplateCryptoService $cryptoService;
    protected FingerprintMatchingService $matchingService;
    protected FingerprintAuditService $fingerprintAuditService;

    protected FingerprintTemplateStateService $templateStateService;
    protected FingerprintEnrollmentService $fingerprintEnrollmentService;
    protected FingerprintVerificationService $fingerprintVerificationService;
    protected FingerprintIdentificationService $fingerprintIdentificationService;
    protected FingerprintTemplateManagementService $fingerprintTemplateManagementService;

    protected int $threshold;

    public function __construct()
    {
        $biometricModel = new BiometricTemplateModel();
        $employeeModel = new EmployeeModel();
        $consentModel = new UserConsentModel();

        $this->settingsService = new FingerprintSettingsService();
        $this->extractionService = new FingerprintTemplateExtractionService();
        $this->cryptoService = new FingerprintTemplateCryptoService();
        $this->matchingService = new FingerprintMatchingService();
        $this->fingerprintAuditService = new FingerprintAuditService();

        $this->templateStateService = new FingerprintTemplateStateService($biometricModel, $employeeModel, $consentModel);
        $this->fingerprintEnrollmentService = new FingerprintEnrollmentService(
            $this->templateStateService,
            $this->settingsService,
            $this->extractionService,
            $this->cryptoService,
            $this->matchingService
        );
        $this->fingerprintVerificationService = new FingerprintVerificationService(
            $this->templateStateService,
            $this->settingsService,
            $this->extractionService,
            $this->matchingService
        );
        $this->fingerprintIdentificationService = new FingerprintIdentificationService(
            $this->templateStateService,
            $this->settingsService,
            $this->extractionService,
            $this->matchingService
        );
        $this->fingerprintTemplateManagementService = new FingerprintTemplateManagementService($this->templateStateService);

        $this->threshold = $this->settingsService->threshold();
    }

    public function enroll(int $employeeId, string $fingerprintData, string $fingerPosition, string $captureMethod, array $options = []): array
    {
        try {
            $result = $this->fingerprintEnrollmentService->enroll($employeeId, $fingerprintData, $fingerPosition, $captureMethod, $options);

            if (($result['duplicate'] ?? false) === true) {
                $existingEmployeeId = $result['existing_employee_id'] ?? null;
                $this->logAudit(
                    $employeeId,
                    'BIOMETRIC_DUPLICATE_ATTEMPT',
                    'biometric_templates',
                    null,
                    "Tentativa de cadastro duplicado detectada (similar a colaborador {$existingEmployeeId})"
                );
            }

            if (($result['success'] ?? false) === true) {
                $this->logAudit(
                    $employeeId,
                    'BIOMETRIC_ENROLLED',
                    'biometric_templates',
                    (int) $result['template_id'],
                    "Impressão digital cadastrada com sucesso (dedo: {$fingerPosition}, qualidade: {$result['quality']})"
                );
            }

            return $result;
        } catch (Exception $e) {
            log_message('error', 'FingerprintService::enroll error: ' . $e->getMessage());
            $this->logAudit($employeeId, 'BIOMETRIC_ENROLL_ERROR', 'biometric_templates', null, 'Erro ao cadastrar impressão digital: ' . $e->getMessage(), 'error');
            return ['success' => false, 'message' => 'Erro interno ao processar cadastro'];
        }
    }

    public function verify(int $employeeId, string $fingerprintData): array
    {
        try {
            $result = $this->fingerprintVerificationService->verify($employeeId, $fingerprintData);
            if (($result['success'] ?? false) !== true) {
                return $result;
            }

            $verified = (bool) ($result['verified'] ?? false);
            $this->logAudit(
                $employeeId,
                $verified ? 'BIOMETRIC_AUTH_SUCCESS' : 'BIOMETRIC_AUTH_FAILED',
                'biometric_templates',
                $result['matched_template_id'] ?? null,
                sprintf(
                    'Verificação 1:1 %s (similaridade: %.2f%%, threshold: %d%%)',
                    $verified ? 'bem-sucedida' : 'falhou',
                    ((float) ($result['best_match'] ?? 0)) * 100,
                    $this->threshold
                ),
                $verified ? 'info' : 'warning'
            );

            unset($result['matched_template_id'], $result['best_match']);
            return $result;
        } catch (Exception $e) {
            log_message('error', 'FingerprintService::verify error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno ao verificar impressão digital'];
        }
    }

    public function identify(string $fingerprintData, array $options = []): array
    {
        try {
            $result = $this->fingerprintIdentificationService->identify($fingerprintData, $options);
            if (($result['success'] ?? false) !== true) {
                return $result;
            }

            $identified = (bool) ($result['identified'] ?? false);
            $this->logAudit(
                $result['employee_id'] ?? null,
                $identified ? 'BIOMETRIC_IDENTIFY_SUCCESS' : 'BIOMETRIC_IDENTIFY_FAILED',
                'biometric_templates',
                $result['matched_template_id'] ?? null,
                sprintf(
                    'Identificação 1:N %s (similaridade: %.2f%%, tempo: %.3fs, templates: %d)',
                    $identified ? 'bem-sucedida' : 'falhou',
                    ((float) ($result['best_match'] ?? 0)) * 100,
                    (float) ($result['processing_time'] ?? 0),
                    (int) ($result['templates_checked'] ?? 0)
                ),
                $identified ? 'info' : 'warning'
            );

            unset($result['matched_template_id'], $result['best_match']);
            return $result;
        } catch (Exception $e) {
            log_message('error', 'FingerprintService::identify error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno ao identificar impressão digital'];
        }
    }

    public function removeTemplate(int $templateId, int $employeeId): array
    {
        try {
            $result = $this->fingerprintTemplateManagementService->removeTemplate($templateId, $employeeId);
            if (($result['success'] ?? false) === true) {
                $this->logAudit($employeeId, 'BIOMETRIC_TEMPLATE_REMOVED', 'biometric_templates', $templateId, "Template de impressão digital removido (ID: {$templateId})");
            }

            return $result;
        } catch (Exception $e) {
            log_message('error', 'FingerprintService::removeTemplate error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno ao excluir template'];
        }
    }

    private function logAudit(?int $userId, string $action, ?string $tableName = null, ?int $recordId = null, ?string $description = null, string $level = 'info'): void
    {
        $this->fingerprintAuditService->log($userId, $action, $tableName, $recordId, $description, $level);
    }
}
