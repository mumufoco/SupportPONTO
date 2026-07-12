<?php

namespace App\Controllers\Settings;

use App\Models\WorkShiftModel;
use App\Models\HolidayModel;
use App\Models\EmployeeModel;

class WorkforceSettingsController extends BaseSettingsController
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    protected WorkShiftModel $workShiftModelInstance;
    protected HolidayModel   $holidayModelInstance;
    protected EmployeeModel  $employeeModelInstance;

    public function __construct()
    {
        parent::__construct();
        $this->workShiftModelInstance = new WorkShiftModel();
        $this->holidayModelInstance   = new HolidayModel();
        $this->employeeModelInstance  = new EmployeeModel();
    }

    private function workShiftModel(): WorkShiftModel
    {
        return $this->workShiftModelInstance;
    }

    private function employeeModel(): EmployeeModel
    {
        return $this->employeeModelInstance;
    }

    private function holidayModel(): HolidayModel
    {
        return $this->holidayModelInstance;
    }



    private function paginationParams(): array
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        return [$page, $perPage];
    }

    private function paginationMeta($pager, int $page, int $perPage, int $count): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'count' => $count,
            'total' => (int) ($pager?->getTotal() ?? $count),
            'page_count' => (int) ($pager?->getPageCount() ?? 1),
        ];
    }

    public function workShifts()
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();
        [$page, $perPage] = $this->paginationParams();

        $records = $shiftModel
            ->orderBy('active', 'DESC')
            ->orderBy('type', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->paginate($perPage, 'default', $page);

        return $this->response->setJSON([
            'success' => true,
            'data' => $records,
            'meta' => $this->paginationMeta($shiftModel->pager, $page, $perPage, count($records)),
        ]);
    }

    public function storeWorkShift()
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'start_time' => 'required',
            'end_time' => 'required',
            'type' => 'required|in_list[morning,afternoon,night,custom]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => implode(', ', $this->validator->getErrors())]);
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time' => $this->request->getPost('end_time'),
            'type' => $this->request->getPost('type'),
            'break_duration' => (int) $this->request->getPost('break_duration'),
            'color' => $this->request->getPost('color') ?: '#9DB89D',
            'active' => 1,
        ];

        if ($shiftModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Jornada criada com sucesso']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao criar jornada']);
    }

    public function updateWorkShift($id)
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $shift = $shiftModel->find($id);
        if (!$shift) {
            return $this->response->setJSON(['success' => false, 'message' => 'Jornada não encontrada']);
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time' => $this->request->getPost('end_time'),
            'type' => $this->request->getPost('type'),
            'break_duration' => (int) $this->request->getPost('break_duration'),
            'color' => $this->request->getPost('color') ?: '#9DB89D',
        ];

        if ($shiftModel->update($id, $data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Jornada atualizada com sucesso']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao atualizar jornada']);
    }

    public function toggleWorkShift($id)
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $shift = $shiftModel->find($id);
        if (!$shift) {
            return $this->response->setJSON(['success' => false, 'message' => 'Jornada não encontrada']);
        }

        $active = is_array($shift) ? ($shift['active'] ?? 0) : ($shift->active ?? 0);
        $shiftModel->update($id, ['active' => $active ? 0 : 1]);

        return $this->response->setJSON(['success' => true, 'message' => 'Status alterado']);
    }

    public function deleteWorkShift($id)
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $shift = $shiftModel->find($id);
        if (!$shift) {
            return $this->response->setJSON(['success' => false, 'message' => 'Jornada não encontrada']);
        }

        if ($shiftModel->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Jornada excluída']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao excluir']);
    }

    public function vacations()
    {
        $this->requireAdminAccess();

        $employeeModel = $this->employeeModel();
        $employees = $employeeModel->where('active', true)->orderBy('name', 'ASC')->findAll();

        return view('settings/vacations/index', [
            'employees' => $employees,
        ]);
    }

    public function holidays()
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();
        [$page, $perPage] = $this->paginationParams();

        $search = (string) ($this->request->getGet('search') ?? '');
        $filterType = (string) ($this->request->getGet('type') ?? '');
        $filterStatus = (string) ($this->request->getGet('status') ?? '');

        $query = $holidayModel;
        if ($search !== '') {
            $query = $query->like('name', $search);
        }
        if ($filterType !== '') {
            $query = $query->where('type', $filterType);
        }
        if ($filterStatus === 'active') {
            $query = $query->where('active', 1);
        } elseif ($filterStatus === 'inactive') {
            $query = $query->where('active', 0);
        }

        $records = $query
            ->orderBy('active', 'DESC')
            ->orderBy('date', 'ASC')
            ->paginate($perPage, 'default', $page);

        $total = $holidayModel->countAllResults(false);

        return view('settings/holidays/index', [
            'holidays'   => $records,
            'pager'      => $holidayModel->pager,
            'filters'    => ['search' => $search, 'type' => $filterType, 'status' => $filterStatus, 'per_page' => $perPage],
            'meta'       => ['total' => $total, 'count' => count($records), 'page' => $page, 'per_page' => $perPage],
        ]);
    }

    public function holidaysJson()
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();
        [$page, $perPage] = $this->paginationParams();

        $records = $holidayModel
            ->orderBy('active', 'DESC')
            ->orderBy('date', 'ASC')
            ->paginate($perPage, 'default', $page);

        return $this->response->setJSON([
            'success' => true,
            'data'    => $records,
            'meta'    => $this->paginationMeta($holidayModel->pager, $page, $perPage, count($records)),
        ]);
    }

    public function storeHoliday()
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'date' => 'required|valid_date',
            'type' => 'required|in_list[national,state,municipal,company,non_working]',
        ];

        if (!$this->validate($rules)) {
            $this->setError(implode('<br>', $this->validator->getErrors()));
            return redirect()->back()->withInput();
        }

        $data = [
            'name'         => $this->request->getPost('name'),
            'description'  => $this->request->getPost('description') ?: null,
            'date'         => $this->request->getPost('date'),
            'type'         => $this->request->getPost('type'),
            'recurring'    => $this->request->getPost('recurring') ? 1 : 0,
            'blocks_punch' => $this->request->getPost('blocks_punch') ? 1 : 0,
            'active'       => 1,
        ];

        if ($holidayModel->insert($data)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Feriado criado com sucesso']);
            }
            $this->setSuccess('Feriado/dia não trabalhado criado com sucesso!');
            return redirect()->to(sp_route_url('settings.holidays'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao criar feriado']);
        }
        $this->setError('Erro ao criar feriado.');
        return redirect()->back()->withInput();
    }

    public function editHoliday($id)
    {
        $this->requireAdminAccess();
        $holiday = $this->holidayModel()->find($id);
        if (!$holiday) {
            $this->setError('Feriado não encontrado.');
            return redirect()->to(sp_route_url('settings.holidays'));
        }

        return view('settings/holidays/edit', ['holiday' => $holiday]);
    }

    public function updateHoliday($id)
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $holiday = $holidayModel->find($id);
        if (!$holiday) {
            return $this->response->setJSON(['success' => false, 'message' => 'Feriado não encontrado']);
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'date' => 'required|valid_date',
            'type' => 'required|in_list[national,state,municipal,company,non_working]',
        ];

        if (!$this->validate($rules)) {
            $this->setError(implode('<br>', $this->validator->getErrors()));
            return redirect()->back()->withInput();
        }

        $data = [
            'name'         => $this->request->getPost('name'),
            'description'  => $this->request->getPost('description') ?: null,
            'date'         => $this->request->getPost('date'),
            'type'         => $this->request->getPost('type'),
            'recurring'    => $this->request->getPost('recurring') ? 1 : 0,
            'blocks_punch' => $this->request->getPost('blocks_punch') ? 1 : 0,
        ];

        if ($holidayModel->update($id, $data)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Feriado atualizado com sucesso']);
            }
            $this->setSuccess('Feriado atualizado com sucesso!');
            return redirect()->to(sp_route_url('settings.holidays'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao atualizar feriado']);
        }
        $this->setError('Erro ao atualizar feriado.');
        return redirect()->back()->withInput();
    }

    public function toggleHoliday($id)
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $holiday = $holidayModel->find($id);
        if (!$holiday) {
            return $this->response->setJSON(['success' => false, 'message' => 'Feriado não encontrado']);
        }

        $active = is_array($holiday) ? ($holiday['active'] ?? 0) : ($holiday->active ?? 0);
        $holidayModel->skipValidation(true)->update($id, ['active' => $active ? 0 : 1]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Status alterado']);
        }
        $this->setSuccess($active ? 'Feriado desativado.' : 'Feriado ativado.');
        return redirect()->to(sp_route_url('settings.holidays'));
    }

    public function deleteHoliday($id)
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $holiday = $holidayModel->find($id);
        if (!$holiday) {
            return $this->response->setJSON(['success' => false, 'message' => 'Feriado não encontrado']);
        }

        if ($holidayModel->delete($id)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Feriado excluído']);
            }
            $this->setSuccess('Feriado excluído com sucesso!');
            return redirect()->to(sp_route_url('settings.holidays'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao excluir']);
        }
        $this->setError('Erro ao excluir feriado.');
        return redirect()->to(sp_route_url('settings.holidays'));
    }
}
