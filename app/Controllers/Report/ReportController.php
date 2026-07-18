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
            'departments'        => $this->reportCoordinatorService->getDepartmentsForReports(),
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
            'employees' => $this->reportCoordinatorService->getEmployeesForReports($employee),
        ]);
    }

    public function timesheetExportPdf(): ResponseInterface
    {
        return $this->timesheetExport('pdf');
    }

    public function timesheetExportExcel(): ResponseInterface
    {
        return $this->timesheetExport('excel');
    }

    public function timesheetExportCsv(): ResponseInterface
    {
        return $this->timesheetExport('csv');
    }

    /**
     * Exporta o espelho consolidado (reports/timesheet.php) com os mesmos
     * filtros da tela (month/employee_id/department), reaproveitando a
     * mesma fonte de dados (generateMonthlyTimesheet) para que o arquivo
     * bata exatamente com o que está na tela.
     */
    protected function timesheetExport(string $format): ResponseInterface
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedEmployee = $this->request->getGet('employee_id');
        $selectedDepartment = $this->request->getGet('department');

        if (empty($selectedEmployee)) {
            return redirect()->to(route_to('reports.timesheet') . '?month=' . urlencode($month))
                ->with('error', 'Selecione um colaborador para exportar o espelho de ponto.');
        }

        $viewData = $this->reportCoordinatorService->timesheetViewData($employee, $month, $selectedDepartment, $selectedEmployee);
        $sheet = $viewData['timesheets'][0] ?? null;
        if ($sheet === null) {
            return redirect()->back()->with('error', 'Nenhum espelho encontrado para exportar.');
        }

        $exporter = new \App\Services\Reports\MonthlyTimesheetExportService();
        $result = match ($format) {
            'pdf' => $exporter->buildPdf($sheet['employee'], $sheet['daily_records'], $sheet['summary'], $month),
            'excel' => $exporter->buildExcel($sheet['employee'], $sheet['daily_records'], $sheet['summary'], $month),
            default => $exporter->buildCsv($sheet['daily_records']),
        };

        $mimeType = match ($format) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        supportponto_log_event('info', 'reports', 'timesheet_exported', [
            'user_id' => $this->session?->get('user_id'),
            'target_employee_id' => (int) $selectedEmployee,
            'month' => $month,
            'format' => $format,
        ]);

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setHeader('Content-Length', (string) strlen($result['content']))
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($result['content']);
    }

    public function attendance()
    {
        return $this->monthRangeView('attendance', 'attendanceData', fn (array $employee, string $start, string $end, ?string $department): array => $this->reportCoordinatorService->attendanceViewData($employee, $start, $end, $department));
    }

    public function attendanceExportPdf(): ResponseInterface
    {
        return $this->attendanceExport('pdf');
    }

    public function attendanceExportExcel(): ResponseInterface
    {
        return $this->attendanceExport('excel');
    }

    public function attendanceExportCsv(): ResponseInterface
    {
        return $this->attendanceExport('csv');
    }

    /**
     * Exporta a tela de assiduidade (reports/attendance.php) com os mesmos
     * filtros da tela (month/department), reaproveitando a mesma fonte de
     * dados (attendanceViewData) para que o arquivo bata com o que está na tela.
     */
    protected function attendanceExport(string $format): ResponseInterface
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $range = $this->reportControllerActionService->monthRange($month);

        $viewData = $this->reportCoordinatorService->attendanceViewData($employee, $range['start_date'], $range['end_date'], $selectedDepartment);
        $attendanceData = $viewData['attendanceData'] ?? [];

        $exporter = new \App\Services\Reports\AttendanceExportService();
        $result = match ($format) {
            'pdf' => $exporter->buildPdf($attendanceData, $month, $selectedDepartment),
            'excel' => $exporter->buildExcel($attendanceData, $month, $selectedDepartment),
            default => $exporter->buildCsv($attendanceData),
        };

        $mimeType = match ($format) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        supportponto_log_event('info', 'reports', 'attendance_exported', [
            'user_id' => $this->session?->get('user_id'),
            'month' => $month,
            'department' => $selectedDepartment,
            'format' => $format,
        ]);

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setHeader('Content-Length', (string) strlen($result['content']))
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($result['content']);
    }

    public function lateArrivals()
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $selectedEmployee = $this->request->getGet('employee_id');
        // So consulta o banco (varredura por colaborador em getLateArrivalsViewData) depois
        // de uma busca explicita - evita rodar a consulta pesada a cada carregamento direto
        // da pagina e deixa a tela vazia ate o usuario definir os filtros desejados.
        $hasSearched = $this->request->getGet('searched') !== null;

        $lateArrivalsData = [];
        $departments = $this->reportCoordinatorService->getDepartmentsForReports();

        if ($hasSearched) {
            $range = $this->reportControllerActionService->monthRange($month);
            $viewData = $this->reportCoordinatorService->lateArrivalsViewData(
                $employee,
                $range['start_date'],
                $range['end_date'],
                $selectedDepartment,
                $selectedEmployee
            );
            $lateArrivalsData = $viewData['lateArrivalsData'];
            $departments = $viewData['departments'];
        }

        return view('reports/late_arrivals', [
            'employee' => $employee,
            'lateArrivalsData' => $lateArrivalsData,
            'hasSearched' => $hasSearched,
            'month' => $month,
            'selectedDepartment' => $selectedDepartment,
            'selectedEmployee' => $selectedEmployee,
            'departments' => $departments,
            'employees' => $this->reportCoordinatorService->getEmployeesForReports($employee),
        ]);
    }

    public function lateArrivalsExportPdf(): ResponseInterface
    {
        return $this->lateArrivalsExport('pdf');
    }

    public function lateArrivalsExportExcel(): ResponseInterface
    {
        return $this->lateArrivalsExport('excel');
    }

    public function lateArrivalsExportCsv(): ResponseInterface
    {
        return $this->lateArrivalsExport('csv');
    }

    /**
     * Exporta a tela de atrasos (reports/late_arrivals.php) com os mesmos
     * filtros da tela (month/department/employee_id), reaproveitando a
     * mesma fonte de dados (lateArrivalsViewData) para que o arquivo bata
     * com o que está na tela.
     */
    protected function lateArrivalsExport(string $format): ResponseInterface
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $selectedEmployee = $this->request->getGet('employee_id');
        $range = $this->reportControllerActionService->monthRange($month);

        $viewData = $this->reportCoordinatorService->lateArrivalsViewData(
            $employee,
            $range['start_date'],
            $range['end_date'],
            $selectedDepartment,
            $selectedEmployee
        );
        $lateArrivalsData = $viewData['lateArrivalsData'] ?? [];

        $exporter = new \App\Services\Reports\LateArrivalsExportService();
        $result = match ($format) {
            'pdf' => $exporter->buildPdf($lateArrivalsData, $month, $selectedDepartment),
            'excel' => $exporter->buildExcel($lateArrivalsData, $month, $selectedDepartment),
            default => $exporter->buildCsv($lateArrivalsData),
        };

        $mimeType = match ($format) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        supportponto_log_event('info', 'reports', 'late_arrivals_exported', [
            'user_id' => $this->session?->get('user_id'),
            'month' => $month,
            'department' => $selectedDepartment,
            'format' => $format,
        ]);

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setHeader('Content-Length', (string) strlen($result['content']))
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($result['content']);
    }

    public function justifications()
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $selectedEmployee = $this->request->getGet('employee_id');
        $range = $this->reportControllerActionService->monthRange($month);

        $viewData = $this->reportCoordinatorService->justificationsViewData(
            $employee,
            $range['start_date'],
            $range['end_date'],
            $selectedDepartment,
            $selectedEmployee
        );

        return view('reports/justifications', [
            'employee' => $employee,
            'justifications' => $viewData['justifications'],
            'summary' => $viewData['summary'],
            'month' => $month,
            'selectedDepartment' => $selectedDepartment,
            'selectedEmployee' => $selectedEmployee,
            'departments' => $viewData['departments'],
            'employees' => $this->reportCoordinatorService->getEmployeesForReports($employee),
        ]);
    }

    public function justificationsExportPdf(): ResponseInterface
    {
        return $this->justificationsExport('pdf');
    }

    public function justificationsExportExcel(): ResponseInterface
    {
        return $this->justificationsExport('excel');
    }

    public function justificationsExportCsv(): ResponseInterface
    {
        return $this->justificationsExport('csv');
    }

    /**
     * Exporta a tela de justificativas (reports/justifications.php) com os
     * mesmos filtros da tela (month/department/employee_id), reaproveitando a
     * mesma fonte de dados (justificationsViewData) para que o arquivo bata
     * com o que está na tela.
     */
    protected function justificationsExport(string $format): ResponseInterface
    {
        $employee = $this->requireOperationalReportsViewAccessOrRedirect();
        if ($employee === null) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
        }

        $month = $this->reportControllerActionService->monthOrCurrent($this->request->getGet('month'));
        $selectedDepartment = $this->request->getGet('department');
        $selectedEmployee = $this->request->getGet('employee_id');
        $range = $this->reportControllerActionService->monthRange($month);

        $viewData = $this->reportCoordinatorService->justificationsViewData(
            $employee,
            $range['start_date'],
            $range['end_date'],
            $selectedDepartment,
            $selectedEmployee
        );
        $justifications = $viewData['justifications'] ?? [];

        $exporter = new \App\Services\Reports\JustificationsExportService();
        $result = match ($format) {
            'pdf' => $exporter->buildPdf($justifications, $month, $selectedDepartment),
            'excel' => $exporter->buildExcel($justifications, $month, $selectedDepartment),
            default => $exporter->buildCsv($justifications),
        };

        $mimeType = match ($format) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        supportponto_log_event('info', 'reports', 'justifications_exported', [
            'user_id' => $this->session?->get('user_id'),
            'month' => $month,
            'department' => $selectedDepartment,
            'format' => $format,
        ]);

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setHeader('Content-Length', (string) strlen($result['content']))
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($result['content']);
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
