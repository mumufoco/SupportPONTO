<?php

namespace App\Controllers\Employees;

use App\Controllers\BaseController;
use App\Enums\DependentKinshipType;
use App\Models\EmployeeDependentModel;
use App\Models\EmployeeModel;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class EmployeeDependentController extends BaseController
{
    protected EmployeeDependentModel $dependentModel;
    protected EmployeeModel $employeeModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->dependentModel = new EmployeeDependentModel();
        $this->employeeModel  = new EmployeeModel();

        helper('cpf');
    }

    public function index()
    {
        $this->requireManager();

        $employeeId = $this->request->getGet('employee_id') ? (int) $this->request->getGet('employee_id') : null;
        $statusFilter = $this->request->getGet('status') ?? 'all';
        $onlyActive = $statusFilter === 'active' ? true : ($statusFilter === 'inactive' ? false : null);

        return view('employees/dependents/index', [
            'dependents'  => $this->dependentModel->listWithEmployee($employeeId, $onlyActive),
            'employees'   => $this->activeEmployeesForActor(),
            'employeeId'  => $employeeId,
            'statusFilter' => $statusFilter,
            'kinshipLabels' => $this->kinshipLabels(),
        ]);
    }

    public function create()
    {
        $this->requireManager();

        return view('employees/dependents/create', [
            'employees' => $this->activeEmployeesForActor(),
            'kinshipTypes' => DependentKinshipType::cases(),
            'preselectedEmployeeId' => $this->request->getGet('employee_id') ? (int) $this->request->getGet('employee_id') : null,
        ]);
    }

    public function store()
    {
        $this->requireManager();

        $postData = $this->normalizeCheckboxes(
            security_sanitize($this->request->getPost() ?? []),
            ['irrf_dependent', 'family_allowance_dependent', 'has_disability']
        );

        if (! $this->dependentModel->insert($postData, false)) {
            return redirect()->back()->withInput()->with('errors', $this->dependentModel->errors());
        }

        $employeeId = (int) ($postData['employee_id'] ?? 0);

        return redirect()->to(site_url('employees/dependents') . '?employee_id=' . $employeeId)
            ->with('success', 'Dependente cadastrado com sucesso.');
    }

    public function edit($id = null)
    {
        $this->requireManager();

        $dependent = $this->dependentModel->find((int) $id);
        if (! $dependent) {
            return redirect()->to(site_url('employees/dependents'))->with('error', 'Dependente não encontrado.');
        }

        $employee = $this->employeeModel->find((int) $dependent->employee_id);

        return view('employees/dependents/edit', [
            'dependent' => $dependent,
            'employee'  => $employee,
            'kinshipTypes' => DependentKinshipType::cases(),
        ]);
    }

    public function update($id = null)
    {
        $this->requireManager();

        $dependent = $this->dependentModel->find((int) $id);
        if (! $dependent) {
            return redirect()->to(site_url('employees/dependents'))->with('error', 'Dependente não encontrado.');
        }

        $postData = $this->normalizeCheckboxes(
            security_sanitize($this->request->getPost() ?? []),
            ['irrf_dependent', 'family_allowance_dependent', 'has_disability', 'active']
        );
        unset($postData['employee_id']);

        if (! $this->dependentModel->update((int) $id, $postData)) {
            return redirect()->back()->withInput()->with('errors', $this->dependentModel->errors());
        }

        return redirect()->to(site_url('employees/dependents') . '?employee_id=' . (int) $dependent->employee_id)
            ->with('success', 'Dependente atualizado com sucesso.');
    }

    public function delete($id = null)
    {
        $this->requireManager();

        $dependent = $this->dependentModel->find((int) $id);
        if (! $dependent) {
            return $this->response->setJSON(['success' => false, 'message' => 'Dependente não encontrado.']);
        }

        $this->dependentModel->delete((int) $id);

        return $this->response->setJSON(['success' => true, 'message' => 'Dependente excluído com sucesso.']);
    }

    private function activeEmployeesForActor(): array
    {
        $query = $this->employeeModel->where('active IS TRUE', null, false);

        if (($this->currentUser->role ?? null) === 'gestor') {
            $query->where('department', $this->currentUser->department ?? null);
        }

        return $query->orderBy('name', 'ASC')->findAll();
    }

    /** Checkboxes ausentes no POST significam "desmarcado" — sem isso, update() manteria o valor antigo. */
    private function normalizeCheckboxes(array $postData, array $checkboxFields): array
    {
        foreach ($checkboxFields as $field) {
            $postData[$field] = !empty($postData[$field]) ? true : false;
        }

        return $postData;
    }

    private function kinshipLabels(): array
    {
        $labels = [];
        foreach (DependentKinshipType::cases() as $case) {
            $labels[$case->value] = $case->label();
        }

        return $labels;
    }
}
