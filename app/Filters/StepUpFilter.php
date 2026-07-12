<?php

declare(strict_types=1);

namespace App\Filters;

use App\Services\Auth\SessionSecurityService;
use CodeIgniter\HTTP\RequestInterface;
use Config\Services;

class StepUpFilter extends AuthFilter
{
    protected SessionSecurityService $sessionSecurityService;

    public function __construct()
    {
        $this->sessionSecurityService = Services::sessionSecurityService();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $guard = parent::before($request, $arguments);
        if ($guard !== null) {
            return $guard;
        }

        $session = session();
        if ($this->sessionSecurityService->hasFreshStepUp($session)) {
            return null;
        }

        $userId = (int) ($session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return $this->deny($request, 401, 'Não autenticado.', sp_login_path(), 'Você precisa estar logado para continuar.');
        }

        $employee = Services::employeeModel()->find($userId);
        if (! is_object($employee) || ! (bool) ($employee->active ?? false)) {
            $session->destroy();
            return $this->deny($request, 403, 'Conta inválida.', sp_login_path(), 'Sua conta não está disponível para esta operação.');
        }

        $guardResult = $this->sessionSecurityService->ensureCriticalActionAllowed($employee, $request, $session);
        if (($guardResult['success'] ?? false) === true) {
            return null;
        }

        $hasPassword = trim((string) ($request->getPost('current_password') ?? $request->getHeaderLine('X-Current-Password'))) !== '';
        $statusCode = $hasPassword ? 422 : 428;
        $message = (string) ($guardResult['message'] ?? 'Confirme sua senha para concluir a ação crítica.');
        $redirectTarget = $this->resolveRedirectPath($arguments);

        $this->logStepUpDenied($userId, $request, $statusCode, $hasPassword, $message);

        if ($this->expectsJsonResponse($request)) {
            return service('response')
                ->setJSON([
                    'success' => false,
                    'message' => $message,
                    'error' => $hasPassword ? 'critical_action_confirmation_failed' : 'critical_action_confirmation_required',
                    'requires_confirmation' => true,
                    'step_up_required' => true,
                ])
                ->setStatusCode($statusCode);
        }

        session()->setFlashdata('error', $message);
        session()->set('redirect_url', current_url());

        return redirect()->to($redirectTarget);
    }

    /**
     * @param array<int, string>|null $arguments
     */
    private function resolveRedirectPath(?array $arguments): string
    {
        $target = strtolower(trim((string) ($arguments[0] ?? 'back')));

        return match ($target) {
            'profile.security', 'security', 'profile-security' => site_url(route_to('profile.security')),
            'login', 'auth.login' => sp_login_url(),
            default => previous_url() ?: site_url(route_to('profile.security')),
        };
    }

    private function logStepUpDenied(int $userId, RequestInterface $request, int $statusCode, bool $hasPassword, string $message): void
    {
        try {
            Services::auditModel()->log(
                $userId,
                $hasPassword ? 'CRITICAL_ACTION_CONFIRMATION_FAILED' : 'CRITICAL_ACTION_CONFIRMATION_REQUIRED',
                'system',
                $userId,
                null,
                [
                    'url' => current_url(),
                    'method' => $request->getMethod(),
                    'status_code' => $statusCode,
                    'confirmation_provided' => $hasPassword,
                ],
                $message,
                'warning'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Failed to log step-up denial: ' . $e->getMessage());
        }
    }
}
