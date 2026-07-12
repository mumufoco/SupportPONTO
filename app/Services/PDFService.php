<?php

namespace App\Services;

use App\Services\Pdf\PdfAdministrativeReportContentBuilder;
use App\Services\Pdf\PdfDocumentFactory;
use App\Services\Pdf\PdfFileExporter;
use App\Services\Pdf\PdfFilterHtmlBuilder;
use App\Services\Pdf\PdfOperationalReportContentBuilder;
use App\Services\Pdf\PdfReportContentComposer;
use App\Services\Reports\ReportFilterRenderer;

class PDFService
{
    protected string $companyName;
    protected ?string $companyLogo;
    protected ReportFilterRenderer $filterRenderer;

    protected PdfReportContentComposer $composer;
    protected PdfDocumentFactory $documentFactory;
    protected PdfFileExporter $fileExporter;

    public function __construct()
    {
        $this->filterRenderer = new ReportFilterRenderer();

        $companyCnpj = '';
        $logoPath    = null;

        try {
            $settingModel      = new \App\Models\SettingModel();
            $this->companyName = $settingModel->get('company_name', 'Sistema de Ponto Eletrônico');
            $this->companyLogo = $settingModel->get('company_logo', null);
            $companyCnpj       = (string) ($settingModel->get('company_cnpj', '') ?? '');

            // Resolve logo filesystem path from setting URL
            if ($this->companyLogo) {
                $candidateUrl  = ltrim((string) $this->companyLogo, '/');
                $candidatePath = FCPATH . $candidateUrl;
                if (file_exists($candidatePath)) {
                    $logoPath = $candidatePath;
                } else {
                    // Try stripping base_url prefix
                    $stripped = ltrim(str_replace(rtrim(base_url(), '/'), '', (string) $this->companyLogo), '/');
                    $alt      = FCPATH . $stripped;
                    if (file_exists($alt)) {
                        $logoPath = $alt;
                    }
                }
            }
            // Fallback: cached company logo written by receipt service
            if (!$logoPath && file_exists(WRITEPATH . 'uploads/company_logo.png')) {
                $logoPath = WRITEPATH . 'uploads/company_logo.png';
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Could not load settings in PDFService: ' . $e->getMessage());
            $this->companyName = 'Sistema de Ponto Eletrônico';
            $this->companyLogo = null;
        }

        $this->documentFactory = new PdfDocumentFactory($this->companyName, $companyCnpj, $logoPath);
        $filterBuilder         = new PdfFilterHtmlBuilder($this->filterRenderer);
        $operational           = new PdfOperationalReportContentBuilder($filterBuilder, $this->documentFactory);
        $administrative        = new PdfAdministrativeReportContentBuilder($filterBuilder, $this->documentFactory);

        $this->composer     = new PdfReportContentComposer($operational, $administrative);
        $this->fileExporter = new PdfFileExporter();
    }

    public function generateReport(string $type, array $data, array $filters = []): array
    {
        try {
            $payload = $this->composer->compose($type, $data, $filters);
            if (($payload['success'] ?? false) !== true) {
                return $payload;
            }

            $pdf = $this->documentFactory->create($payload['title']);
            $pdf->writeHTML($payload['html'], true, false, true, false, '');

            return $this->fileExporter->export($pdf, $payload['filename']);
        } catch (\Throwable $e) {
            log_message('error', 'PDF generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao gerar PDF',
                'details' => $e->getMessage(),
            ];
        }
    }

    public function signPDF(string $filepath): bool
    {
        $certPath = env('ICP_CERTIFICATE_PATH');
        $keyPath = env('ICP_KEY_PATH');
        $password = env('ICP_KEY_PASSWORD');

        if (!$certPath || !$keyPath || !file_exists($certPath) || !file_exists($keyPath)) {
            log_message('warning', 'ICP certificate not configured for PDF signing');
            return false;
        }

        try {
            $cert = file_get_contents($certPath);
            $key = file_get_contents($keyPath);
            $signedFile = $filepath . '.signed';

            $result = openssl_pkcs7_sign(
                $filepath,
                $signedFile,
                $cert,
                [$key, $password],
                [],
                PKCS7_BINARY | PKCS7_DETACHED
            );

            if ($result) {
                rename($signedFile, $filepath);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            log_message('error', 'PDF signing error: ' . $e->getMessage());
            return false;
        }
    }
}
