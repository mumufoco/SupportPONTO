<?php

namespace App\Controllers\Employees;

use App\Controllers\BaseController;
use App\Models\EmployeeChangeRequestModel;
use App\Models\EmployeeModel;
use App\Models\NotificationModel;
use App\Services\Employees\EmployeeAfdEventRecorderService;

class EmployeeChangeRequestController extends BaseController
{
    protected EmployeeChangeRequestModel $changeRequestModel;
    protected EmployeeModel $employeeModel;
    protected NotificationModel $notificationModel;
    protected EmployeeAfdEventRecorderService $employeeAfdEventRecorder;

    // Campos que o colaborador pode solicitar alteração
    public const ALLOWED_FIELDS = [
        'name'       => 'Nome completo',
        'email'      => 'E-mail',
        'phone'      => 'Telefone',
        'cpf'        => 'CPF',
        'address'    => 'Endereço',
        'birth_date' => 'Data de nascimento',
        'department' => 'Departamento',
        'position'   => 'Cargo',
        'other'      => 'Outro (descreva no campo)',
    ];

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ): void {
        parent::initController($request, $response, $logger);
        $this->changeRequestModel = new EmployeeChangeRequestModel();
        $this->employeeModel      = new EmployeeModel();
        $this->notificationModel  = new NotificationModel();
        $this->employeeAfdEventRecorder = new EmployeeAfdEventRecorderService();
    }

    // GET /employees/change-request/create/{employee_id}
    // Abre o formulário de solicitação de alteração
    public function create(int $employeeId): mixed
    {
        $this->requireAuth();

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Colaborador não encontrado.');
        }

        $isSelf = (int)($this->currentUser->id ?? 0) === $employeeId;

        // MED-04 (auditoria): isManager() checava só o papel, não o escopo — um gestor
        // do Departamento A conseguia abrir/criar solicitações de qualquer funcionário,
        // fora do seu time, diferente do resto do módulo de funcionários (que restringe
        // gestor ao próprio departamento via canAccessEmployeeRecord()).
        if (!$this->canAccessEmployeeRecord($employee)) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Sem permissão.');
        }

        // Busca solicitações pendentes do colaborador para mostrar aviso
        $pending = $this->changeRequestModel->getPendingForEmployee($employeeId);

        return view('employees/change_request_form', [
            'employee'      => $employee,
            'allowedFields' => self::ALLOWED_FIELDS,
            'pending'       => $pending,
            'isSelf'        => $isSelf,
            'currentUser'   => $this->currentUser,
        ]);
    }

    // POST /employees/change-request/store
    public function store(): mixed
    {
        $this->requireAuth();

        $employeeId = (int)$this->request->getPost('employee_id');
        $fieldKey   = $this->request->getPost('field_key');
        $newValue   = trim((string)$this->request->getPost('requested_value'));
        $justif     = trim((string)$this->request->getPost('justification'));

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return redirect()->back()->with('error', 'Colaborador não encontrado.');
        }

        // MED-04 (auditoria): mesma correção de escopo de create()/status() — ver nota lá.
        if (!$this->canAccessEmployeeRecord($employee)) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Sem permissão.');
        }

        if (!array_key_exists($fieldKey, self::ALLOWED_FIELDS)) {
            return redirect()->back()->withInput()->with('error', 'Campo inválido.');
        }

        if (strlen($justif) < 20) {
            return redirect()->back()->withInput()->with('error', 'A justificativa deve ter pelo menos 20 caracteres.');
        }

        if (empty($newValue)) {
            return redirect()->back()->withInput()->with('error', 'O novo valor é obrigatório.');
        }

        // Valor atual do campo no cadastro
        $currentValue = is_object($employee) ? ($employee->$fieldKey ?? '') : ($employee[$fieldKey] ?? '');

        $this->changeRequestModel->insert([
            'employee_id'     => $employeeId,
            'requested_by'    => (int)($this->currentUser->id ?? 0),
            'field_key'       => $fieldKey,
            'field_label'     => self::ALLOWED_FIELDS[$fieldKey],
            'current_value'   => (string)$currentValue,
            'requested_value' => $newValue,
            'justification'   => $justif,
            'status'          => 'pending',
        ]);

        // Notifica todos os admins
        $admins = $this->employeeModel->whereIn('role', ['admin', 'rh'])->findAll();
        foreach ($admins as $admin) {
            $adminId = is_object($admin) ? $admin->id : $admin['id'];
            $this->notificationModel->notify(
                (int)$adminId,
                'system',
                'Solicitação de alteração cadastral',
                esc($employee->name ?? 'Colaborador') . ' solicitou alteração de: ' . self::ALLOWED_FIELDS[$fieldKey],
                site_url('admin/change-requests'),
                'bi-pencil-square',
                'normal',
                'employee_change_request',
                (int)$employeeId
            );
        }

        return redirect()->to(site_url('employees/change-request/status/' . $employeeId))
            ->with('success', 'Solicitação enviada! Você será notificado quando o administrador revisar.');
    }

    // GET /employees/change-request/status/{employee_id}
    // Colaborador vê o histórico das suas solicitações
    public function status(int $employeeId): mixed
    {
        $this->requireAuth();

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Colaborador não encontrado.');
        }

        // MED-04 (auditoria): mesma correção de escopo de create()/store() — ver nota lá.
        if (!$this->canAccessEmployeeRecord($employee)) {
            return redirect()->to(site_url('dashboard'))->with('error', 'Sem permissão.');
        }

        $isSelf = (int)($this->currentUser->id ?? 0) === $employeeId;

        return view('employees/change_request_status', [
            'employee'    => $employee,
            'requests'    => $this->changeRequestModel->getAllForEmployee($employeeId),
            'isSelf'      => $isSelf,
            'currentUser' => $this->currentUser,
        ]);
    }

    // GET /admin/change-requests
    // Painel de revisão para o admin
    public function adminIndex(): mixed
    {
        $this->requireRole('admin');

        return view('admin/employee_change_requests', [
            'requests'     => $this->changeRequestModel->getPendingWithEmployeeData(),
            'pendingCount' => $this->changeRequestModel->getPendingCount(),
            'currentUser'  => $this->currentUser,
        ]);
    }

    // POST /admin/change-requests/{id}/approve
    public function approve(int $id): mixed
    {
        $this->requireRole('admin');

        $req = $this->changeRequestModel->find($id);
        if (!$req) {
            return redirect()->back()->with('error', 'Solicitação não encontrada.');
        }

        $note = trim((string)$this->request->getPost('review_note'));

        // Aplica a mudança no cadastro (campos conhecidos)
        $fieldKey = $req->field_key ?? '';
        $directFields = ['name', 'email', 'phone', 'cpf', 'address', 'birth_date', 'department', 'position'];

        if (in_array($fieldKey, $directFields, true)) {
            $this->employeeModel->update((int)$req->employee_id, [
                $fieldKey => $req->requested_value,
            ]);

            // Registro tipo "5" do AFD ("A" — alteração de cadastro de empregado no
            // REP), a partir do snapshot do empregado JÁ ATUALIZADO. Nunca bloqueia
            // o fluxo principal — ver EmployeeAfdEventRecorderService.
            $updatedEmployee = $this->employeeModel->find((int)$req->employee_id);
            if ($updatedEmployee !== null) {
                $this->employeeAfdEventRecorder->recordAlteration($updatedEmployee, $this->currentUser);
            }
        }

        $this->changeRequestModel->approve($id, (int)($this->currentUser->id ?? 0), $note);

        // Notifica o colaborador
        $this->notificationModel->notify(
            (int)$req->employee_id,
            'system',
            'Solicitação aprovada',
            'Sua solicitação de alteração de "' . ($req->field_label ?? $fieldKey) . '" foi aprovada.',
            site_url('employees/change-request/status/' . $req->employee_id),
            'bi-check-circle-fill',
            'normal',
            'employee_change_request',
            $id
        );

        return redirect()->to(site_url('admin/change-requests'))
            ->with('success', 'Solicitação aprovada e alteração aplicada.');
    }

    // POST /admin/change-requests/{id}/reject
    public function reject(int $id): mixed
    {
        $this->requireRole('admin');

        $req = $this->changeRequestModel->find($id);
        if (!$req) {
            return redirect()->back()->with('error', 'Solicitação não encontrada.');
        }

        $note = trim((string)$this->request->getPost('review_note'));
        if (empty($note)) {
            return redirect()->back()->with('error', 'Informe o motivo da recusa.');
        }

        $this->changeRequestModel->reject($id, (int)($this->currentUser->id ?? 0), $note);

        $this->notificationModel->notify(
            (int)$req->employee_id,
            'system',
            'Solicitação recusada',
            'Sua solicitação de alteração de "' . ($req->field_label ?? '') . '" foi recusada. Motivo: ' . $note,
            site_url('employees/change-request/status/' . $req->employee_id),
            'bi-x-circle-fill',
            'normal',
            'employee_change_request',
            $id
        );

        return redirect()->to(site_url('admin/change-requests'))
            ->with('success', 'Solicitação recusada.');
    }
}
