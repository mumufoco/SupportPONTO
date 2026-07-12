<?php

namespace App\Services\Biometric;

class FingerprintMatchingService
{
    public function __construct(
        private readonly SourceAFISService $sourceAFISService = new SourceAFISService(),
        private readonly FingerprintTemplateCryptoService $cryptoService = new FingerprintTemplateCryptoService(),
    ) {
    }

    public function bestMatch(string $probeTemplate, array $templates): array
    {
        $bestMatch = 0.0;
        $matchedTemplateId = null;
        $identifiedEmployeeId = null;

        foreach ($templates as $template) {
            $storedTemplate = $this->cryptoService->decrypt((string) $template->template_data);
            if ($storedTemplate === false) {
                continue;
            }

            $compareResult = $this->sourceAFISService->compareTemplates($probeTemplate, $storedTemplate);
            if (($compareResult['success'] ?? false) !== true) {
                continue;
            }

            $similarity = (float) ($compareResult['similarity'] ?? 0);
            if ($similarity > $bestMatch) {
                $bestMatch = $similarity;
                $matchedTemplateId = $template->id;
                $identifiedEmployeeId = $template->employee_id;
            }
        }

        return [
            'similarity' => $bestMatch,
            'template_id' => $matchedTemplateId,
            'employee_id' => $identifiedEmployeeId,
        ];
    }

    public function findDuplicate(string $template, array $templates, float $thresholdRatio): array
    {
        foreach ($templates as $storedTemplate) {
            $decrypted = $this->cryptoService->decrypt((string) $storedTemplate->template_data);
            if ($decrypted === false) {
                continue;
            }

            $compareResult = $this->sourceAFISService->compareTemplates($template, $decrypted);
            if (($compareResult['success'] ?? false) !== true) {
                continue;
            }

            $similarity = (float) ($compareResult['similarity'] ?? 0);
            if ($similarity >= $thresholdRatio) {
                return [
                    'is_duplicate' => true,
                    'similarity' => $similarity,
                    'existing_employee_id' => $storedTemplate->employee_id,
                ];
            }
        }

        return ['is_duplicate' => false];
    }
}
