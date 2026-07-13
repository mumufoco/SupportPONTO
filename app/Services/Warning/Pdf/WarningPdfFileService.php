<?php

namespace App\Services\Warning\Pdf;

use App\Services\Pdf\GotenbergPdfDocument;
use App\Services\Warning\WarningPdfStorageService;

class WarningPdfFileService
{
    public function __construct(private readonly WarningPdfStorageService $storageService)
    {
        $this->storageService->ensureDirectory();
    }

    public function save(GotenbergPdfDocument $pdf, int $warningId, bool $final): array
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
