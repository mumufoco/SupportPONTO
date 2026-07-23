<?php

namespace App\Services\Biometric\Fingerprint;

use App\Services\Biometric\FingerprintMatchingService;
use App\Services\Biometric\FingerprintSettingsService;
use App\Services\Biometric\FingerprintTemplateExtractionService;

class FingerprintVerificationService
{
    public function __construct(
        private FingerprintTemplateStateService $stateService,
        private FingerprintSettingsService $settingsService,
        private FingerprintTemplateExtractionService $extractionService,
        private FingerprintMatchingService $matchingService
    ) {
    }

    public function verify(int $employeeId, string $fingerprintData): array
    {
        $templates = $this->stateService->activeTemplatesForEmployee($employeeId);
        if (empty($templates)) {
            return ['success' => true, 'verified' => false, 'message' => 'Colaborador não possui impressões digitais cadastradas'];
        }

        $extractResult = $this->extractionService->extractFromInput($fingerprintData);
        if (($extractResult['success'] ?? false) !== true) {
            return ['success' => false, 'message' => $extractResult['message'] ?? 'Erro ao extrair template'];
        }

        $match = $this->matchingService->bestMatch((string) $extractResult['template'], $templates);
        $bestMatch = (float) ($match['similarity'] ?? 0);
        $matchedTemplateId = isset($match['template_id']) ? (int) $match['template_id'] : null;
        $verified = $bestMatch >= ($this->settingsService->threshold() / 100);

        if ($verified && $matchedTemplateId !== null) {
            $this->stateService->touchTemplateUsage($matchedTemplateId);
        }

        return [
            'success' => true,
            'verified' => $verified,
            'matched_template_id' => $matchedTemplateId,
            'best_match' => $bestMatch,
            'similarity_score' => round($bestMatch * 100, 2),
            'threshold' => $this->settingsService->threshold(),
            'message' => $verified ? 'Verificação bem-sucedida' : 'Impressão digital não reconhecida',
        ];
    }
}
