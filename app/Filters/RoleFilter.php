<?php

namespace App\Filters;

use App\Models\AuditModel;
use App\Services\AuthorizationService;
use App\Services\SessionAuthorizationContext;
use CodeIgniter\HTTP\RequestInterface;

/**
 * Generic RBAC filter for web routes.
 *
 * Usage examples:
 *   ['filter' => ['auth', 'role:admin,rh']]
 *   ['filter' => 'role:admin']
 *
 * This filter keeps AdminFilter/ManagerFilter backward compatible, but gives
 * new routes a single explicit matrix-based guard instead of ad-hoc role checks.
 */
class RoleFilter extends AuthFilter
{
    protected AuthorizationService $authorizationService;
    protected SessionAuthorizationContext $sessionContext;

    public function __construct()
    {
        $this->authorizationService = new AuthorizationService();
        $this->sessionContext = new SessionAuthorizationContext();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $guard = parent::before($request, $arguments);
        if ($guard !== null) {
            return $guard;
        }

        $roles = $this->normalizeArguments($arguments);
        if ($roles === []) {
            $this->logUnauthorizedAccess((int) session()->get('user_id'), current_url(), ['missing_role_argument']);
            return $this->deny($request, 403, 'Acesso negado. Permissão não configurada.', '/', 'Acesso negado por política de segurança.');
        }

        if (! $this->sessionContext->isActive()) {
            return $this->deny($request, 403, 'Conta desativada.', sp_login_path(), 'Sua conta está desativada. Contate o administrador.');
        }

        $userRole = $this->sessionContext->getNormalizedRole();
        if (! $this->authorizationService->hasRole(['role' => $userRole], $roles)) {
            $this->logUnauthorizedAccess((int) session()->get('user_id'), current_url(), $roles);
            return $this->deny(
                $request,
                403,
                'Acesso negado. Permissão insuficiente.',
                $this->sessionContext->getDashboardUrlForRole($userRole),
                'Você não tem permissão para acessar esta área.'
            );
        }

        return null;
    }

    /**
     * @param array<int, string>|null $arguments
     * @return list<string>
     */
    protected function normalizeArguments($arguments): array
    {
        if (! is_array($arguments)) {
            return [];
        }

        $roles = [];
        foreach ($arguments as $argument) {
            foreach (explode(',', (string) $argument) as $role) {
                $role = trim($role);
                if ($role !== '') {
                    $roles[] = $role;
                }
            }
        }

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $requiredRoles
     */
    protected function logUnauthorizedAccess(int $userId, string $url, array $requiredRoles): void
    {
        try {
            (new AuditModel())->log(
                $userId,
                'UNAUTHORIZED_ACCESS_ATTEMPT',
                'system',
                null,
                null,
                ['url' => $url, 'required_roles' => $requiredRoles],
                'Tentativa de acesso não autorizado bloqueada pelo RBAC: ' . $url,
                'warning'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Failed to log RBAC unauthorized access: ' . $e->getMessage());
        }
    }
}
