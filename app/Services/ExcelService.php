<?php

namespace App\Services;

use App\Services\Excel\ExcelFileExporter;
use App\Services\Excel\ExcelSheetFormatter;
use App\Services\Excel\ExcelWorkbookComposer;
use App\Services\Excel\ExcelOperationalReportBuilder;
use App\Services\Excel\ExcelAdministrativeReportBuilder;
use App\Services\Reports\ReportFilterRenderer;

class ExcelService
{
    protected string $companyName;
    protected ReportFilterRenderer $filterRenderer;
    protected ExcelWorkbookComposer $composer;
    protected ExcelFileExporter $exporter;

    public function __construct()
    {
        $companyCnpj = '';
        try {
            $settingModel      = new \App\Models\SettingModel();
            $this->companyName = $settingModel->get('company_name', 'Sistema de Ponto Eletrônico');
            $companyCnpj       = (string) ($settingModel->get('company_cnpj', '') ?? '');
        } catch (\Throwable $e) {
            $this->companyName = 'Sistema de Ponto Eletrônico';
        }

        $this->filterRenderer  = new ReportFilterRenderer();
        $formatter             = new ExcelSheetFormatter($this->companyName, $this->filterRenderer, $companyCnpj);
        $operationalBuilder    = new ExcelOperationalReportBuilder($formatter);
        $administrativeBuilder = new ExcelAdministrativeReportBuilder($formatter);
        $this->composer        = new ExcelWorkbookComposer($operationalBuilder, $administrativeBuilder);
        $this->exporter        = new ExcelFileExporter();
    }

    public function generateReport(string $type, array $data, array $filters = []): array
    {
        try {
            $composed = $this->composer->compose($type, $data, $filters);
            if (($composed['success'] ?? false) !== true) {
                return $composed;
            }

            return $this->exporter->export($composed['spreadsheet'], $composed['filename']);
        } catch (\Throwable $e) {
            log_message('error', 'Excel generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erro ao gerar Excel',
                'details' => $e->getMessage(),
            ];
        }
    }
}
