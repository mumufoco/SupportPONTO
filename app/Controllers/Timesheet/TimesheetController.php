<?php

namespace App\Controllers\Timesheet;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\TimePunchModel;
use App\Models\TimesheetConsolidatedModel;
use App\Services\Queue\AsyncJobService;
use App\Services\Timesheet\TimesheetManagementService;
use App\Services\Timesheet\TimesheetReadService;
use App\Services\Timesheet\TimesheetWorkflowService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class TimesheetController extends BaseController
{
    protected EmployeeModel $employeeModel;
    protected TimesheetConsolidatedModel $consolidatedModel;
    protected TimePunchModel $timePunchModel;
    protected TimesheetReadService $timesheetReadService;
    protected TimesheetWorkflowService $timesheetWorkflowService;
    protected TimesheetManagementService $timesheetManagementService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->employeeModel = new EmployeeModel();
        $this->consolidatedModel = new TimesheetConsolidatedModel();
        $this->timePunchModel = new TimePunchModel();

        $this->timesheetReadService = new TimesheetReadService($this->employeeModel, $this->timePunchModel, $this->consolidatedModel);
        $this->timesheetWorkflowService = new TimesheetWorkflowService();
        $this->timesheetManagementService = new TimesheetManagementService(
            $this->employeeModel,
            $this->consolidatedModel,
            $this->timesheetReadService,
            new AsyncJobService(),
            new AuditModel(),
        );
    }

    public function employee($employeeId)
    {
        return $this->index($employeeId);
    }

    public function index($employeeId = null)
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $selectedMonth = normalize_month_reference($this->request->getGet('month'));
        $targetEmployeeId = (int) ($employeeId ?: ($this->request->getGet('employee_id') ?: $actor['id']));

        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return redirect()->back()->with('error', $access['message']);
        }

        $monthData = $this->timesheetReadService->getMonthlyTimesheetData($targetEmployeeId, $selectedMonth);

        return view('timesheet/index', [
            'employee' => $actor,
            'viewingEmployee' => $access['employee'],
            'selectedMonth' => $selectedMonth,
            'summary' => $monthData['summary'],
            'dailyRecords' => $monthData['dailyRecords'],
            'warning' => $monthData['warning'],
        ]);
    }

    public function history()
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $filters = [
            'start_date' => $this->request->getGet('start_date') ?: date('Y-m-01'),
            'end_date' => $this->request->getGet('end_date') ?: date('Y-m-d'),
            'type' => $this->request->getGet('type'),
            'method' => $this->request->getGet('method'),
        ];

        $targetEmployeeId = (int) ($this->request->getGet('employee_id') ?: $actor['id']);
        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return redirect()->back()->with('error', $access['message']);
        }

        $historyData = $this->timesheetReadService->getHistoryData($targetEmployeeId, $filters);

        $isManager = in_array($actor['role'] ?? '', ['admin', 'rh', 'gestor'], true);
        $employeesList = $isManager
            ? $this->employeeModel->select('id, name')->where('active', true)->orderBy('name', 'ASC')->findAll()
            : [];

        return view('timesheet/history', [
            'employee'        => $actor,
            'viewingEmployee' => $access['employee'],
            'filters'         => $filters,
            'punches'         => $historyData['punches'],
            'summary'         => $historyData['summary'],
            'balance'         => $historyData['balance'],
            'employeesList'   => $employeesList,
            'isManager'       => $isManager,
            'targetEmployeeId'=> $targetEmployeeId,
        ]);
    }

    public function show($id)
    {
        return $this->punchDetails($id);
    }

    public function historyExportPdf()
    {
        return $this->historyExport('pdf');
    }

    public function historyExportExcel()
    {
        return $this->historyExport('excel');
    }

    public function historyExportCsv()
    {
        return $this->historyExport('csv');
    }

    /**
     * Exporta o espelho de ponto com os MESMOS filtros aplicados na tela
     * /timesheet/history (start_date, end_date, type, method, employee_id) —
     * antes desta rota não existia nenhum export que respeitasse esses
     * filtros (o export existente em directExport() é do saldo consolidado
     * e só entende period/irregularities).
     */
    protected function historyExport(string $format)
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $filters = [
            'start_date' => $this->request->getGet('start_date') ?: date('Y-m-01'),
            'end_date' => $this->request->getGet('end_date') ?: date('Y-m-d'),
            'type' => $this->request->getGet('type'),
            'method' => $this->request->getGet('method'),
        ];

        $targetEmployeeId = (int) ($this->request->getGet('employee_id') ?: $actor['id']);
        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return redirect()->back()->with('error', $access['message']);
        }

        $historyData = $this->timesheetReadService->getHistoryData($targetEmployeeId, $filters);

        $exporter = new \App\Services\Timesheet\TimesheetHistoryExportService();
        $result = match ($format) {
            'pdf' => $exporter->buildPdf($access['employee'], $historyData['punches'], $filters, $historyData['summary']),
            'csv' => $exporter->buildCsv($historyData['punches']),
            default => $exporter->buildExcel($access['employee'], $historyData['punches'], $filters, $historyData['summary']),
        };

        $mimeType = match ($format) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setHeader('Content-Length', (string) strlen($result['content']))
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($result['content']);
    }

    public function filter()
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return $this->response->setJSON(['error' => 'Não autenticado'])->setStatusCode(401);
        }

        $selectedMonth = normalize_month_reference($this->request->getPost('month'));
        $targetEmployeeId = (int) ($this->request->getPost('employee_id') ?: $actor['id']);

        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return $this->response->setJSON(['error' => $access['message']])->setStatusCode($access['status'] ?? 403);
        }

        $monthData = $this->timesheetReadService->getMonthlyTimesheetData($targetEmployeeId, $selectedMonth);

        return $this->response->setJSON([
            'dailyRecords' => $monthData['dailyRecords'],
            'summary' => $monthData['summary'],
        ]);
    }

    public function getKPIs()
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return $this->response->setJSON(['error' => 'Não autenticado'])->setStatusCode(401);
        }

        $targetEmployeeId = (int) ($this->request->getPost('employee_id') ?: $actor['id']);
        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return $this->response->setJSON(['error' => $access['message']])->setStatusCode($access['status'] ?? 403);
        }

        return $this->response->setJSON($this->timesheetReadService->getCurrentMonthKpis($targetEmployeeId));
    }

    public function getRecords()
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return $this->response->setJSON(['error' => 'Não autenticado'])->setStatusCode(401);
        }

        $date = $this->request->getPost('date');
        $targetEmployeeId = (int) ($this->request->getPost('employee_id') ?: $actor['id']);

        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return $this->response->setJSON(['error' => $access['message']])->setStatusCode($access['status'] ?? 403);
        }

        return $this->response->setJSON([
            'records' => $this->timesheetReadService->getFormattedRecords($targetEmployeeId, $date ?: null),
        ]);
    }

    public function day(string $date)
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        // Validate date format
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return redirect()->to(sp_timesheet_history_url())->with('error', 'Data inválida.');
        }

        $targetEmployeeId = (int) ($this->request->getGet('employee_id') ?: $actor['id']);
        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return redirect()->to(sp_timesheet_history_url())->with('error', $access['message']);
        }

        // Fetch punches for the specific day
        [$dayStart, $dayEnd] = $this->timePunchModel->getDayBounds($date);
        $rawPunches = $this->timePunchModel
            ->where('employee_id', $targetEmployeeId)
            ->where('punch_time >=', $dayStart)
            ->where('punch_time <', $dayEnd)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $punches = [];
        $invalidCount = 0;
        foreach ($rawPunches as $p) {
            $isValid = filter_var($p->is_valid ?? true, FILTER_VALIDATE_BOOLEAN);
            if (! $isValid) {
                $invalidCount++;
            }

            $lat = $p->location_lat ?? $p->latitude ?? null;
            $lng = $p->location_lng ?? $p->longitude ?? null;

            $punches[] = [
                'id'             => $p->id,
                'type'           => $p->punch_type,
                'time'           => date('H:i', strtotime((string) $p->punch_time)),
                'method'         => $p->method ?? '-',
                'nsr'            => $p->nsr ?? null,
                'is_valid'       => $isValid,
                'status'         => $p->status ?? 'approved',
                'rejection_reason' => $p->rejection_reason ?? '',
                'notes'          => $p->notes ?? '',
                'within_geofence'=> $p->within_geofence ?? null,
                'geofence_name'  => $p->geofence_name ?? null,
                'lat'            => $lat !== null ? (float) $lat : null,
                'lng'            => $lng !== null ? (float) $lng : null,
            ];
        }

        // Calculate worked hours from entrada/saida pairs
        $entradaTs = null;
        $totalSeconds = 0;
        $intervalStart = null;
        $intervalSeconds = 0;
        foreach ($rawPunches as $p) {
            $ts = strtotime((string) $p->punch_time);
            if ($p->punch_type === 'entrada')           { $entradaTs = $ts; }
            elseif ($p->punch_type === 'saida' && $entradaTs) { $totalSeconds += ($ts - $entradaTs); $entradaTs = null; }
            elseif ($p->punch_type === 'intervalo_inicio') { $intervalStart = $ts; }
            elseif ($p->punch_type === 'intervalo_fim' && $intervalStart) { $intervalSeconds += ($ts - $intervalStart); $intervalStart = null; }
        }
        $workedSeconds = max(0, $totalSeconds - $intervalSeconds);
        $workedH = (int) floor($workedSeconds / 3600);
        $workedM = (int) floor(($workedSeconds % 3600) / 60);
        $hoursWorked = $workedSeconds > 0 ? sprintf('%dh%02dmin', $workedH, $workedM) : '–';

        $hasPairs = count($rawPunches) > 0 && count($rawPunches) % 2 === 0;
        $status = count($rawPunches) === 0 ? 'Sem registros' : ($hasPairs ? 'Completo' : 'Incompleto');

        return view('timesheet/day', [
            'employee'       => $actor,
            'viewingEmployee'=> $access['employee'],
            'date'           => $date,
            'date_formatted' => date('d/m/Y', strtotime($date)) . ' (' . ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'][date('w', strtotime($date))] . ')',
            'punches'        => $punches,
            'day_summary'    => [
                'hours_worked'    => $hoursWorked,
                'status'          => $status,
                'total_punches'   => count($rawPunches),
                'inconsistencies' => $invalidCount,
                'first_punch'     => $rawPunches ? date('H:i', strtotime((string) $rawPunches[0]->punch_time)) : '-',
                'last_punch'      => $rawPunches ? date('H:i', strtotime((string) $rawPunches[count($rawPunches) - 1]->punch_time)) : '-',
            ],
        ]);
    }

    public function punchDetails($id)
    {
        $actor = $this->requireAuthenticatedEmployee() ?? [];
        $result = $this->timesheetWorkflowService->getPunchDetails((int) $id, $actor);

        return $this->respondOperationResult($result, ['punch' => $result['data'] ?? null]);
    }

    public function approvePunch($id)
    {
        $actor = $this->requireAuthenticatedEmployee() ?? [];
        $result = $this->timesheetWorkflowService->approvePunch((int) $id, $actor);

        return $this->respondOperationResult($result);
    }

    public function rejectPunch($id)
    {
        $actor = $this->requireAuthenticatedEmployee() ?? [];
        $reason = (string) $this->request->getPost('reason');
        $result = $this->timesheetWorkflowService->rejectPunch((int) $id, $reason, $actor);

        return $this->respondOperationResult($result);
    }

    public function balance()
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $targetEmployeeId = $this->request->getGet('employee_id');
        $period = $this->request->getGet('period') ?? '30';
        $days = max(1, (int) $period);
        $irregularitiesOnly = $this->request->getGet('irregularities') === '1';

        $viewingEmployeeId = (int) ($targetEmployeeId ?: $actor['id']);
        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $viewingEmployeeId);
        if (! $access['success']) {
            return redirect()->back()->with('error', $access['message']);
        }

        $viewData = $this->timesheetManagementService->buildBalanceViewData(
            $actor,
            $viewingEmployeeId,
            $access['employee'],
            $days,
            $irregularitiesOnly,
            $targetEmployeeId
        );

        return view('timesheet/balance', $viewData);
    }

    public function exportExcel()
    {
        return $this->directExport('excel');
    }

    public function exportPdf()
    {
        return $this->directExport('pdf');
    }

    public function exportCsv()
    {
        return $this->directExport('csv');
    }

    protected function directExport(string $format)
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $days               = max(1, (int) ($this->request->getGet('period') ?? 30));
        $targetEmployeeId   = (int) ($this->request->getGet('employee_id') ?: $actor['id']);
        $irregularitiesOnly = $this->request->getGet('irregularities') === '1';

        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return redirect()->back()->with('error', $access['message']);
        }

        $viewData = $this->timesheetManagementService->buildBalanceViewData(
            $actor, $targetEmployeeId, $access['employee'], $days, $irregularitiesOnly, (string) $targetEmployeeId
        );

        $exporter = new \App\Services\Timesheet\TimesheetBalanceExportService();
        $result   = match ($format) {
            'pdf' => $exporter->buildPdf($access['employee'], $viewData['records'] ?? [], $viewData['balance'], $viewData['statistics'], $days),
            'csv' => $exporter->buildCsv($access['employee'], $viewData['records'] ?? []),
            default => $exporter->buildExcel($access['employee'], $viewData['records'] ?? [], $viewData['balance'], $viewData['statistics'], $days),
        };

        $mimeType = match ($format) {
            'pdf' => 'application/pdf',
            'csv' => 'text/csv; charset=UTF-8',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        return $this->response
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"')
            ->setHeader('Content-Length', (string) strlen($result['content']))
            ->setHeader('Cache-Control', 'no-store')
            ->setBody($result['content']);
    }

    protected function queueExport(string $format)
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        $selectedMonth = normalize_month_reference($this->request->getGet('month'));
        $targetEmployeeId = (int) ($this->request->getGet('employee_id') ?: $actor['id']);

        $result = $this->timesheetManagementService->queueTimesheetExport($actor, $targetEmployeeId, $selectedMonth, $format);
        if (! ($result['success'] ?? false)) {
            return redirect()->back()->with('error', $result['message'] ?? 'Erro ao enfileirar exportação.');
        }

        return redirect()->back()->with('success', $result['message']);
    }

    protected function requireAuthenticatedEmployee(): ?array
    {
        $session = session();
        $userId = (int) ($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $employee = $this->employeeModel->find($userId);
        if (! $employee) {
            return null;
        }

        return [
            'id' => (int) $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'department' => $employee->department,
            'active' => (bool) $employee->active,
        ];
    }

    protected function respondOperationResult(array $result, ?array $successData = null)
    {
        if (! ($result['success'] ?? false)) {
            return $this->respondError($result['message'] ?? 'Operação inválida.', null, (int) ($result['status'] ?? 400));
        }

        return $this->respondSuccess($successData ?? [], $result['message'] ?? 'OK', (int) ($result['status'] ?? 200));
    }

    public function byClient()
    {
        $actor = $this->requireAuthenticatedEmployee();
        if ($actor === null) {
            return redirect()->to(sp_login_url())->with('error', 'Autenticacao necessaria.');
        }

        if (!in_array($actor['role'] ?? '', ['admin', 'rh', 'gestor'], true)) {
            return redirect()->to(sp_dashboard_url())->with('error', 'Acesso restrito a gestores e administradores.');
        }

        $workUnitModel = new \App\Models\WorkUnitModel();
        $selectedMonth = normalize_month_reference($this->request->getGet('month'));
        $selectedWorkUnitId = $this->request->getGet('work_unit_id') ? (int)$this->request->getGet('work_unit_id') : null;

        $workUnits = $workUnitModel->where('active', true)->orderBy('name', 'ASC')->findAll();
        $selectedWorkUnit = null;

        if ($selectedWorkUnitId) {
            foreach ($workUnits as $wu) {
                if ((int)$wu->id === $selectedWorkUnitId) {
                    $selectedWorkUnit = $wu;
                    break;
                }
            }
        }

        $query = $this->employeeModel->where('active', true);
        if ($selectedWorkUnitId) {
            $query = $query->where('work_unit_id', $selectedWorkUnitId);
        }
        $employees = $query->orderBy('name', 'ASC')->findAll();

        $clientData = [];
        $totals = [
            'total_hours'    => 0.0,
            'days_worked'    => 0,
            'expected_hours' => 0.0,
            'balance'        => 0.0,
            'late_arrivals'  => 0,
        ];

        foreach ($employees as $emp) {
            $monthData = $this->timesheetReadService->getMonthlyTimesheetData(
                (int)$emp->id,
                $selectedMonth
            );
            $summary = $monthData['summary'];
            $clientData[] = [
                'employee' => $emp,
                'summary'  => $summary,
            ];
            $totals['total_hours']    += (float)($summary['total_hours'] ?? 0);
            $totals['days_worked']    += (int)($summary['days_worked'] ?? 0);
            $totals['expected_hours'] += (float)($summary['expected_hours'] ?? 0);
            $totals['balance']        += (float)($summary['balance'] ?? 0);
            $totals['late_arrivals']  += (int)($summary['late_arrivals'] ?? 0);
        }

        $totals['total_hours']    = number_format($totals['total_hours'], 2);
        $totals['expected_hours'] = number_format($totals['expected_hours'], 2);

        return view('timesheet/by_client', [
            'workUnits'           => $workUnits,
            'selectedWorkUnitId'  => $selectedWorkUnitId,
            'selectedWorkUnit'    => $selectedWorkUnit,
            'selectedMonth'       => $selectedMonth,
            'clientData'          => $clientData,
            'totals'              => $totals,
        ]);
    }

}
