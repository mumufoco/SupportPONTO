<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\SettingModel;
use App\Services\Auth\RegisterPolicyService;
use App\Services\Auth\RegisterWorkflowService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class RegisterController extends BaseController
{
    protected SettingModel $settingModel;
    protected RegisterPolicyService $registerPolicyService;
    protected RegisterWorkflowService $registerWorkflowService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->settingModel            = new SettingModel();
        $this->registerPolicyService   = new RegisterPolicyService();
        $this->registerWorkflowService = new RegisterWorkflowService();
    }

    public function index(): ResponseInterface|string
    {
        if (!$this->registerPolicyService->selfRegistrationEnabled()) {
            $this->setError('O auto-cadastro está desativado. Entre em contato com o administrador.');
            return redirect()->to(route_to('login'));
        }

        if ($this->isAuthenticated()) {
            return redirect()->to(route_to('dashboard'));
        }

        return view('auth/register', $this->registrationViewData());
    }

    public function create(): string
    {
        $this->requireManager();
        return view('employees/create');
    }

    public function getPositionsByDepartment(): ResponseInterface
    {
        try {
            $departmentId = $this->request->getGet('department_id');
            $positions    = $this->registerWorkflowService->positionsByDepartment(
                $departmentId ? (string) $departmentId : null
            );
            return $this->response->setJSON($positions);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['error' => 'Erro ao carregar cargos.'])->setStatusCode(500);
        }
    }

    public function store(): ResponseInterface
    {
        if (!$this->registerPolicyService->selfRegistrationEnabled()) {
            return $this->respondError('O auto-cadastro está desativado.', null, 403);
        }

        $postData = $this->registerPolicyService->normalizePostData($this->request->getPost() ?? []);
        $this->request->setGlobal('post', $postData);

        if (!$this->validate($this->registerPolicyService->selfRegistrationRules(), $this->registerPolicyService->selfRegistrationMessages())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $cpf = (string) ($postData['cpf'] ?? '');
        if (!$this->registerPolicyService->validateCPF($cpf)) {
            $this->setError('CPF inválido.');
            return redirect()->back()->withInput();
        }

        $result = $this->registerWorkflowService->registerSelf($postData, $this->getClientIp());
        if (!($result['success'] ?? false)) {
            $this->setError((string) ($result['message'] ?? 'Erro ao realizar cadastro. Tente novamente.'));
            return redirect()->back()->withInput();
        }

        $this->setSuccess('Cadastro realizado com sucesso! Aguarde a aprovação do administrador.');
        return redirect()->to(route_to('login'));
    }

    public function storeByManager(): ResponseInterface
    {
        $this->requireManager();

        $postData = $this->registerPolicyService->normalizePostData($this->request->getPost() ?? []);
        $this->request->setGlobal('post', $postData);

        if (!$this->validate($this->registerPolicyService->managerRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $cpf = (string) ($postData['cpf'] ?? '');
        if (!$this->registerPolicyService->validateCPF($cpf)) {
            $this->setError('CPF inválido.');
            return redirect()->back()->withInput();
        }

        $role = (string) ($postData['role'] ?? '');
        if ($role === 'admin' && !$this->hasRole('admin')) {
            $this->setError('Apenas administradores podem criar outros administradores.');
            return redirect()->back()->withInput();
        }

        $result = $this->registerWorkflowService->registerByManager(
            $postData,
            (int) ($this->currentUser->id ?? 0)
        );
        if (!($result['success'] ?? false)) {
            $this->setError((string) ($result['message'] ?? 'Erro ao cadastrar colaborador. Tente novamente.'));
            return redirect()->back()->withInput();
        }

        $this->setSuccess('Colaborador cadastrado com sucesso!');
        return redirect()->to(route_to('employees.show', (int) $result['employee_id']));
    }

    private function registrationViewData(): array
    {
        return [
            'formOptions' => $this->settingModel->loadSelectOptions(),
            'positionsEndpoint' => route_to('register.positions-by-department'),
            'loginUrl' => route_to('login'),
            'forgotPasswordUrl' => route_to('forgot-password'),
            'formAction' => route_to('register.store'),
            'selfRegistrationEnabled' => true,
        ];
    }
}
