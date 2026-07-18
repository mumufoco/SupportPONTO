<?php

namespace App\Controllers\Settings;

use App\Services\Settings\Catalog\CatalogSettingsActionService;
use App\Services\Settings\Catalog\DepartmentCatalogService;
use App\Services\Settings\Catalog\PositionCatalogService;
use App\Services\Settings\Catalog\RoleCatalogService;
use App\Services\Settings\Catalog\WorkUnitCatalogService;
use Config\Services;

class CatalogSettingsController extends BaseSettingsController
{
    public function __construct(
        private readonly ?WorkUnitCatalogService $workUnits = null,
        private readonly ?DepartmentCatalogService $departments = null,
        private readonly ?PositionCatalogService $positions = null,
        private readonly ?RoleCatalogService $roles = null,
        private readonly ?CatalogSettingsActionService $actionService = null,
    ) {
        parent::__construct();
    }


    private function workUnitsService(): WorkUnitCatalogService
    {
        return $this->workUnits ?? Services::workUnitCatalogService(false);
    }

    private function departmentsService(): DepartmentCatalogService
    {
        return $this->departments ?? Services::departmentCatalogService(false);
    }

    private function positionsService(): PositionCatalogService
    {
        return $this->positions ?? Services::positionCatalogService(false);
    }

    private function rolesService(): RoleCatalogService
    {
        return $this->roles ?? Services::roleCatalogService(false);
    }

    private function actionService(): CatalogSettingsActionService
    {
        return $this->actionService ?? Services::catalogSettingsActionService(false);
    }

    public function workUnits()
    {
        $this->requireAdminAccess();

        $listing = $this->workUnitsService()->listing($this->request->getGet());

        return view('settings/work_units/index', [
            'workUnits' => $listing['items'],
            'filters' => $listing['filters'],
            'meta' => $listing['meta'],
        ]);
    }

    public function createWorkUnit()
    {
        $this->requireAdminAccess();
        return view('settings/work_units/create');
    }

    public function storeWorkUnit()
    {
        return $this->storeSimpleCatalog(
            $this->workUnitsService()->createRules(),
            fn () => $this->workUnitsService()->create(security_sanitize($this->request->getPost() ?? [])),
            '/settings/work-units',
            'Unidade de trabalho criada com sucesso!'
        );
    }

    public function editWorkUnit($id)
    {
        return $this->editSimpleCatalog(
            $this->workUnitsService()->find((int) $id),
            '/settings/work-units',
            'Unidade de trabalho não encontrada.',
            'settings/work_units/edit',
            'workUnit'
        );
    }

    public function updateWorkUnit($id)
    {
        $workUnit = $this->workUnitsService()->find((int) $id);
        if (!$workUnit) {
            return $this->redirectCatalogNotFound('/settings/work-units', 'Unidade de trabalho não encontrada.');
        }

        return $this->updateSimpleCatalog(
            $this->workUnitsService()->updateRules((int) $id),
            fn () => $this->workUnitsService()->update((int) $id, security_sanitize($this->request->getPost() ?? [])),
            '/settings/work-units',
            'Unidade de trabalho atualizada com sucesso!'
        );
    }

    public function toggleWorkUnit($id)
    {
        return $this->toggleCatalog($this->workUnitsService()->toggle((int) $id), 'Unidade não encontrada');
    }

    public function deleteWorkUnit($id)
    {
        $this->requireAdminAccess();

        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido.']);
        }

        $result = $this->workUnitsService()->delete((int) $id);

