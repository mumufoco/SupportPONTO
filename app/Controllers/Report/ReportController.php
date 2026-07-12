<?php

namespace App\Controllers\Report;

use App\Controllers\BaseController;
use App\Services\Queue\AsyncJobService;
use App\Services\Reports\ReportControllerActionService;
use App\Services\Reports\ReportCoordinatorService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ReportController extends BaseController
{
    protected ReportCoordinatorService $reportCoordinatorService;
    protected ReportControllerActionService $reportControllerActionService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->reportCoordinatorService = new ReportCoordinatorService();
        $this->reportControllerActionService = new ReportControllerActionService();
        helper(['datetime', 'format']);
    }

    public function index(int $employeeId = null)
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado. Apenas gestores, RH e administradores podem acessar relatórios operacionais.');
        }

        supportponto_log_event('info', 'reports', 'index_opened', [
            'user_id' => $this->session?->get('user_id'),
            'selected_employee_id' => $employeeId ?? $this->request->getGet('employee_id'),
        ]);

        return view('reports/index', [
            'employee'           => $employee,
            'selectedEmployeeId' => $employeeId ?? $this->request->getGet('employee_id'),
            'employees'          => $this->reportCoordinatorService->getEmployeesForReports($employee),
        ]);
    }

    public function timesheet(?string $monthFromRoute = null)
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($monthFromRoute ?: $this->request->getGet('month'));
        $selectedEmployee = $this->request->getGet('employee_id');
        $selectedDepartment = $this->request->getGet('department');
        $viewData = $this->reportCoordinatorService->timesheetViewData($employee, $month, $selectedDepartment, $selectedEmployee);

        return view('reports/timesheet', [
            'employee' => $employee,
            'timesheets' => $viewData['timesheets'],
            'month' => $month,
            'selectedEmployee' => $selectedEmployee,
            'selectedDepartment' => $selectedDepartment,
            'departments' => $viewData['departments'],
        ]);
    }

    public function attendance()
    {
        return $this->monthRangeView('attendance', 'attendanceData', fn (array $employee, string $start, string $end, ?string $department): array => $this->reportCoordinatorService->attendanceViewData($employee, $start, $end, $department));
    }

    public function lateArrivals()
    {
        return $this->monthRangeView('late_arrivals', 'lateArrivalsData', fn (array $employee, string $start, string $end, ?string $department): array => $this->reportCoordinatorService->lateArrivalsViewData($employee, $start, $end, $department));
    }

    public function justifications()
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $selectedStatus = $this->request->getGet('status') ?: 'all';
        $range = $this->reportControllerActionService->monthRange($month);

        $viewData = $this->reportCoordinatorService->justificationsViewData(
            $employee,
            $range['start_date'],
            $range['end_date'],
            $selectedDepartment,
            $selectedStatus
        );

        return view('reports/justifications', [
            'employee' => $employee,
            'justifications' => $viewData['justifications'],
            'summary' => $viewData['summary'],
            'month' => $month,
            'selectedDepartment' => $selectedDepartment,
            'selectedStatus' => $selectedStatus,
            'departments' => $viewData['departments'],
        ]);
    }

    public function generate(): ResponseInterface
    {
        $employee = $this->requireOperationalReportsGenerateAccessOrJson();
        if ($employee === null) {
            return $this->jsonError('Acesso negado.', 403);
        }

        $type = (string) $this->request->getPost('type');
        $format = (string) ($this->request->getPost('format') ?? 'html');
        $filters = $this->reportControllerActionService->filtersFromPost($this->request->getPost('filters'));

        if (!$this->reportCoordinatorService->isValidType($type)) {
            supportponto_log_event('warning', 'reports', 'invalid_type', [
                'user_id' => $this->session?->get('user_id'),
                'type' => $type,
            ]);
            return $this->jsonError('Tipo de relatório inválido', 400);
        }

        if (!$this->reportCoordinatorService->isValidFormat($format)) {
            supportponto_log_event('warning', 'reports', 'invalid_format', [
                'user_id' => $this->session?->get('user_id'),
                'format' => $format,
            ]);
            return $this->jsonError('Formato de relatório inválido', 400);
        }

        supportponto_log_event('info', 'reports', 'generate_requested', [
            'user_id' => $this->session?->get('user_id'),
            'type' => $type,
            'format' => $format,
        ]);

        $cached = $this->reportCoordinatorService->cachedHtml($type, $filters);
        if ($cached && $format === 'html') {
            return $this->respondWithJson(['success' => true, 'data' => $cached['data'], 'cached' => true]);
        }

        if ($this->reportCoordinatorService->isAsyncFormat($format)) {
            return $this->queueReportGeneration($employee, $type, $format, $filters);
        }

        return $this->respondSyncGeneration($employee, $type, $format, $filters);
    }

    public function download(string $jobId): ResponseInterface
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee === null) {
            return $this->jsonError('Não autenticado.', 401);
        }

        $job = $this->reportCoordinatorService->getJobStatus($jobId);
        if (!$job) {
            return $this->jsonError('Relatório não encontrado.', 404);
        }

        if (!$this->canAccessManagedReportJob($job)) {
            return $this->jsonError('Sem permissão para acessar este relatório.', 403);
        }

        $status = (string) $this->reportCoordinatorService->jobValue($job, 'status');
        $resultFile = (string) $this->reportCoordinatorService->jobValue($job, 'result_file_path');

        if ($status !== AsyncJobService::STATUS_COMPLETED || $resultFile === '') {
            return $this->jsonError('Relatório ainda não está pronto para download.', 409);
        }

        helper('file_upload');
        $safePath = supportponto_safe_download_path($resultFile);

        if ($safePath === null) {
            return $this->jsonError('Arquivo do relatório não encontrado.', 404);
        }

        return $this->response->download($safePath, null)->setFileName(basename($safePath));
    }

    public function preview(string $type): ResponseInterface
    {
        $employee = $this->requireOperationalReportsGenerateAccessOrJson();
        if ($employee === null) {
            return $this->jsonError('Acesso negado.', 403);
        }

        if (!$this->reportCoordinatorService->isValidType($type)) {
            return $this->jsonError('Tipo de relatório inválido', 400);
        }

        $previewFilters = [
            'limit'      => 10,
            'start_date' => date('Y-m-01'),
            'end_date'   => date('Y-m-t'),
        ];
        $result = $this->reportCoordinatorService->generateSync($employee, $type, 'html', $previewFilters, $this->currentUser);
        if (!($result['success'] ?? false)) {
            return $this->respondWithJson($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 500));
        }

        return $this->respondWithJson([
            'success' => true,
            'type' => $type,
            'sample_data' => $result['output']['payload']['data'] ?? [],
            'preview' => true,
        ]);
    }

    public function status(string $jobId): ResponseInterface
    {
        $employee = $this->requireOperationalReportsViewAccessOrJson();
        if ($employee === null) {
            return $this->jsonError('Acesso negado.', 403);
        }

        $job = $this->reportCoordinatorService->getJobStatus($jobId);
        if (!$job) {
            return $this->jsonError('Job não encontrado', 404);
        }

        if (!$this->canAccessManagedReportJob($job)) {
            return $this->jsonError('Acesso negado.', 403);
        }

        $status = (string) $this->reportCoordinatorService->jobValue($job, 'status');

        return $this->respondWithJson([
            'success' => true,
            'job_id' => $jobId,
            'status' => $status,
            'progress' => (int) ($this->reportCoordinatorService->jobValue($job, 'progress') ?? 0),
            'created_at' => $this->reportCoordinatorService->jobValue($job, 'created_at'),
            'completed_at' => $this->reportCoordinatorService->jobValue($job, 'completed_at'),
            'download_url' => $status === AsyncJobService::STATUS_COMPLETED ? sp_reports_download_url($jobId) : null,
            'error' => $this->reportCoordinatorService->jobValue($job, 'last_error'),
        ]);
    }

    public function exportAFD(): ResponseInterface
    {
        $employee = $this->requireOperationalReportsExportAccessOrJson();
        if ($employee === null) {
            return $this->jsonError('Acesso negado. Apenas gestores, RH e administradores podem exportar relatórios operacionais como AFD.', 403);
        }

        $filters = $this->reportControllerActionService->filtersFromPost($this->request->getPost('filters'));
        if (!$this->reportControllerActionService->hasPeriod($filters)) {
            return $this->jsonError('É necessário informar o período (data inicial e final) para gerar AFD.', 400);
        }

        return $this->queueReportGeneration($employee, 'folha-ponto', 'afd', $filters);
    }

    protected function queueReportGeneration(array $employee, string $type, string $format, array $filters): ResponseInterface
    {
        $queue = $this->reportCoordinatorService->queueGeneration($employee, $type, $format, $filters, $this->currentUser);
        return $this->respondWithJson($queue['data'] ?? ['success' => false], (int) ($queue['status'] ?? 202));
    }

    protected function requireAuthenticatedEmployee(): ?array
    {
        return $this->reportCoordinatorService->authenticatedEmployee();
    }

    protected function requireOperationalReportsViewAccessOrRedirect(): ?array
    {
        $employee = $this->requireAuthenticatedEmployee();
        if (!$employee || !$this->authorizationService->canViewOperationalReports($this->currentUser)) {
            return null;
        }

        return $employee;
    }

    protected function requireOperationalReportsViewAccessOrJson(): ?array
    {
        return $this->requireOperationalReportsViewAccessOrRedirect();
    }

    protected function requireOperationalReportsGenerateAccessOrJson(): ?array
    {
        $employee = $this->requireAuthenticatedEmployee();
        if (!$employee || !$this->authorizationService->canGenerateOperationalReports($this->currentUser)) {
            return null;
        }

        return $employee;
    }

    protected function requireOperationalReportsExportAccessOrJson(): ?array
    {
        $employee = $this->requireAuthenticatedEmployee();
        if (!$employee || !$this->authorizationService->canExportOperationalReports($this->currentUser)) {
            return null;
        }

        return $employee;
    }

    protected function jsonError(string $error, int $status): ResponseInterface
    {
        return $this->respondWithJson(['success' => false, 'error' => $error], $status);
    }

    private function monthRangeView(string $viewName, string $dataKey, callable $viewDataFactory)
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $range = $this->reportControllerActionService->monthRange($month);
        $viewData = $viewDataFactory($employee, $range['start_date'], $range['end_date'], $selectedDepartment);

        return view('reports/' . $viewName, [
            'employee' => $employee,
            $dataKey => $viewData[$dataKey],
            'month' => $month,
            'selectedDepartment' => $selectedDepartment,
            'departments' => $viewData['departments'],
        ]);
    }


    private function canAccessManagedReportJob(object|array $job): bool
    {
        $jobContext = $this->buildManagedReportJobContext($job);
        $ownerId = (int) ($jobContext['owner_id'] ?? 0);

        if ($ownerId > 0 && $ownerId === (int) ($this->currentUser->id ?? 0)) {
            return true;
        }

        return $this->authorizationService->canAccessAsyncJob($this->currentUser, $jobContext);
    }

    private function buildManagedReportJobContext(object|array $job): array
    {
        $ownerId = (int) ($this->reportCoordinatorService->jobValue($job, 'employee_id') ?? 0);
        $owner = $ownerId > 0 ? $this->reportCoordinatorService->resolveJobOwnerEmployee($ownerId) : null;

        $ownerDepartment = is_object($owner)
            ? (string) ($owner->department ?? '')
            : (is_array($owner) ? (string) ($owner['department'] ?? '') : '');

        return [
            'owner_id' => $ownerId,
            'employee_id' => $ownerId,
            'owner_department' => $ownerDepartment !== '' ? $ownerDepartment : null,
            'department' => $ownerDepartment !== '' ? $ownerDepartment : null,
            'owner' => $owner,
            'employee' => $owner,
            'job_type' => (string) ($this->reportCoordinatorService->jobValue($job, 'job_type') ?? 'report'),
        ];
    }

    private function respondSyncGeneration(array $employee, string $type, string $format, array $filters): ResponseInterface
    {
        $result = $this->reportCoordinatorService->generateSync($employee, $type, $format, $filters, $this->currentUser);
        if (!($result['success'] ?? false)) {
            return $this->respondWithJson($result['payload'] ?? ['success' => false], (int) ($result['status'] ?? 500));
        }

        $output = $result['output'];
        if (($output['kind'] ?? null) === 'download') {
            return $this->response->download($output['filepath'], null)->setFileName($output['filename']);
        }

        return $this->respondWithJson($output['payload'] ?? ['success' => false], 200);
    }
}
