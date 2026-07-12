<?php

namespace App\Services\Warning\Pdf;

use App\Services\Warning\WarningPdfStorageService;
use TCPDF;

class WarningPdfFileService
{
    public function __construct(private readonly WarningPdfStorageService $storageService)
    {
        $this->storageService->ensureDirectory();
    }

    public function save(TCPDF $pdf, int $warningId, bool $final): array
    {
        $filename = ($final ? 'advertencia_final_' : 'advertencia_') . $warningId . '_' . time() . '.pdf';
        $filepath = $this->storageService->outputDirectory() . $filename;
        $pdf->Output($filepath, 'F');

        return [
            'success' => true,
            'filepath' => $this->storageService->buildStoredPath($filename),
            'filename' => $filename,
        ];
    }
}
