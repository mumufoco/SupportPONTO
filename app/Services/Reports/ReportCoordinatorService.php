<?php

namespace App\Services\Reports;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Services\Queue\AsyncJobService;
use App\Services\TimesheetService;
use App\Services\AuthorizationService;

class ReportCoordinatorService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly AuditModel $auditModel = new AuditModel(),
        private readonly AsyncJobService $asyncJobService = new AsyncJobService(),
        private readonly ReportExecutionService $executionService = new ReportExecutionService(),
        private readonly ReportRequestPolicyService $requestPolicy = new ReportRequestPolicyService(),
        private readonly ReportViewService $viewService = new ReportViewService(
            new EmployeeModel(),
            new JustificationModel(),
            new TimesheetService(),
        ),
        private readonly AuthorizationService $authorizationService = new AuthorizationService(),
    ) {
    }

    public function authenticatedEmployee(): ?array
    {
        if (!session()->has('user_id')) {
            return null;
        }

        $employee = $this->employeeModel->find(session()->get('user_id'));
        if (!$employee) {
            return null;
        }

        return [
            'id' => (int) $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'department' => $employee->department,
        ];
    }

    public function getEmployeesForReports(array $actor): array
    {
        return $this->viewService->getEmployeesForReports($actor);
    }

    public function getDepartmentsForReports(): array
    {
        return $this->viewService->getDepartmentsForReports();
    }

    public function timesheetViewData(array $employee, string $month, ?string $department, ?string $selectedEmployee): array
    {
        return $this->viewService->getTimesheetViewData($employee, $month, $department, $selectedEmployee);
    }

    public function attendanceViewData(array $employee, string $startDate, string $endDate, ?string $department): array
    {
        return $this->viewService->getAttendanceViewData($employee, $startDate, $endDate, $department);
    }

    public function lateArrivalsViewData(array $employee, string $startDate, string $endDate, ?string $department): array
    {
        return $this->viewService->getLateArrivalsViewData($employee, $startDate, $endDate, $department);
    }

    public function justificationsViewData(array $employee, string $startDate, string $endDate, ?string $department, ?string $employeeId = null): array
    {
        return $this->viewService->getJustificationsViewData($employee, $startDate, $endDate, $department, $employeeId);
    }

    public function isValidType(string $type): bool
    {
        return $this->executionService->isValidType($type);
    }

    public function isValidFormat(string $format): bool
    {
        return $this->executionService->isValidFormat($format);
    }

    public function cachedHtml(string $type, array $filters): ?array
    {
        return $this->executionService->getCachedHtml($type, $filters);
    }

    public function generateSync(array $employee, string $type, string $format, array $filters, object|array|null $actor = null): array
    {
        $prepared = $this->prepareFilters($employee, $type, $format, $filters, $actor);
        if (! ($prepared['success'] ?? false)) {
            return ['success' => false, 'status' => (int) ($prepared['status'] ?? 422), 'payload' => $prepared];
        }

        $filters = $prepared['filters'];
        $result = $this->executionService->generateReportData($type, $filters);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'status' => 500, 'payload' => $result];
        }

        $output = $this->executionService->formatReport($type, $result['data'], $format, $filters);

        if ($format === 'html') {
            $this->executionService->cacheHtml($type, $filters, $result['data']);
        }

        $this->auditModel->log(
            $employee['id'],
            'REPORT_GENERATED',
            'reports',
            null,
            null,
            ['type' => $type, 'format' => $format, 'filters' => $filters],
            "Relatório gerado: {$type} ({$format})",
            'info'
        );

        if (!($output['success'] ?? false)) {
            return ['success' => false, 'status' => 500, 'payload' => $output];
        }

        helper('operational_link');

        return [
            'success' => true,
            'status' => 200,
            'output' => $output,
        ];
    }

    public function queueGeneration(array $employee, string $type, string $format, array $filters, object|array|null $actor = null): array
    {
        $prepared = $this->prepareFilters($employee, $type, $format, $filters, $actor);
        if (! ($prepared['success'] ?? false)) {
            return [
                'success' => false,
                'status' => (int) ($prepared['status'] ?? 422),
                'data' => $prepared,
            ];
        }

        $filters = $prepared['filters'];
        $departmentRestriction = $this->authorizationService->resolveDepartmentRestriction($actor, $employee);

        $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_REPORT_GENERATE, [
            'type' => $type,
            'format' => $format,
            'filters' => $filters,
            'department_restriction' => $departmentRestriction,
        ], [
            'employee_id' => (int) $employee['id'],
            'queue' => 'reports',
            'priority' => 60,
            'max_attempts' => 4,
        ]);

        $jobId = $job['job_id'] ?? null;
        $this->auditModel->log(
            (int) $employee['id'],
            'REPORT_QUEUED',
            'async_jobs',
            null,
            null,
            ['type' => $type, 'format' => $format, 'job_id' => $jobId, 'filters' => $filters],
            "Relatório enfileirado: {$type} ({$format})",
            'info'
        );

        helper('operational_link');

        return [
            'success' => true,
            'status' => 202,
            'data' => [
                'success' => true,
                'queued' => true,
                'message' => 'Relatório enfileirado para processamento em background.',
                'job_id' => $jobId,
                'status_url' => sp_reports_status_url((string) $jobId),
                'download_url' => sp_reports_download_url((string) $jobId),
            ],
        ];
    }



    /**
     * @return array{success:bool,filters?:array,error?:string,status?:int}
     */
    private function prepareFilters(array $employee, string $type, string $format, array $filters, object|array|null $actor): array
    {
        $prepared = $this->requestPolicy->normalizeAndValidate($type, $format, $filters);
        if (! ($prepared['success'] ?? false)) {
            return $prepared;
        }

        $departmentRestriction = $this->authorizationService->resolveDepartmentRestriction($actor, $employee);

        return [
            'success' => true,
            'filters' => $this->requestPolicy->applyDepartmentRestriction($prepared['filters'] ?? [], $departmentRestriction),
        ];
    }

    public function resolveJobOwnerEmployee(int $ownerId): ?object
    {
        if ($ownerId <= 0) {
            return null;
        }

        return $this->employeeModel->find($ownerId);
    }

    public function getJobStatus(string $jobId): object|array|null
    {
        return $this->asyncJobService->getJobStatus($jobId);
    }

    public function jobValue(object|array $job, string $key): mixed
    {
        return is_array($job) ? ($job[$key] ?? null) : ($job->{$key} ?? null);
    }

    public function isAsyncFormat(string $format): bool
    {
        return in_array($format, ['pdf', 'excel', 'csv', 'xml', 'txt', 'afd'], true);
    }
}
