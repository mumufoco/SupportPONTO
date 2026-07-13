<?php

namespace App\Services\Biometric;

use App\Services\Audit\CanonicalAuditLogger;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;

class FingerprintWorkflowService
{
    private EmployeeModel $employeeModel;
    private BiometricTemplateModel $biometricModel;
    private UserConsentModel $consentModel;
    private CanonicalAuditLogger $auditLogger;
    private SourceAFISService $sourceAFISService;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->biometricModel = new BiometricTemplateModel();
        $this->consentModel = new UserConsentModel();
        $this->auditLogger = new CanonicalAuditLogger();
        $this->sourceAFISService = new SourceAFISService();
    }

    public function findEmployee(int $employeeId): mixed
    {
        return $this->employeeModel->find($employeeId);
    }

    public function enrollmentPageData(array $actor, int $employeeId): array
    {
        return [
            'employee' => $actor,
            'targetEmployee' => $this->employeeModel->find($employeeId),
            'hasConsent' => $this->hasConsent($employeeId),
            'existingTemplates' => $this->biometricModel
                ->where('employee_id', $employeeId)
                ->where('biometric_type', 'fingerprint')
                ->where('active', true)
                ->orderBy('created_at', 'DESC')
                ->findAll(),
            'title' => 'Cadastro de Impressão Digital',
        ];
    }

    public function enrollFingerprint(array $actor, int $employeeId, string $template, string $finger, float $quality, string $userAgent, string $ipAddress): array
    {
        $targetEmployee = $this->employeeModel->find($employeeId);
        if (!$targetEmployee) {
            return ['success' => false, 'status' => 404, 'message' => 'Funcionário não encontrado'];
        }

        if (!$this->hasConsent($employeeId)) {
            return ['success' => false, 'status' => 403, 'message' => 'Funcionário não concedeu consentimento para uso de dados biométricos (LGPD)'];
        }

        $existingTemplate = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'fingerprint')
            ->where('active', true)
            ->first();

        if ($existingTemplate) {
            $metadata = json_decode((string) $existingTemplate->metadata, true);
            if (isset($metadata['finger']) && $metadata['finger'] === $finger) {
                return ['success' => false, 'status' => 409, 'message' => 'Já existe um template cadastrado para este dedo. Exclua o anterior antes de cadastrar novamente.'];
            }
        }

        $encrypter = \Config\Services::encrypter();
        $encryptedTemplate = base64_encode($encrypter->encrypt($template));
        $templateHash = hash('sha256', $template);

        $db = \Config\Database::connect();
        $db->transStart();

        $templateId = $this->biometricModel->insert([
            'employee_id' => $employeeId,
            'biometric_type' => 'fingerprint',
            'template_data' => $encryptedTemplate,
            'template_hash' => $templateHash,
            'metadata' => json_encode([
                'finger' => $finger,
                'quality' => $quality,
                'enrolled_by' => $actor['id'],
                'enrolled_by_name' => $actor['name'],
                'device_info' => $userAgent,
            ]),
            'enrollment_quality' => $quality,
            'model_used' => 'SourceAFIS',
            'active' => true,
        ]);

        if (!$templateId) {
            $db->transRollback();
            return ['success' => false, 'status' => 500, 'message' => 'Erro ao salvar template biométrico'];
        }

        $this->employeeModel->update($employeeId, ['has_fingerprint_biometric' => true]);

        $this->auditLogger->logEntityEvent(
            (int) $actor['id'],
            'ENROLL_FINGERPRINT',
            'biometric_templates',
            (int) $templateId,
            null,
            [
                'template_id' => $templateId,
                'employee_id' => $employeeId,
                'finger' => $finger,
                'quality' => $quality,
            ],
            "Cadastro de impressão digital para funcionário {$targetEmployee->name} (dedo: {$finger})",
            'info'
        );

        $db->transComplete();
        if ($db->transStatus() === false) {
            return ['success' => false, 'status' => 500, 'message' => 'Erro ao processar cadastro'];
        }

        return [
            'success' => true,
            'status' => 201,
            'message' => 'Impressão digital cadastrada com sucesso!',
            'data' => [
                'template_id' => $templateId,
                'finger' => $finger,
                'quality' => $quality,
            ],
        ];
    }

    public function deleteFingerprint(array $actor, int $templateId, string $userAgent, string $ipAddress): array
    {
        $template = $this->biometricModel->find($templateId);
        if (!$template) {
            return ['success' => false, 'status' => 404, 'message' => 'Template não encontrado'];
        }

        if ($template->biometric_type !== 'fingerprint') {
            return ['success' => false, 'status' => 400, 'message' => 'Este template não é de impressão digital'];
        }

        $targetEmployee = $this->employeeModel->find((int) $template->employee_id);
        $metadata = json_decode((string) $template->metadata, true) ?: [];

        $db = \Config\Database::connect();
        $db->transStart();

        $deleted = $this->biometricModel->update($templateId, [
            'active' => false,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$deleted) {
            $db->transRollback();
            return ['success' => false, 'status' => 500, 'message' => 'Erro ao excluir template'];
        }

        $hasOtherTemplates = $this->biometricModel
            ->where('employee_id', $template->employee_id)
            ->where('biometric_type', 'fingerprint')
            ->where('active', true)
            ->where('id !=', $templateId)
            ->countAllResults() > 0;

        if (!$hasOtherTemplates) {
            $this->employeeModel->update((int) $template->employee_id, ['has_fingerprint_biometric' => false]);
        }

        $employeeName = $targetEmployee->name ?? ('#' . $template->employee_id);

        $this->auditLogger->logEntityEvent(
            (int) $actor['id'],
            'DELETE_FINGERPRINT',
            'biometric_templates',
            (int) $templateId,
            [
                'template_id' => $templateId,
                'employee_id' => $template->employee_id,
                'finger' => $metadata['finger'] ?? 'unknown',
            ],
            null,
            "Exclusão de impressão digital do funcionário {$employeeName} (dedo: " . ($metadata['finger'] ?? 'unknown') . ')',
            'warning'
        );

        $db->transComplete();
        if ($db->transStatus() === false) {
            return ['success' => false, 'status' => 500, 'message' => 'Erro ao processar exclusão'];
        }

        return ['success' => true, 'message' => 'Template de impressão digital excluído com sucesso'];
    }

    public function testFingerprint(array $actor, int $templateId, string $testTemplate, string $userAgent, string $ipAddress): array
    {
        $storedTemplate = $this->biometricModel->find($templateId);
        if (!$storedTemplate || $storedTemplate->biometric_type !== 'fingerprint' || !$storedTemplate->active) {
            return ['success' => false, 'status' => 404, 'message' => 'Template não encontrado ou inativo'];
        }

        $encrypter = \Config\Services::encrypter();
        $decryptedTemplate = $encrypter->decrypt(base64_decode((string) $storedTemplate->template_data));

        $similarity = $this->compareFingerprints((string) $decryptedTemplate, $testTemplate);
        $threshold = 0.50;
        $matched = $similarity >= $threshold;

        $this->auditLogger->logEntityEvent(
            (int) $actor['id'],
            'TEST_FINGERPRINT',
            'biometric_templates',
            (int) $templateId,
            null,
            [
                'template_id' => $templateId,
                'similarity' => $similarity,
                'matched' => $matched,
            ],
            'Teste de reconhecimento de impressão digital (similaridade: ' . round($similarity * 100, 2) . '%)',
            $matched ? 'info' : 'warning'
        );

        return [
            'success' => true,
            'matched' => $matched,
            'similarity' => round($similarity * 100, 2),
            'threshold' => round($threshold * 100, 2),
            'message' => $matched
                ? 'Impressão digital reconhecida com sucesso!'
                : 'Impressão digital não reconhecida. Similaridade abaixo do threshold.',
        ];
    }

    private function hasConsent(int $employeeId): bool
    {
        // Aceita o consentimento específico de digital OU o termo genérico
        // "biometric_data" (gravado pelo autocadastro em /minha-biometria, que
        // cobre facial e digital) — mesmo fallback já usado em
        // FaceRecognitionWorkflowService e ApiFingerprintBiometricService.
        // Sem isso, um colaborador que já aceitou o termo genérico e usa
        // facial normalmente era barrado aqui com "consentimento não
        // concedido" ao tentar cadastrar a digital.
        return $this->consentModel
                ->where('employee_id', $employeeId)
                ->where('consent_type', 'biometric_fingerprint')
                ->where('granted', true)
                ->where('revoked_at', null)
                ->first() !== null
            || $this->consentModel
                ->where('employee_id', $employeeId)
                ->where('consent_type', 'biometric_data')
                ->where('granted', true)
                ->where('revoked_at', null)
                ->first() !== null;
    }

    private function compareFingerprints(string $template1, string $template2): float
    {
        $result = $this->sourceAFISService->compareTemplates($template1, $template2);
        if (!$result['success']) {
            log_message('error', 'SourceAFIS comparison failed: ' . ($result['error'] ?? 'Unknown error'));
            return 0.0;
        }

        return (float) ($result['similarity'] ?? 0.0);
    }
}
