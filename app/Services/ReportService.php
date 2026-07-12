<?php

namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Services\Reports\Excel\DepartmentSummaryExcelGenerator;
use App\Services\Reports\Excel\MonthlyTimesheetExcelGenerator;
use App\Services\Reports\Excel\ReportExcelValueFormatter;
use App\Services\Reports\Excel\ReportFileCleaner;
use App\Services\Reports\ReportExecutionService;

class ReportService
{
    private MonthlyTimesheetExcelGenerator $monthlyGenerator;
    private DepartmentSummaryExcelGenerator $departmentGenerator;
    private ReportFileCleaner $fileCleaner;
    private ReportExecutionService $executionService;

    public function __construct(?ReportExecutionService $executionService = null)
    {
        $formatter = new ReportExcelValueFormatter();
        $timesheetService = new TimesheetService();
        $settingModel = new SettingModel();
        $employeeModel = new EmployeeModel();

        $this->monthlyGenerator = new MonthlyTimesheetExcelGenerator($timesheetService, $settingModel, $formatter);
        $this->departmentGenerator = new DepartmentSummaryExcelGenerator($employeeModel, $settingModel, $timesheetService, $formatter);
        $this->fileCleaner = new ReportFileCleaner();
        $this->executionService = $executionService ?? \Config\Services::reportExecutionService(false);
    }

    public function generateMonthlyTimesheetExcel(int $employeeId, string $month): array
    {
        try {
            return $this->monthlyGenerator->generate($employeeId, $month);
        } catch (\Throwable $e) {
            log_message('error', 'Report generation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Erro ao gerar relatório.',
                'details' => $e->getMessage(),
            ];
        }
    }

    public function generateDepartmentSummaryExcel(string $department, string $month): array
    {
        try {
            return $this->departmentGenerator->generate($department, $month);
        } catch (\Throwable $e) {
            log_message('error', 'Department report generation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Erro ao gerar relatório.',
                'details' => $e->getMessage(),
            ];
        }
    }

    public function deleteOldReports(int $daysOld = 30): int
    {
        return $this->fileCleaner->deleteOldReports($daysOld);
    }

    /**
     * Facade canônica para geração de dados de relatório.
     * Mantém compatibilidade com fluxos mais antigos sem duplicar lógica.
     */
    public function generateReportData(string $type, array $filters): array
    {
        return $this->executionService->generateReportData($type, $filters);
    }

    /**
     * @return array{success:bool,kind?:string,filepath?:string,filename?:string,payload?:array,error?:string}
     */
    public function generateReport(string $type, array $data, string $format, array $filters = []): array
    {
        return $this->executionService->formatReport($type, $data, $format, $filters);
    }

    public function generatePDF(string $type, array $data, array $filters = []): array
    {
        return $this->generateReport($type, $data, 'pdf', $filters);
    }

    public function generateExcel(string $type, array $data, array $filters = []): array
    {
        return $this->generateReport($type, $data, 'excel', $filters);
    }

    public function generateCSV(string $type, array $data, array $filters = []): array
    {
        return $this->generateReport($type, $data, 'csv', $filters);
    }

    public function generateXML(string $type, array $data, array $filters = []): array
    {
        return $this->generateReport($type, $data, 'xml', $filters);
    }

    public function generateTXT(string $type, array $data, array $filters = []): array
    {
        return $this->generateReport($type, $data, 'txt', $filters);
    }
}
