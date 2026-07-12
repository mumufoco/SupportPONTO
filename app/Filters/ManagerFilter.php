<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use App\Services\AuthorizationService;
use App\Services\SessionAuthorizationContext;

/**
 * Manager Filter
 *
 * Verifies if user has manager or admin role before accessing management routes
 * Conforme Portaria MTE para controle de acesso a funcionalidades de empregados
 */
class ManagerFilter extends AuthFilter
{
    protected AuthorizationService $authorizationService;
    protected SessionAuthorizationContext $sessionContext;

    public function __construct()
    {
        $this->authorizationService = new AuthorizationService();
        $this->sessionContext = new SessionAuthorizationContext();
    }

    /**
     * Check if user is manager or admin
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
        $uri = $request->getUri()->getPath();

        if (! $this->sessionContext->isActive()) {
            return $this->deny(
                $request,
                403,
                'Conta desativada.',
                sp_login_path(),
                'Sua conta está desativada. Contate o administrador.'
            );
        }

        $userRole = $this->sessionContext->getNormalizedRole();

        if (! $this->authorizationService->canAccessManagerArea(['role' => $userRole])) {
            $this->logUnauthorizedAccess((int) $session->get('user_id'), $uri);
            return $this->deny(
                $request,
                403,
                'Acesso negado. Permissão de gestor necessária.',
                $this->sessionContext->getDashboardUrlForRole($userRole),
                'Você não tem permissão para acessar esta área. Acesso restrito a gestores e administradores.'
            );
        }

        return null; // Allow request to proceed
    }

    /**
     * Log unauthorized access attempt
     *
     * @param int $userId
     * @param string $url
     * @return void
     */
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
                ['url' => $url, 'required_role' => 'gestor/rh'],
                "Tentativa de acesso não autorizado à área de gestão: {$url}",
                'warning'
            );
        } catch (\Exception $e) {
            log_message('error', 'Failed to log unauthorized access: ' . $e->getMessage());
        }
    }
}
