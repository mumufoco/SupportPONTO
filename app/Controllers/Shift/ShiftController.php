<?php

namespace App\Controllers\Shift;

use App\Controllers\BaseController;
use App\Services\Shift\ShiftCoordinatorService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class ShiftController extends BaseController
{
    private ShiftCoordinatorService $shiftCoordinator;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->shiftCoordinator = ShiftCoordinatorService::createDefault();
    }

    public function index()
    {
        $this->requireManager();

        $filters = [
            'type' => $this->request->getGet('type'),
            'status' => $this->request->getGet('status'),
            'search' => $this->request->getGet('search'),
        ];

        return view('shifts/index', $this->shiftCoordinator->indexData($filters));
    }

    public function show(int $id)
    {
        $this->requireManager();

        $data = $this->shiftCoordinator->showData($id);
        if ($data === null) {
            $this->setError('Turno não encontrado.');
            return redirect()->to(sp_shifts_index_url());
        }

        return view('shifts/show', $data);
    }

    public function create()
    {
        $this->requireManager();
        return view('shifts/create', $this->shiftCoordinator->createFormData());
    }

    public function store()
    {
        $this->requireManager();

        if (!$this->validate($this->storeRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->shiftCoordinator->create(security_sanitize($this->request->getPost() ?? []), (int) $this->currentUser->id);

        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao criar turno.');
            return redirect()->back()->withInput();
        }

        $this->applyAudit($result);

        if (!empty($result['warning'])) {
            $this->setWarning($result['warning']);
        }

        $this->setSuccess('Turno criado com sucesso!');
        return redirect()->to(sp_shifts_show_url((int) $result['shift_id']));
    }

    public function edit(int $id)
    {
        $this->requireManager();

        $data = $this->shiftCoordinator->editData($id);
        if ($data === null) {
            $this->setError('Turno não encontrado.');
            return redirect()->to(sp_shifts_index_url());
        }

        return view('shifts/edit', $data);
    }

    public function update(int $id)
    {
        $this->requireManager();

        if (!$this->validate($this->updateRules($id))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->shiftCoordinator->update($id, security_sanitize($this->request->getPost() ?? []));
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao atualizar turno.');
            return isset($result['not_found']) ? redirect()->to(sp_shifts_index_url()) : redirect()->back()->withInput();
        }

        $this->applyAudit($result);

        if (!empty($result['warning'])) {
            $this->setWarning($result['warning']);
        }

        $this->setSuccess('Turno atualizado com sucesso!');
        return redirect()->to(sp_shifts_show_url($id));
    }

    public function delete(int $id)
    {
        $this->requireManager();

        $result = $this->shiftCoordinator->delete($id);
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao excluir turno.');
            return isset($result['not_found']) ? redirect()->to(sp_shifts_index_url()) : redirect()->back();
        }

        $this->applyAudit($result);
        $this->setSuccess('Turno excluído com sucesso!');

        return redirect()->to(sp_shifts_index_url());
    }

    public function clone(int $id)
    {
        $this->requireManager();

        $result = $this->shiftCoordinator->clone($id);
        if (($result['success'] ?? false) !== true) {
            $this->setError($result['error'] ?? 'Erro ao clonar turno.');
            return isset($result['not_found']) ? redirect()->to(sp_shifts_index_url()) : redirect()->back();
        }

        $this->applyAudit($result);
        $this->setSuccess('Turno clonado com sucesso!');

        return redirect()->to(sp_shifts_edit_url((int) $result['shift_id']));
    }

    public function toggleActive(int $id)
    {
        $this->requireManager();

        $result = $this->shiftCoordinator->toggleActive($id);
        if (($result['success'] ?? false) !== true) {
            if (!empty($result['not_found'])) {
                return $this->respondError($result['error'] ?? 'Turno não encontrado.', null, 404);
            }

            return $this->respondError($result['error'] ?? 'Erro ao atualizar status do turno.');
        }

        $this->applyAudit($result);

        return $this->respondSuccess($result['data'] ?? [], $result['message'] ?? 'Status atualizado com sucesso.');
    }

    public function statistics()
    {
        $this->requireManager();
        return view('shifts/statistics', $this->shiftCoordinator->statisticsData());
    }

    private function applyAudit(array $result): void
    {
        $audit = $result['audit'] ?? null;
        if (!is_array($audit)) {
            return;
        }

        $this->logAudit(
            $audit['action'] ?? 'SHIFT_UPDATED',
            'work_shifts',
            (int) ($audit['entity_id'] ?? 0),
            $audit['old_values'] ?? null,
            $audit['new_values'] ?? null,
            $audit['description'] ?? 'Ação de turno registrada'
        );
    }

    private function storeRules(): array
    {
        return [
            'name' => 'required|min_length[3]|max_length[100]|is_unique[work_shifts.name]',
            'start_time' => 'required|valid_time',
            'end_time' => 'required|valid_time',
            'type' => 'required|in_list[morning,afternoon,night,custom]',
            'break_duration' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[480]',
            'color' => 'permit_empty|regex_match[/^#[0-9A-Fa-f]{6}$/]',
        ];
    }

    private function updateRules(int $id): array
    {
        return [
            'name' => "required|min_length[3]|max_length[100]|is_unique[work_shifts.name,id,{$id}]",
            'start_time' => 'required|valid_time',
            'end_time' => 'required|valid_time',
            'type' => 'required|in_list[morning,afternoon,night,custom]',
            'break_duration' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[480]',
            'color' => 'permit_empty|regex_match[/^#[0-9A-Fa-f]{6}$/]',
        ];
    }
}
