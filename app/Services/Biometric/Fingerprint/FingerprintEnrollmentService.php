<?php

namespace App\Services\Biometric\Fingerprint;

use App\Services\Biometric\FingerprintMatchingService;
use App\Services\Biometric\FingerprintSettingsService;
use App\Services\Biometric\FingerprintTemplateCryptoService;
use App\Services\Biometric\FingerprintTemplateExtractionService;

class FingerprintEnrollmentService
{
    private FingerprintTemplateStateService $stateService;
    private FingerprintSettingsService $settingsService;
    private FingerprintTemplateExtractionService $extractionService;
    private FingerprintTemplateCryptoService $cryptoService;
    private FingerprintMatchingService $matchingService;

    public function __construct(
        FingerprintTemplateStateService $stateService,
        FingerprintSettingsService $settingsService,
        FingerprintTemplateExtractionService $extractionService,
        FingerprintTemplateCryptoService $cryptoService,
        FingerprintMatchingService $matchingService
    ) {
        $this->stateService = $stateService;
        $this->settingsService = $settingsService;
        $this->extractionService = $extractionService;
        $this->cryptoService = $cryptoService;
        $this->matchingService = $matchingService;
    }

    public function enroll(int $employeeId, string $fingerprintData, string $fingerPosition, string $captureMethod, array $options = []): array
    {
        if (!$this->stateService->employeeExists($employeeId)) {
            return ['success' => false, 'message' => 'Colaborador não encontrado'];
        }

        if ($this->settingsService->requireConsent() && !$this->stateService->hasValidConsent($employeeId)) {
            return ['success' => false, 'message' => 'Consentimento LGPD necessário antes de cadastrar biometria', 'consent_required' => true];
        }

        if ($this->stateService->activeTemplatesCount($employeeId) >= $this->settingsService->maxTemplatesPerUser()) {
            return ['success' => false, 'message' => 'Limite máximo de ' . $this->settingsService->maxTemplatesPerUser() . ' digitais por colaborador atingido'];
        }

        if ($this->stateService->hasFingerPositionTemplate($employeeId, $fingerPosition)) {
            return ['success' => false, 'message' => 'Já existe um template cadastrado para este dedo'];
        }

        $extractResult = $this->extractionService->extractFromInput($fingerprintData);
        if (($extractResult['success'] ?? false) !== true) {
            return ['success' => false, 'message' => $extractResult['message'] ?? 'Erro ao extrair template'];
        }

        $template = (string) $extractResult['template'];
        $quality = (int) ($extractResult['minutiae_count'] ?? 0);
        if ($quality < $this->settingsService->minQuality()) {
            return ['success' => false, 'message' => 'Qualidade da impressão digital muito baixa (min: ' . $this->settingsService->minQuality() . ')', 'quality' => $quality];
        }

        if ($this->settingsService->allowDuplicateCheck()) {
            $duplicate = $this->matchingService->findDuplicate(
                $template,
                $this->templatesForDuplicateCheck($employeeId),
                $this->settingsService->threshold() / 100
            );

            if (($duplicate['is_duplicate'] ?? false) === true) {
                return [
                    'success' => false,
                    'message' => 'Esta impressão digital já está cadastrada para outro colaborador',
                    'duplicate' => true,
                    'similarity' => $duplicate['similarity'] ?? 0,
                    'existing_employee_id' => $duplicate['existing_employee_id'] ?? null,
                ];
            }
        }

        $encryptedTemplate = $this->cryptoService->encrypt($template);
        if ($encryptedTemplate === false) {
            return ['success' => false, 'message' => 'Erro ao criptografar template'];
        }

        $templateId = $this->stateService->insertTemplate([
            'employee_id' => $employeeId,
            'biometric_type' => 'fingerprint',
            'finger_position' => $fingerPosition,
            'template_data' => $encryptedTemplate,
            'template_hash' => $this->cryptoService->hash($template),
            'template_format' => 'sourceafis_proprietary',
            'quality_score' => $quality,
            'capture_method' => $captureMethod,
            'capture_device' => $options['device'] ?? null,
            'algorithm_version' => $this->settingsService->algorithmVersion(),
            'model_used' => 'SourceAFIS',
            'is_active' => true,
            'enrolled_at' => date('Y-m-d H:i:s'),
            'enrolled_by' => $options['enrolled_by'] ?? null,
            'usage_count' => 0,
            'metadata' => json_encode([
                'enrolled_by_name' => $options['enrolled_by_name'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]),
        ]);

        if (!$templateId) {
            return ['success' => false, 'message' => 'Erro ao salvar template no banco de dados'];
        }

        $this->stateService->setEmployeeFingerprintFlag($employeeId, true);

        return [
            'success' => true,
            'message' => 'Impressão digital cadastrada com sucesso',
            'template_id' => $templateId,
            'quality' => $quality,
            'finger_position' => $fingerPosition,
        ];
    }

    private function templatesForDuplicateCheck(int $excludeEmployeeId): array
    {
        $model = new \App\Models\BiometricTemplateModel();

        return $model->where('biometric_type', 'fingerprint')
            ->where('employee_id !=', $excludeEmployeeId)
            ->where('is_active', true)
            ->findAll();
    }
}
