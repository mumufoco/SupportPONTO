<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\Auth\PasswordLifecycleService;
use App\Services\Auth\RegisterPolicyService;
use App\Services\Auth\WebAuthService;
use App\Services\Security\RateLimitService;
use App\Services\Security\TurnstileService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Config\Services;
use App\Services\Dashboard\DashboardCoordinatorService;

class LoginController extends BaseController
{
    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;
    protected RateLimitService $rateLimitService;
    protected WebAuthService $webAuthService;
    protected PasswordLifecycleService $passwordLifecycleService;
    protected RegisterPolicyService $registerPolicyService;
    protected TurnstileService $turnstileService;

    protected const GENERIC_LOGIN_ERROR = 'Não foi possível entrar. Revise seu e-mail e senha e tente novamente.';

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->employeeModel = Services::employeeModel();
        $this->auditModel = Services::auditModel();
        $this->rateLimitService = Services::rateLimitService();
        $this->webAuthService = Services::webAuthService();
        $this->passwordLifecycleService = Services::passwordLifecycleService();
        $this->registerPolicyService = new RegisterPolicyService();
        $this->turnstileService = Services::turnstileService();
    }

    public function index()
    {
        if (! $this->isAuthenticated()) {
            $this->webAuthService->restoreFromRememberMe($this->session, $this->request);
        }

        if ($this->isAuthenticated()) {
            if ($this->session->get('2fa_pending_user_id')) {
                return redirect()->to(site_url('auth/2fa/verify'));
            }

            $role = (string) $this->session->get('user_role');
            return redirect()->to($this->getRoleBasedRedirect($role));
        }

        return view('auth/login', [
            'selfRegistrationEnabled' => $this->registerPolicyService->selfRegistrationEnabled(),
            'rememberMeEnabled' => $this->webAuthService->isRememberMeEnabled(),
        ]);
    }

    public function authenticate()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $turnstileToken = (string) $this->request->getPost('cf-turnstile-response');
        if (! $this->turnstileService->verify($turnstileToken, $this->getClientIp())) {
            $this->setError('Falha na verificação de segurança. Tente novamente.');
            return redirect()->back()->withInput();
        }

        $email = mb_strtolower(trim((string) $this->request->getPost('email')));
        $password = (string) $this->request->getPost('password');
        $rememberValue = $this->request->getPost('remember');
        $remember = in_array((string) $rememberValue, ['1', 'true', 'on', 'yes'], true);

        $result = $this->webAuthService->authenticate($email, $password, $remember, $this->session, $this->request);
        if (! ($result['success'] ?? false)) {
            // WebAuthService ja distingue "credenciais invalidas" de "bloqueado
            // por rate limit" com mensagens proprias e seguras (nenhuma delas
            // revela se o e-mail existe) -- antes disso o controller ignorava
            // $result['message'] e sempre mostrava o erro generico de senha,
            // fazendo um colaborador bloqueado por rate limit achar que tinha
            // digitado a senha errada.
            $this->setError((string) ($result['message'] ?? self::GENERIC_LOGIN_ERROR));
            return redirect()->back()->withInput();
        }

        $user = $result['user'];

        if ($this->requiresInitialPasswordChange($user)) {
            $this->setSuccess("Bem-vindo(a), {$user->name}! Antes de continuar, defina uma nova senha.");
            return redirect()->to(site_url('auth/first-access-password'));
        }

        if ((bool) ($result['must_setup_2fa'] ?? false)) {
            $this->setSuccess("Bem-vindo(a), {$user->name}! A política de segurança exige 2FA para o seu perfil. Configure agora para continuar.");
            return redirect()->to(site_url('auth/2fa/setup'));
        }

        if ((bool) ($result['requires_2fa'] ?? false)) {
            $this->setSuccess('Credenciais confirmadas. Conclua a autenticação em duas etapas para entrar.');
            return redirect()->to(site_url('auth/2fa/verify'));
        }

        $this->setSuccess("Bem-vindo(a), {$user->name}!");

        return redirect()->to($this->getRoleBasedRedirect((string) $user->role));
    }

    public function firstAccessPassword()
    {
        if (! $this->isAuthenticated()) {
            return redirect()->to(sp_login_url());
        }

        if (! $this->session->get('must_change_password')) {
            return redirect()->to($this->getRoleBasedRedirect((string) $this->session->get('user_role')));
        }

        return view('auth/first_access_password');
    }

    public function updateFirstAccessPassword()
    {
        if (! $this->isAuthenticated()) {
            return redirect()->to(sp_login_url());
        }

        if (! $this->session->get('must_change_password')) {
            return redirect()->to($this->getRoleBasedRedirect((string) $this->session->get('user_role')));
        }

        $rules = [
            'password' => 'required|strong_password|matches[confirm_password]',
            'confirm_password' => 'required',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $userId = (int) $this->session->get('user_id');
        $newPassword = (string) $this->request->getPost('password');

        $this->passwordLifecycleService->updatePassword($userId, $newPassword, [
            'clear_reset_tokens' => true,
            'clear_remember_tokens' => true,
            'audit_action' => 'FIRST_ACCESS_PASSWORD_CHANGED',
            'audit_new_values' => ['must_change_password' => false],
            'audit_description' => 'Senha temporária substituída no primeiro acesso',
            'audit_level' => 'info',
        ]);

        $employee = $this->employeeModel->find($userId);
        $this->session->set([
            'must_change_password' => false,
            'employee' => $employee ? (array) $employee : $this->session->get('employee'),
        ]);

        return redirect()->to($this->getRoleBasedRedirect((string) $this->session->get('user_role')))
            ->with('success', 'Senha definida com sucesso. Seu acesso foi liberado.');
    }

    protected function getRoleBasedRedirect(string $role): string
    {
        return (new DashboardCoordinatorService())->routeByRole($role);
    }

    protected function requiresInitialPasswordChange(object $user): bool
    {
        return in_array($user->must_change_password ?? null, [true, 't', '1', 1], true);
    }
}