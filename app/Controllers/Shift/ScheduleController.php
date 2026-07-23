<?php

namespace App\Controllers\Shift;

use App\Controllers\BaseController;
use App\Services\Shift\ScheduleCoordinatorService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ScheduleController extends BaseController
{
    private ScheduleCoordinatorService $scheduleCoordinator;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->scheduleCoordinator = ScheduleCoordinatorService::createDefault();
    }

    public function index()
    {
        $this->requireManager();

        $data = $this->scheduleCoordinator->calendarData(
            $this->request->getGet('year'),
            $this->request->getGet('month'),
            $this->request->getGet('view')
        );

        return view('schedules/index', $data);
    }

    public function create()
    {
        $this->requireManager();

        // Sanitize date param: accept only Y-m-d to prevent XSS via HTML attribute injection
        $rawDate     = (string) ($this->request->getGet('date') ?? '');
        $dateDefault = preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) ? $rawDate : date('Y-m-d');

        $data                = $this->scheduleCoordinator->createFormData();
        $data['dateDefault'] = $dateDefault;

        return view('schedules/create', $data);
    }

    public function store()
    {
        $this->requireManager();

        $rules = [
            'employee_id' => 'required|integer|is_not_unique[employees.id]',
            'shift_id' => 'required|integer|is_not_unique[work_shifts.id]',
            'date' => 'required|valid_date[Y-m-d]',
            'is_recurring' => 'permit_empty|in_list[0,1]',
        ];

        if (($this->request->getPost('is_recurring') ?? '0') === '1') {
            $rules['recurrence_end_date'] = 'required|valid_date[Y-m-d]';
        }

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->scheduleCoordinator->create(security_sanitize($this->request->getPost() ?? []), (int) $this->currentUser->id);
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao criar escala.');
            return redirect()->back()->withInput();
        }

        $this->applyAudit($result);
        $this->setSuccess($result['message'] ?? 'Escala criada com sucesso!');

        return redirect()->to(sp_schedules_index_url());
    }

    public function edit(int $id)
    {
        $this->requireManager();

        $data = $this->scheduleCoordinator->editData($id);
        if ($data === null) {
            $this->setError('Escala não encontrada.');
            return redirect()->to(sp_schedules_index_url());
        }

        return view('schedules/edit', $data);
    }

    public function update(int $id)
    {
        $this->requireManager();

        $rules = [
            'employee_id' => 'required|integer|is_not_unique[employees.id]',
            'shift_id' => 'required|integer|is_not_unique[work_shifts.id]',
            'date' => 'required|valid_date[Y-m-d]',
            'status' => 'required|in_list[scheduled,completed,cancelled,absent]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->scheduleCoordinator->update($id, security_sanitize($this->request->getPost() ?? []));
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao atualizar escala.');
            return !empty($result['not_found']) ? redirect()->to(sp_schedules_index_url()) : redirect()->back()->withInput();
        }

        $this->applyAudit($result);
        $this->setSuccess($result['message'] ?? 'Escala atualizada com sucesso!');

        return redirect()->to(sp_schedules_index_url());
    }

    public function delete(int $id)
    {
        $this->requireManager();

        $result = $this->scheduleCoordinator->delete($id);
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao excluir escala.');
            return !empty($result['not_found']) ? redirect()->to(sp_schedules_index_url()) : redirect()->back();
        }

        if (!empty($result['warning'])) {
            $this->setWarning($result['warning']);
        }

        $this->applyAudit($result);
        $this->setSuccess($result['message'] ?? 'Escala excluída com sucesso!');

        return redirect()->to(sp_schedules_index_url());
    }

    public function bulkAssign()
    {
        $this->requireManager();

        $rules = [
            'employee_ids' => 'required',
            'shift_id' => 'required|integer|is_not_unique[work_shifts.id]',
            'start_date' => 'required|valid_date[Y-m-d]',
            'end_date' => 'required|valid_date[Y-m-d]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->scheduleCoordinator->bulkAssign(security_sanitize($this->request->getPost() ?? []));
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao atribuir turnos em massa.');
            return redirect()->back()->withInput();
        }

        $this->applyAudit($result);
        $this->setSuccess($result['message'] ?? 'Escalas criadas com sucesso!');

        return redirect()->to(sp_schedules_index_url());
    }

    public function bulkAssignForm()
    {
        $this->requireManager();
        return view('schedules/bulk_assign', $this->scheduleCoordinator->createFormData());
    }

    public function mySchedules()
    {
        $this->requireAuth();

        return view('schedules/my_schedules', $this->scheduleCoordinator->mySchedulesData(
            (int) $this->currentUser->id,
            $this->request->getGet('start'),
            $this->request->getGet('end')
        ));
    }

    public function export(): ResponseInterface
    {
        $this->requireManager();

        $data = $this->scheduleCoordinator->csvData(
            $this->request->getGet('start'),
            $this->request->getGet('end')
        );

        $rows = [];
        foreach ($data['schedules'] as $schedule) {
            $rows[] = [
                date('d/m/Y', strtotime($schedule->date)),
                $schedule->employee_name,
                $schedule->shift_name,
                substr($schedule->start_time, 0, 5),
                substr($schedule->end_time, 0, 5),
                $this->scheduleCoordinator->translateStatus((string) $schedule->status),
                $schedule->notes ?? '',
            ];
        }

        return csv_download_response(
            $this->response,
            (string) $data['filename'],
            ['Data', 'Colaborador', 'Turno', 'Horário Início', 'Horário Fim', 'Status', 'Observações'],
            $rows
        );
    }

    private function applyAudit(array $result): void
    {
        $audit = $result['audit'] ?? null;
        if (!is_array($audit)) {
            return;
        }

        $this->logAudit(
            $audit['action'] ?? 'SCHEDULE_UPDATED',
            'schedules',
            $audit['entity_id'],
            $audit['old_values'] ?? null,
            $audit['new_values'] ?? null,
            $audit['description'] ?? 'Ação de escala registrada'
        );
    }
}
