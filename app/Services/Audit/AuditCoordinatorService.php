<?php

namespace App\Services\Audit;

use App\Models\EmployeeModel;

class AuditCoordinatorService
{
    private EmployeeModel $employeeModel;
    private AuditQueryService $queryService;
    private AuditExportService $exportService;
    private AuditComplianceService $complianceService;
    private AuditMaintenanceService $maintenanceService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?AuditQueryService $queryService = null,
        ?AuditExportService $exportService = null,
        ?AuditComplianceService $complianceService = null,
        ?AuditMaintenanceService $maintenanceService = null,
    ) {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->queryService = $queryService ?? AuditQueryService::createDefault();
        $this->exportService = $exportService ?? AuditExportService::createDefault();
        $this->complianceService = $complianceService ?? AuditComplianceService::createDefault();
        $this->maintenanceService = $maintenanceService ?? AuditMaintenanceService::createDefault();
    }

    public function authenticatedEmployee(): ?array
    {
        if (!session()->has('user_id')) {
            return null;
        }

        $employee = $this->employeeModel->find(session()->get('user_id'));
        if (!$employee || !is_object($employee)) {
            return null;
        }

        return [
            'id' => (int) $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'department' => $employee->department,
            'department_id' => $employee->department_id ?? null,
        ];
    }

    public function indexData(?array $actor = null): array
    {
        return $this->queryService->indexOptions($actor);
    }

    public function datatableData(array $requestData, ?array $actor = null): array
    {
        return $this->queryService->datatablePayload($requestData, $actor);
    }

    public function showData(int $id, ?array $actor = null): ?array
    {
        return $this->queryService->showData($id, $actor);
    }

    public function detailsData(int $id, ?array $actor = null): ?array
    {
        return $this->queryService->detailsData($id, $actor);
    }

    public function exportCsv(string $dateFrom, string $dateTo, ?int $actorId = null): array
    {
        $result = $this->exportService->csvExport($dateFrom, $dateTo);
        if ($actorId !== null) {
            $this->exportService->logCsvExport($actorId, $dateFrom, $dateTo, (int) ($result['count'] ?? 0));
        }

        return $result;
    }

    public function exportAfd(int $employeeId, string $dateFrom, string $dateTo): array
    {
        $result = $this->exportService->afdExport($dateFrom, $dateTo);
        $this->exportService->logAfdExport($employeeId, $dateFrom, $dateTo, (int) $result['records']);

        return $result;
    }

    public function exportPdf(string $dateFrom, string $dateTo, ?int $actorId = null): array
    {
        $result = $this->exportService->pdfExport($dateFrom, $dateTo);
        if ($actorId !== null) {
            $this->exportService->logPdfExport($actorId, $dateFrom, $dateTo, (int) ($result['count'] ?? 0));
        }

        return $result;
    }

    public function exportExcel(string $dateFrom, string $dateTo, ?int $actorId = null): array
    {
        $result = $this->exportService->excelExport($dateFrom, $dateTo);
        if ($actorId !== null) {
            $this->exportService->logExcelExport($actorId, $dateFrom, $dateTo, (int) ($result['count'] ?? 0));
        }

        return $result;
    }

    public function complianceData(): array
    {
        return $this->complianceService->complianceSummary();
    }

    public function clearLogs(int $days, int $actorId): int
    {
        return $this->maintenanceService->clearOlderThanDays($days, $actorId);
    }
}
