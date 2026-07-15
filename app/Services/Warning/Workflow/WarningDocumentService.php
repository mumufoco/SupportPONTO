<?php

namespace App\Services\Warning\Workflow;

use App\Models\WarningModel;
use App\Services\WarningPDFService;

class WarningDocumentService
{
    public function __construct(
        private readonly WarningModel $warningModel,
        private readonly WarningPDFService $pdfService
    ) {
    }

    public function generateInitialPdf(int $warningId, int $issuerId, ?object $targetEmployee, ?object $issuer): void
    {
        if (!$targetEmployee || !$issuer) {
            return;
        }

        $pdfResult = $this->pdfService->generateWarningPDF($warningId, [
            'warning' => $this->warningModel->find($warningId),
            'employee' => $targetEmployee,
            'issuer' => $issuer,
        ]);

        if (!($pdfResult['success'] ?? false)) {
            return;
        }
    }

    public function regenerateFinalPdf(int $warningId, ?object $employee, ?object $issuer): void
    {
        $this->pdfService->generateFinalPDF($warningId, [
            'warning' => $this->warningModel->find($warningId),
            'employee' => $employee,
            'issuer' => $issuer,
        ]);
    }
}
