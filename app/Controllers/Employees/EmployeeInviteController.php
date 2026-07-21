<?php
namespace App\Controllers\Employees;

use App\Controllers\BaseController;
use App\Services\Employees\EmployeeInviteService;
use App\Services\Employees\EmployeeCoordinatorService;
use App\Models\SettingModel;
use App\Models\RoleModel;
use App\Services\Employees\Management\EmployeeFormSupportService;
use Config\Services;

class EmployeeInviteController extends BaseController
{
    protected EmployeeInviteService $inviteService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $req, \CodeIgniter\HTTP\ResponseInterface $res, \Psr\Log\LoggerInterface $log)
    {
        parent::initController($req, $res, $log);
        $this->inviteService = new EmployeeInviteService();
    }

    /** POST /employees/invite — create invite (manager only) */
    public function create(): \CodeIgniter\HTTP\ResponseInterface
    {
        $this->requireManager();

        $data = security_sanitize($this->request->getPost() ?? []);

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->response->setJSON(['success' => false, 'message' => 'E-mail inválido.']);
        }

        $result = $this->inviteService->create($data, (int) $this->currentUser->id);
        return $this->response->setJSON($result);
    }

    /** GET /convite/{token} — public self-registration form */
    public function show(string $token): string|\CodeIgniter\HTTP\ResponseInterface
    {
        $validation = $this->inviteService->validate($token);
        if (!$validation['success']) {
            return view('employees/invite_invalid', ['message' => $validation['message']]);
        }

        $formSupport = new EmployeeFormSupportService(new SettingModel(), new RoleModel());
        $formOptions = $formSupport->loadFormData();

        return view('employees/invite_register', [
            'invite'      => $validation['invite'],
            'token'       => $token,
            'formOptions' => $formOptions,
        ]);
    }

    /** POST /convite/{token} — submit self-registration */
    public function store(string $token): string|\CodeIgniter\HTTP\ResponseInterface
    {
        $validation = $this->inviteService->validate($token);
        if (!$validation['success']) {
            return view('employees/invite_invalid', ['message' => $validation['message']]);
        }

        $invite = $validation['invite'];
        $post   = security_sanitize($this->request->getPost() ?? []);

        // Force values from invite (cannot be overridden) -- perfil de acesso e
        // tipo de contrato são definidos pelo admin ao convidar, não pelo
        // colaborador (o formulário de autocadastro nem exibe esses campos).
        $post['email']         = $invite->email;
        $post['role']          = $invite->role ?? 'funcionario';
        $post['tipo_contrato'] = $invite->tipo_contrato ?? null;
        $post['active']        = false;

        // Use coordinator to create the employee
        $coordinator = Services::employeeCoordinatorService();
        $result = $coordinator->createEmployee($post);

        if (!($result['success'] ?? false)) {
            $formSupport = new EmployeeFormSupportService(new SettingModel(), new RoleModel());
            return view('employees/invite_register', [
                'invite'      => $invite,
                'token'       => $token,
                'formOptions' => $formSupport->loadFormData(),
                'errors'      => $result['errors'] ?? [],
                'error'       => $result['message'] ?? 'Erro ao salvar cadastro.',
            ]);
        }

        $this->inviteService->markUsed($token);

        return view('employees/invite_success', [
            'name' => $post['name'] ?? $invite->name ?? 'Colaborador',
        ]);
    }
}