        return $this->response->setJSON($result);
    }

    public function departments()
    {
        $this->requireAdminAccess();

        $listing = $this->departmentsService()->listing($this->request->getGet());

        return view('settings/departments/index', [
            'departments' => $listing['items'],
            'filters' => $listing['filters'],
            'meta' => $listing['meta'],
        ]);
    }

    public function createDepartment()
    {
        $this->requireAdminAccess();
        return view('settings/departments/create');
    }

    public function storeDepartment()
    {
        return $this->storeSimpleCatalog(
            $this->departmentsService()->createRules(),
            fn () => $this->departmentsService()->create(security_sanitize($this->request->getPost() ?? [])),
            '/settings/departments',
            'Departamento criado com sucesso!'
        );
    }

    public function editDepartment($id)
    {
        return $this->editSimpleCatalog(
            $this->departmentsService()->find((int) $id),
            '/settings/departments',
            'Departamento não encontrado.',
            'settings/departments/edit',
            'department'
        );
    }

    public function updateDepartment($id)
    {
        $department = $this->departmentsService()->find((int) $id);
        if (!$department) {
            return $this->redirectCatalogNotFound('/settings/departments', 'Departamento não encontrado.');
        }

        return $this->updateSimpleCatalog(
            $this->departmentsService()->updateRules((int) $id),
            fn () => $this->departmentsService()->update((int) $id, security_sanitize($this->request->getPost() ?? [])),
            '/settings/departments',
            'Departamento atualizado com sucesso!'
        );
    }

    public function toggleDepartment($id)
    {
        return $this->toggleCatalog($this->departmentsService()->toggle((int) $id), 'Departamento não encontrado');
    }

    public function deleteDepartment($id)
    {
        $this->requireAdminAccess();

        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido.']);
        }

        $result = $this->departmentsService()->delete((int) $id);

        return $this->response->setJSON($result);
    }

    public function positions()
    {
        $this->requireAdminAccess();

        $listing = $this->positionsService()->listing($this->request->getGet());

        return view('settings/positions/index', [
            'positions' => $listing['items'],
            'departments' => $this->departmentsService()->active(),
            'filters' => $listing['filters'],
            'meta' => $listing['meta'],
        ]);
    }

    public function createPosition()
    {
        $this->requireAdminAccess();

        return view('settings/positions/create', ['departments' => $this->departmentsService()->active()]);
    }

    public function storePosition()
    {
        $this->requireAdminAccess();

        if (!$this->validate($this->positionsService()->rules())) {
            return $this->redirectValidationErrors();
        }

        $result = $this->positionsService()->create(security_sanitize($this->request->getPost() ?? []));
        $persist = $this->actionService()->persistResult(
            (bool) ($result['success'] ?? false),
            $result['errors'] ?? [],
            'CatalogSettingsController::storePosition failed',
            'Erro ao salvar cargo.'
        );

        if (!($persist['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $persist['error_message'])->with('errors', $persist['errors']);
        }

        $this->setSuccess('Cargo criado com sucesso!');
        return redirect()->to(route_to('settings.positions'));
    }

    public function editPosition($id)
    {
        $this->requireAdminAccess();

        $position = $this->positionsService()->find((int) $id);
        if (!$position) {
            return $this->redirectCatalogNotFound('/settings/positions', 'Cargo não encontrado.');
        }

        return view('settings/positions/edit', [
            'position' => $position,
            'departments' => $this->departmentsService()->active(),
        ]);
    }

    public function updatePosition($id)
    {
        $this->requireAdminAccess();

        $position = $this->positionsService()->find((int) $id);
        if (!$position) {
            return $this->redirectCatalogNotFound('/settings/positions', 'Cargo não encontrado.');
        }

        if (!$this->validate($this->positionsService()->rules())) {
            return $this->redirectValidationErrors();
        }

        $result = $this->positionsService()->update((int) $id, security_sanitize($this->request->getPost() ?? []));
        $persist = $this->actionService()->persistResult(
            (bool) ($result['success'] ?? false),
            $result['errors'] ?? [],
            'CatalogSettingsController::updatePosition failed for id ' . (int) $id,
            'Erro ao atualizar cargo.'
        );

        if (!($persist['success'] ?? false)) {
            return redirect()->back()->withInput()->with('error', $persist['error_message'])->with('errors', $persist['errors']);
        }

        $this->setSuccess('Cargo atualizado com sucesso!');
        return redirect()->to(route_to('settings.positions'));
    }

    public function togglePosition($id)
    {
        return $this->toggleCatalog($this->positionsService()->toggle((int) $id), 'Cargo não encontrado');
    }

    public function roles()
    {
        $this->requireAdminAccess();

        $listing = $this->rolesService()->listing($this->request->getGet());

        return view('settings/roles/index', [
            'roles' => $listing['items'],
            'filters' => $listing['filters'],
            'meta' => $listing['meta'],
        ]);
    }

    public function createRole()
    {
        $this->requireAdminAccess();
        return view('settings/roles/create');
    }

    public function storeRole()
    {
        $this->requireAdminAccess();

        if (!$this->validate($this->rolesService()->createRules())) {
            return $this->redirectValidationErrors();
        }

        $permissions = $this->request->getPost('permissions') ?? [];
        $this->rolesService()->create(security_sanitize($this->request->getPost() ?? []), $permissions);

        $this->setSuccess('Nível de acesso criado com sucesso!');
        return redirect()->to(route_to('settings.roles'));
    }

    public function editRole($id)
    {
        return $this->editSimpleCatalog(
            $this->rolesService()->find((int) $id),
            '/settings/roles',
            'Nível de acesso não encontrado.',
            'settings/roles/edit',
            'role'
        );
    }

    public function updateRole($id)
    {
        $this->requireAdminAccess();

        $role = $this->rolesService()->find((int) $id);
        if (!$role) {
            return $this->redirectCatalogNotFound('/settings/roles', 'Nível de acesso não encontrado.');
        }

        if (!$this->validate($this->rolesService()->updateRules((int) $id))) {
            return $this->redirectValidationErrors();
        }

        $permissions = $this->request->getPost('permissions') ?? [];
        $this->rolesService()->update((int) $id, security_sanitize($this->request->getPost() ?? []), $permissions);

        $this->setSuccess('Nível de acesso atualizado com sucesso!');
        return redirect()->to(route_to('settings.roles'));
    }


    public function deleteRole($id)
    {
        $this->requireAdminAccess();

        if (!$this->request->is('post')) {
            return $this->response->setJSON(['success' => false, 'message' => 'Método inválido.']);
        }

        $result = $this->rolesService()->delete((int) $id);

        return $this->response->setJSON($result);
    }

    public function toggleRole($id)
    {
        return $this->toggleCatalog($this->rolesService()->toggle((int) $id), 'Nível de acesso não encontrado');
    }

    private function storeSimpleCatalog(array $rules, callable $storeAction, string $redirectPath, string $successMessage)
    {
        $this->requireAdminAccess();

        if (!$this->validate($rules)) {
            return $this->redirectValidationErrors();
        }

        $storeAction();

        $this->setSuccess($successMessage);
        return redirect()->to($redirectPath);
    }

    private function updateSimpleCatalog(array $rules, callable $updateAction, string $redirectPath, string $successMessage)
    {
        $this->requireAdminAccess();

        if (!$this->validate($rules)) {
            return $this->redirectValidationErrors();
        }

        $updateAction();

        $this->setSuccess($successMessage);
        return redirect()->to($redirectPath);
    }

    private function editSimpleCatalog(mixed $record, string $redirectPath, string $notFoundMessage, string $view, string $key)
    {
        $this->requireAdminAccess();

        if (!$record) {
            return $this->redirectCatalogNotFound($redirectPath, $notFoundMessage);
        }

        return view($view, [$key => $record]);
    }

    private function toggleCatalog(?bool $active, string $notFoundMessage)
    {
        $this->requireAdminAccess();

        return $this->response->setJSON($this->actionService()->togglePayload($active, $notFoundMessage));
    }

    private function redirectValidationErrors()
    {
        return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
    }

    private function redirectCatalogNotFound(string $path, string $message)
    {
        $this->setError($message);
        return redirect()->to($path);
    }
}
