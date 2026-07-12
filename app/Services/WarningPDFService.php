<?php

namespace App\Services;

use App\Models\WarningModel;
use App\Services\Warning\Pdf\WarningPdfDocumentFactory;
use App\Services\Warning\Pdf\WarningPdfFileService;
use App\Services\Warning\WarningPdfContentService;
use App\Services\Warning\WarningPdfSignatureService;
use App\Services\Warning\WarningPdfStorageService;

class WarningPDFService
{
    protected string $companyName;
    protected string $companyCNPJ;
    protected string $companyLogo;
    protected string $pdfOutputPath;

    protected WarningPdfContentService $contentService;
    protected WarningPdfSignatureService $signatureService;
    protected WarningPdfDocumentFactory $documentFactory;
    protected WarningPdfFileService $fileService;

    public function __construct(private readonly ?WarningPdfStorageService $storageService = null)
    {
        $this->companyName = env('COMPANY_NAME', 'Empresa LTDA');
        $this->companyCNPJ = env('COMPANY_CNPJ', '00.000.000/0000-00');
        $this->companyLogo = FCPATH . 'assets/images/logo.png';
        $storage = $this->storageService ?? new WarningPdfStorageService();
        $this->pdfOutputPath = $storage->outputDirectory();

        $this->contentService = new WarningPdfContentService();
        $this->signatureService = new WarningPdfSignatureService();
        $this->documentFactory = new WarningPdfDocumentFactory();
        $this->fileService = new WarningPdfFileService($storage);
    }

    public function generateWarningPDF(int $warningId, array $data): array
    {
        return $this->generatePdf($warningId, $data, false);
    }

    public function generateFinalPDF(int $warningId, array $data): array
    {
        $result = $this->generatePdf($warningId, $data, true);

        if (($result['success'] ?? false) === true) {
            (new WarningModel())->update($warningId, ['pdf_path' => $result['filepath']]);
        }

        return $result;
    }

    public function signPDFWithICP(string $pdfPath, int $employeeId): array
    {
        $resolvedPath = $this->resolvePdfPath($pdfPath);
        if ($resolvedPath === null) {
            return ['success' => false, 'error' => 'PDF da advertência não encontrado ou inválido.'];
        }

        $result = $this->signatureService->signPdfWithIcp($resolvedPath);

        return $this->normalizeSignedResult($result);
    }

    public function signPDFWithICPUpload(string $pdfPath, $certificateFile, string $password, int $employeeId): array
    {
        $resolvedPath = $this->resolvePdfPath($pdfPath);
        if ($resolvedPath === null) {
            return ['success' => false, 'error' => 'PDF da advertência não encontrado ou inválido.'];
        }

        $result = $this->signatureService->signPdfWithUploadedCert($resolvedPath, $certificateFile, $password);

        return $this->normalizeSignedResult($result);
    }

    private function generatePdf(int $warningId, array $data, bool $final): array
    {
        try {
            $warning = $data['warning'];
            $employee = $data['employee'];
            $issuer = $data['issuer'];

            $pdf = $this->documentFactory->create(
                $this->companyName,
                $issuer->name,
                'Advertência - ' . $employee->name,
                'Advertência ' . strtoupper($warning->warning_type)
            );

            $html = $final
                ? $this->contentService->generateFinalHtml($warning, $employee, $issuer, $this->companyLogo, $this->companyName, $this->companyCNPJ)
                : $this->contentService->generateWarningHtml($warning, $employee, $issuer, $this->companyLogo, $this->companyName, $this->companyCNPJ);

            $pdf->writeHTML($html, true, false, true, false, '');

            return $this->fileService->save($pdf, $warningId, $final);
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function resolvePdfPath(string $pdfPath): ?string
    {
        if ($this->storageService instanceof WarningPdfStorageService) {
            return $this->storageService->resolveAbsolutePath($pdfPath) ?? supportponto_safe_download_path($pdfPath, $this->storageService->allowedRoots());
        }

        $storage = new WarningPdfStorageService();

        return $storage->resolveAbsolutePath($pdfPath) ?? supportponto_safe_download_path($pdfPath, $storage->allowedRoots());
    }

    private function normalizeSignedResult(array $result): array
    {
        if (($result['success'] ?? false) !== true) {
            return $result;
        }

        $storage = $this->storageService ?? new WarningPdfStorageService();
        $storedPath = $storage->toStoredPath((string) ($result['filepath'] ?? ''));
        if ($storedPath === null) {
            return ['success' => false, 'error' => 'PDF assinado gerado fora do diretório permitido.'];
        }

        $result['filepath'] = $storedPath;

        return $result;
    }
}
