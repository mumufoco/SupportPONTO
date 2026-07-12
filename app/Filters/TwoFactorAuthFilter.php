<?php

namespace App\Filters;

use App\Models\EmployeeModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class TwoFactorAuthFilter implements FilterInterface
{
    protected array $allowedRoutes = [
        'auth/login',
        'auth/logout',
        'auth/forgot-password',
        'auth/reset-password',
        'auth/first-access-password',
        'auth/2fa/verify',
        'auth/2fa/setup',
        'auth/2fa/enable',
        'auth/2fa/manage',
        'auth/2fa/disable',
        'auth/2fa/backup-codes',
        'auth/2fa/regenerate-backup-codes',
    ];

    protected array $allowedApiPrefixes = ['api'];

    public function before(RequestInterface $request, $arguments = null)
    {
        $path = trim($request->getUri()->getPath(), '/');
        if ($this->isAllowedRoute($path) || $this->isAllowedApiPath($path)) {
            return null;
        }

        $session = session();
        $userId = $session->get('user_id');
        if (! is_numeric($userId)) {
            return null;
        }

        $pendingUserId = $session->get('2fa_pending_user_id');
        if (is_numeric($pendingUserId)) {
            return $this->deny($request, 428, 'Autenticação em duas etapas pendente.', site_url('auth/2fa/verify'));
        }

        $employee = (new EmployeeModel())->find((int) $userId);
        if ($employee === null) {
            $session->destroy();
            return $this->deny($request, 401, 'Sessão inválida.', sp_login_url(), 'Sessão inválida. Faça login novamente.');
        }

        if (! filter_var($employee->two_factor_enabled ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        if ((bool) $session->get('2fa_verified')) {
            return null;
        }

        $session->set([
            '2fa_pending_user_id' => (int) $userId,
            '2fa_pending_redirect' => current_url(),
            '2fa_remember_me' => (bool) $session->get('remember_me'),
        ]);

        return $this->deny($request, 428, 'Autenticação em duas etapas pendente.', site_url('auth/2fa/verify'), 'Por favor, conclua a autenticação em duas etapas para continuar.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    protected function isAllowedRoute(string $path): bool
    {
        foreach ($this->allowedRoutes as $route) {
            if ($path === trim($route, '/') || str_starts_with($path, trim($route, '/') . '/')) {
                return true;
            }
        }
        return false;
    }

    protected function isAllowedApiPath(string $path): bool
    {
        foreach ($this->allowedApiPrefixes as $prefix) {
            $prefix = trim($prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }
        return false;
    }

    protected function isApiRequest(RequestInterface $request): bool
    {
        $path = trim((string) $request->getUri()->getPath(), '/');
        if ($path === 'api' || str_starts_with($path, 'api/')) {
            return true;
        }
        $accept = strtolower($request->getHeaderLine('Accept'));
        if (str_contains($accept, 'application/json') || str_contains($accept, 'application/vnd.supportponto')) {
            return true;
        }
        return str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json');
    }

    protected function deny(RequestInterface $request, int $statusCode, string $apiError, string $redirectPath, ?string $flashMessage = null)
    {
        if ($request->isAJAX() || $this->isApiRequest($request)) {
            return Services::response()
                ->setJSON(['success' => false, 'error' => $apiError, 'requires_2fa' => true, 'redirect' => $redirectPath])
                ->setStatusCode($statusCode);
        }
        if ($flashMessage !== null) {
            session()->setFlashdata('info', $flashMessage);
        }
        return redirect()->to($redirectPath);
    }
}
