<?php

namespace App\Services\Biometric;

use Exception;

class FingerprintTemplateExtractionService
{
    public function __construct(private readonly SourceAFISService $sourceAFISService = new SourceAFISService())
    {
    }

    public function extractFromInput(string $fingerprintData): array
    {
        $tempFile = $this->saveTempFile($fingerprintData);
        if ($tempFile === false) {
            return ['success' => false, 'message' => 'Erro ao processar dados da impressão digital'];
        }

        $extractResult = $this->sourceAFISService->extractTemplate($tempFile);
        @unlink($tempFile);

        if (($extractResult['success'] ?? false) !== true) {
            return [
                'success' => false,
                'message' => 'Erro ao extrair template: ' . ($extractResult['error'] ?? 'Desconhecido'),
            ];
        }

        return [
            'success' => true,
            'template' => $extractResult['template'],
            'minutiae_count' => $extractResult['minutiae_count'] ?? 0,
        ];
    }

    private function saveTempFile(string $data): string|false
    {
        try {
            if (strpos($data, 'data:image/') === 0) {
                $parts = explode(',', $data, 2);
                $data = $parts[1] ?? '';
            }

            if (file_exists($data)) {
                return $data;
            }

            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                return false;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'fingerprint_');
            if ($tempFile === false || file_put_contents($tempFile, $decoded) === false) {
                return false;
            }

            return $tempFile;
        } catch (Exception $e) {
            log_message('error', 'Error saving temp file: ' . $e->getMessage());
            return false;
        }
    }
}
