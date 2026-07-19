<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Services\Auth\TwoFactorManagerService;
use App\Services\Security\TwoFactorAuthService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;

class TwoFactorAuthController extends BaseController
{
    protected TwoFactorAuthService $twoFactorService;
    protected TwoFactorManagerService $twoFactorManager;
    protected EmployeeModel $employeeModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->twoFactorService = Services::twoFactorAuthService();
        $this->twoFactorManager = Services::twoFactorManagerService();
        $this->employeeModel = Services::employeeModel();
    }

    public function setup()
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(route_to('login'))->with('error', 'Sessão expirada.');
        }
        if ((bool) ($employee->two_factor_enabled ?? false)) {
            return redirect()->to(route_to('admin.settings.two-factor'))->with('info', '2FA já está habilitado.');
        }
        try {
            $setupPayload = $this->twoFactorManager->buildSetupPayload($employee);
        } catch (\RuntimeException $e) {
            log_message('error', '2FA setup unavailable: {message}', ['message' => $e->getMessage()]);
            return redirect()->to(route_to('admin.settings.two-factor'))->with('error', 'Não foi possível preparar o QR Code do 2FA. Instale as dependências e tente novamente.');
        }
        session()->set('2fa_setup_secret', $setupPayload['secret']);
        return view('auth/2fa/setup', $setupPayload + ['employee' => $employee]);
    }

    public function enable()
    {
        $employee = $this->requireAuthenticatedEmployee();
        $secret = (string) session()->get('2fa_setup_secret');
        if ($employee === null || $secret === '') {
            return redirect()->to(route_to('2fa.setup'))->with('error', 'Sessão expirada. Inicie a configuração novamente.');
        }
        $code = trim((string) $this->request->getPost('code'));
        if ($code === '') {
            return redirect()->back()->with('error', 'Por favor, digite o código de verificação.');
        }
        if (! $this->twoFactorService->verifyCode($secret, $code)) {
            return redirect()->back()->with('error', 'Código inválido. Por favor, tente novamente.');
        }
        $backupCodes = $this->twoFactorManager->enableForEmployee((int) $employee->id, $secret);
        session()->remove('2fa_setup_secret');
        session()->setFlashdata('backup_codes', $backupCodes);
        session()->set('2fa_verified', true);
        log_message('info', "2FA enabled for employee ID: {$employee->id}");
        return redirect()->to(route_to('2fa.backup-codes'))->with('success', '2FA habilitado com sucesso!');
    }

    public function showBackupCodes()
    {
        $backupCodes = session()->getFlashdata('backup_codes');
        if (! is_array($backupCodes) || $backupCodes === []) {
            return redirect()->to(route_to('admin.settings.two-factor'))->with('error', 'Códigos de backup não disponíveis.');
        }
        return view('auth/2fa/backup_codes', ['backup_codes' => $backupCodes]);
    }

    public function verify()
    {
        $employeeId = session()->get('2fa_pending_user_id');
        if (! is_numeric($employeeId)) {
            return redirect()->to(route_to('login'))->with('error', 'Sessão expirada. Faça login novamente.');
        }
        $employee = $this->employeeModel->find((int) $employeeId);
        if (! $employee || ! (bool) ($employee->two_factor_enabled ?? false)) {
            session()->remove(['2fa_pending_user_id', '2fa_pending_redirect']);
            return redirect()->to(route_to('login'))->with('error', 'Configuração de 2FA inválida.');
        }
        if ($this->request->getMethod() !== 'post') {
            return view('auth/2fa/verify', ['employee' => $employee]);
        }
        $code = trim((string) $this->request->getPost('code'));
        $useBackupCode = in_array((string) $this->request->getPost('use_backup_code'), ['1', 'true', 'on', 'yes'], true);
        if ($code === '') {
            return redirect()->back()->with('error', 'Por favor, digite o código de verificação.');
        }
        $verified = $useBackupCode
            ? $this->twoFactorManager->verifyAndConsumeBackupCode($employee, $code)
            : $this->twoFactorManager->verifyTotpCode($employee, $code);
        if (! $verified) {
            log_message('warning', "Failed 2FA verification for employee ID: {$employeeId}");
            return redirect()->back()->with('error', 'Código inválido. Por favor, tente novamente.');
        }
        session()->remove(['2fa_pending_user_id']);
        session()->set(['user_id' => (int) $employeeId, '2fa_verified' => true]);
        log_message('info', "2FA verified for employee ID: {$employeeId}");
        $redirectTo = session()->get('redirect_url') ?: session()->get('2fa_pending_redirect') ?: route_to('dashboard');
        session()->remove(['redirect_url', '2fa_pending_redirect']);
        return redirect()->to($redirectTo)->with('success', 'Login realizado com sucesso!');
    }

    /**
     * Pagina auth/2fa/manage eliminada -- gerenciamento do 2FA (status,
     * desabilitar, gerar novos codigos de backup) agora vive em
     * admin/settings/two-factor. Rota mantida apenas como redirecionamento
     * para nao quebrar links/favoritos antigos.
     */
    public function manage()
    {
        return redirect()->to(route_to('admin.settings.two-factor'));
    }

    public function disable()
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(route_to('login'))->with('error', 'Sessão expirada.');
        }
        if (! $this->requestHasValidPassword($employee)) {
            return redirect()->back()->with('error', 'Senha incorreta.');
        }
        $this->twoFactorManager->disableForEmployee((int) $employee->id);
        session()->remove('2fa_verified');
        log_message('info', "2FA disabled for employee ID: {$employee->id}");
        return redirect()->to(route_to('dashboard'))->with('success', '2FA desabilitado com sucesso.');
    }

    public function regenerateBackupCodes()
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee === null) {
            return redirect()->to(route_to('login'))->with('error', 'Sessão expirada.');
        }
        if (! (bool) ($employee->two_factor_enabled ?? false)) {
            return redirect()->to(route_to('dashboard'))->with('error', '2FA não está habilitado.');
        }
        if (! $this->requestHasValidPassword($employee)) {
            return redirect()->back()->with('error', 'Senha incorreta.');
        }
        $backupCodes = $this->twoFactorManager->regenerateBackupCodes((int) $employee->id);
        session()->setFlashdata('backup_codes', $backupCodes);
        log_message('info', "Backup codes regenerated for employee ID: {$employee->id}");
        return redirect()->to(route_to('2fa.backup-codes'))->with('success', 'Novos códigos de backup gerados!');
    }

    protected function requireAuthenticatedEmployee(): ?object
    {
        $employeeId = session()->get('user_id');
        if (! is_numeric($employeeId)) {
            return null;
        }
        return $this->employeeModel->find((int) $employeeId);
    }

    protected function requestHasValidPassword(object $employee): bool
    {
        $password = (string) $this->request->getPost('password');
        if ($password === '') {
            return false;
        }
        return password_verify($password, (string) $employee->password);
    }
}
