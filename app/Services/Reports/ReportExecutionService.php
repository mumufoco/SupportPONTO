<?php

namespace App\Services\Reports;

use Config\Services;

use App\Services\Reports\Concerns\ReportExecutionGeneratorsTrait;
use App\Services\Reports\Concerns\ReportExecutionHelperTrait;
use App\Services\Reports\Concerns\ReportExecutionCacheTrait;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\TimePunchModel;
use App\Models\TimesheetConsolidatedModel;
use App\Models\WarningModel;
use App\Services\CSVService;
use App\Services\ExcelService;
use App\Services\PDFService;
use App\Services\TXTService;
use App\Services\TimesheetService;
use App\Services\XMLService;

class ReportExecutionService
{
    use ReportExecutionGeneratorsTrait;
    use ReportExecutionCacheTrait;
    use ReportExecutionHelperTrait;
    protected EmployeeModel $employeeModel;
    protected TimePunchModel $timePunchModel;
    protected JustificationModel $justificationModel;
    protected WarningModel $warningModel;
    protected TimesheetConsolidatedModel $consolidatedModel;
    protected TimesheetService $timesheetService;
    protected PDFService $pdfService;
    protected ExcelService $excelService;
    protected CSVService $csvService;
    protected XMLService $xmlService;
    protected TXTService $txtService;
    protected string $cacheDir;

    /** @var array<int, object|null> */
    protected array $employeeCache = [];

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?TimePunchModel $timePunchModel = null,
        ?JustificationModel $justificationModel = null,
        ?TimesheetConsolidatedModel $consolidatedModel = null,
        ?TimesheetService $timesheetService = null,
        ?PDFService $pdfService = null,
        ?ExcelService $excelService = null,
        ?CSVService $csvService = null,
        ?XMLService $xmlService = null,
        ?TXTService $txtService = null,
        ?WarningModel $warningModel = null,
    ) {
        $this->employeeModel = $employeeModel ?? Services::employeeModel(false);
        $this->timePunchModel = $timePunchModel ?? Services::timePunchModel(false);
        $this->justificationModel = $justificationModel ?? Services::justificationModel(false);
        $this->warningModel = $warningModel ?? Services::warningModel(false);
        $this->consolidatedModel = $consolidatedModel ?? Services::timesheetConsolidatedModel(false);
        $this->timesheetService = $timesheetService ?? Services::timesheetService(false);
        $this->pdfService = $pdfService ?? Services::pdfService(false);
        $this->excelService = $excelService ?? Services::excelService(false);
        $this->csvService = $csvService ?? Services::csvService(false);
        $this->xmlService = $xmlService ?? Services::xmlService(false);
        $this->txtService = $txtService ?? Services::txtService(false);
        $this->cacheDir = WRITEPATH . 'cache/reports/';

        if (! is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function isValidType(?string $type): bool
    {
        return in_array($type, $this->getValidTypes(), true);
    }

    public function isValidFormat(?string $format): bool
    {
        return in_array($format, $this->getValidFormats(), true);
    }

    public function getValidTypes(): array
    {
        return array_keys($this->reportDataGenerators());
    }

    public function getValidFormats(): array
    {
        return ['html', 'pdf', 'excel', 'csv', 'xml', 'txt', 'afd', 'json'];
    }

    public function getCachedHtml(string $type, array $filters): ?array
    {
        return $this->getFromCache($this->getCacheKey($type, $filters));
    }

    public function cacheHtml(string $type, array $filters, array $data): void
    {
        $this->saveToCache($this->getCacheKey($type, $filters), ['data' => $data, 'filters' => $filters]);
    }

    public function generateReportData(string $type, array $filters): array
    {
        try {
            $generators = $this->reportDataGenerators();
            $generator = $generators[$type] ?? null;

            if ($generator === null) {
                return ['success' => false, 'error' => 'Tipo inválido'];
            }

            return $this->{$generator}($filters);
        } catch (\Throwable $e) {
            log_message('error', 'Report generation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Erro ao gerar relatório',
                'details' => $e->getMessage(),
            ];
        }
    }

    public function formatReport(string $type, array $data, string $format, array $filters): array
    {
        return match ($format) {
            'pdf' => $this->formatDownload($this->pdfService->generateReport($type, $data, $filters)),
            'excel' => $this->formatDownload($this->excelService->generateReport($type, $data, $filters)),
            'csv' => $this->formatDownload($this->csvService->generateReport($type, $data, $filters)),
            'xml' => $this->formatDownload($this->xmlService->generateReport($type, $data, $filters)),
            'txt' => $this->formatDownload($this->txtService->generateReport($type, $data, $filters)),
            'afd' => $this->formatDownload($this->txtService->generateReport('afd', $data, $filters)),
            'json' => $this->jsonPayload($data, $filters, true),
            default => $this->jsonPayload($data, $filters, false),
        };
    }



















    protected function formatDownload(array $result): array
    {
        if (! ($result['success'] ?? false)) {
            return ['success' => false, 'error' => $result['error'] ?? 'Erro ao gerar arquivo'];
        }

        return [
            'success' => true,
            'kind' => 'download',
            'filepath' => $result['filepath'],
            'filename' => $result['filename'],
        ];
    }


















}
