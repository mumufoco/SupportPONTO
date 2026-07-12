<?php

namespace App\Services\Biometric\Fingerprint;

use App\Models\EmployeeModel;
use App\Services\Biometric\FingerprintMatchingService;
use App\Services\Biometric\FingerprintSettingsService;
use App\Services\Biometric\FingerprintTemplateExtractionService;

class FingerprintIdentificationService
{
    private EmployeeModel $employeeModel;

    public function __construct(
        private FingerprintTemplateStateService $stateService,
        private FingerprintSettingsService $settingsService,
        private FingerprintTemplateExtractionService $extractionService,
        private FingerprintMatchingService $matchingService
    ) {
        $this->employeeModel = new EmployeeModel();
    }

    public function identify(string $fingerprintData, array $options = []): array
    {
        $startTime = microtime(true);

        $extractResult = $this->extractionService->extractFromInput($fingerprintData);
        if (($extractResult['success'] ?? false) !== true) {
            return ['success' => false, 'message' => $extractResult['message'] ?? 'Erro ao extrair template'];
        }

        $templates = $this->stateService->activeTemplatesForIdentification($options['department'] ?? null);
        if (empty($templates)) {
            return ['success' => true, 'identified' => false, 'message' => 'Nenhum template disponível para identificação'];
        }

        $match = $this->matchingService->bestMatch((string) $extractResult['template'], $templates);
        $bestMatch = (float) ($match['similarity'] ?? 0);
        $matchedTemplateId = $match['template_id'] ?? null;
        $identifiedEmployeeId = $match['employee_id'] ?? null;
        $identified = $bestMatch >= ($this->settingsService->threshold() / 100);

        if ($identified && $matchedTemplateId !== null) {
            $this->stateService->touchTemplateUsage((int) $matchedTemplateId);
        }

        $result = [
            'success' => true,
            'identified' => $identified,
            'employee_id' => $identified ? $identifiedEmployeeId : null,
            'matched_template_id' => $matchedTemplateId,
            'best_match' => $bestMatch,
            'similarity_score' => round($bestMatch * 100, 2),
            'threshold' => $this->settingsService->threshold(),
            'processing_time' => round(microtime(true) - $startTime, 3),
            'templates_checked' => count($templates),
            'message' => $identified ? 'Funcionário identificado' : 'Impressão digital não reconhecida',
        ];

        if ($identified && $identifiedEmployeeId !== null) {
            $employee = $this->employeeModel->find($identifiedEmployeeId);
            if ($employee) {
                $result['employee'] = [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'cpf' => $employee->cpf,
                    'department' => $employee->department,
                    'position' => $employee->position,
                ];
            }
        }

        return $result;
    }
}
