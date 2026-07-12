<?php

namespace App\Filters;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Services\AuthorizationService;
use App\Traits\ObservabilityTrait;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Generic RBAC filter for OAuth2 API routes.
 * Must be applied after oauth2 so ApiRequestAuthContext is available.
 */
class ApiRoleFilter implements FilterInterface
{
    use ObservabilityTrait;

    protected AuthorizationService $authorizationService;
    protected EmployeeModel $employeeModel;

    public function __construct()
    {
        $this->authorizationService = new AuthorizationService();
        $this->employeeModel = new EmployeeModel();
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        $roles = $this->normalizeArguments($arguments);
        if ($roles === []) {
            return $this->forbiddenResponse('Política de acesso da API não configurada.');
        }

        $employeeId = Services::apiRequestAuthContext()->getEmployeeId();
        if ($employeeId === null) {
            return $this->unauthorizedResponse('Token de acesso ausente, inválido ou expirado.');
        }

        $employee = $this->employeeModel->find($employeeId);
        if (! $employee || ! (bool) ($employee->active ?? false)) {
            return $this->unauthorizedResponse('Usuário da API inativo ou inexistente.');
        }

        if (! $this->authorizationService->hasRole($employee, $roles)) {
            $this->logUnauthorizedAccess((int) $employeeId, trim($request->getUri()->getPath(), '/'), $roles);
            return $this->forbiddenResponse('Acesso negado. Permissão insuficiente para este endpoint.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
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
    protected function logUnauthorizedAccess(int $employeeId, string $path, array $requiredRoles): void
    {
        try {
            (new AuditModel())->log(
                $employeeId,
                'API_UNAUTHORIZED_ACCESS_ATTEMPT',
                'api',
                null,
                null,
                ['path' => $path, 'required_roles' => $requiredRoles],
                'Tentativa de acesso não autorizado à API bloqueada pelo RBAC: ' . $path,
                'warning'
            );
        } catch (\Throwable $e) {
            log_message('error', 'Failed to log API RBAC unauthorized access: ' . $e->getMessage());
        }
    }

    protected function unauthorizedResponse(string $message): ResponseInterface
    {
        return $this->attachResponseContext(
            service('response')
                ->setStatusCode(401, 'Unauthorized')
                ->setJSON($this->apiErrorPayload('unauthorized', $message)),
            true
        );
    }

    protected function forbiddenResponse(string $message): ResponseInterface
    {
        return $this->attachResponseContext(
            service('response')
                ->setStatusCode(403, 'Forbidden')
                ->setJSON($this->apiErrorPayload('forbidden', $message)),
            true
        );
    }
}
