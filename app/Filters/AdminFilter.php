<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use App\Services\AuthorizationService;
use App\Services\SessionAuthorizationContext;

/**
 * Admin Filter
 *
 * Verifies if user has admin role before accessing admin-only routes
 * Conforme Portaria MTE para controle de acesso a configurações avançadas
 */
class AdminFilter extends AuthFilter
{
    protected AuthorizationService $authorizationService;
    protected SessionAuthorizationContext $sessionContext;

    public function __construct()
    {
        $this->authorizationService = new AuthorizationService();
        $this->sessionContext = new SessionAuthorizationContext();
    }

    /**
     * Check if user is admin
     *
     * @param RequestInterface $request
     * @param array|null $arguments
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $guard = parent::before($request, $arguments);
        if ($guard !== null) {
            return $guard;
        }

        $session = session();

        if (! $this->sessionContext->isActive()) {
            return $this->deny($request, 403, 'Conta desativada.', sp_login_path(), 'Sua conta está desativada. Contate o administrador.');
        }

        $userRole = $this->sessionContext->getNormalizedRole();

        if (! $this->authorizationService->hasRole(['role' => $userRole], 'admin')) {
            $this->logUnauthorizedAccess((int) $session->get('user_id'), current_url());
            return $this->deny(
                $request,
                403,
                'Acesso negado. Permissão de administrador necessária.',
                $this->getDashboardUrl($userRole),
                'Você não tem permissão para acessar esta área. Acesso restrito a administradores.'
            );
        }

        return null;
    }

    protected function getDashboardUrl(string $role): string
    {
        return $this->sessionContext->getDashboardUrlForRole($role);
    }

    protected function logUnauthorizedAccess(int $userId, string $url): void
    {
        try {
            $auditModel = new \App\Models\AuditModel();

            $auditModel->log(
                $userId,
                'UNAUTHORIZED_ACCESS_ATTEMPT',
                'system',
                null,
                null,
                ['url' => $url, 'required_role' => 'admin'],
                "Tentativa de acesso não autorizado à área de administrador: {$url}",
                'warning'
            );
        } catch (\Exception $e) {
            log_message('error', 'Failed to log unauthorized access: ' . $e->getMessage());
        }
    }
}
