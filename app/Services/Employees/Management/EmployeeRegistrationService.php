<?php

namespace App\Services\Employees\Management;

use App\Models\EmployeeModel;
use App\Services\Auth\RegisterPolicyService;
use App\Services\Auth\SessionSecurityService;

class EmployeeRegistrationService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel,
        private readonly EmployeeValidationRulesProvider $rulesProvider,
        private readonly EmployeePayloadBuilder $payloadBuilder,
        private readonly EmployeeFormSupportService $formSupport,
        private readonly RegisterPolicyService $registerPolicyService = new RegisterPolicyService(),
        private readonly SessionSecurityService $sessionSecurityService = new SessionSecurityService(),
    ) {
    }

    public function validateAndCreate(array $postData): array
    {
        $postData = $this->normalizeRegistrationPayload($postData);

        $normalizedRole = $this->formSupport->resolveRoleInput($postData['role'] ?? $postData['role_id'] ?? null);
        if ($normalizedRole === null) {
            return ['success' => false, 'error' => 'Nível de acesso inválido.'];
        }
        $postData['role'] = $normalizedRole;

        $validation = \Config\Services::validation();
        $validation->setRules($this->rulesProvider->createRules());

        if (!$validation->run($postData)) {
            return ['success' => false, 'errors' => $validation->getErrors()];
        }

        $payload = $this->payloadBuilder->build($postData, true);
        $validationErrors = $this->formSupport->validateBeforeSave($payload);
        if (!empty($validationErrors)) {
            return ['success' => false, 'error' => implode(' ', $validationErrors)];
        }

        $employeeId = $this->employeeModel->insert($payload);
        if (!$employeeId) {
            return ['success' => false, 'error' => 'Erro ao criar empregado.'];
        }

        $this->formSupport->clearCache();

        return [
            'success' => true,
            'employee_id' => (int) $employeeId,
            'data' => $payload,
        ];
    }

    public function validateAndUpdate(int $id, array $postData): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Empregado não encontrado.', 'code' => 'not_found'];
        }

        $postData = $this->normalizeRegistrationPayload($postData);

        $normalizedRole = $this->formSupport->resolveRoleInput($postData['role'] ?? $postData['role_id'] ?? null);
        if ($normalizedRole === null) {
            return ['success' => false, 'error' => 'Nível de acesso inválido.'];
        }
        $postData['role'] = $normalizedRole;

        $validation = \Config\Services::validation();
        $validation->setRules($this->rulesProvider->updateRules($id));

        if (!$validation->run($postData)) {
            return ['success' => false, 'errors' => $validation->getErrors()];
        }

        $payload = $this->payloadBuilder->build($postData, false);
        if (!empty($postData['password']) && strlen((string) $postData['password']) >= 8) {
            $payload['password'] = password_hash((string) $postData['password'], PASSWORD_ARGON2ID);
        }

        $updated = $this->employeeModel->update($id, $payload);
        if (!$updated) {
            return ['success' => false, 'error' => 'Erro ao atualizar empregado.'];
        }

        $oldRole = is_array($employee) ? ($employee['role'] ?? null) : ($employee->role ?? null);
        $oldActive = is_array($employee) ? ($employee['active'] ?? null) : ($employee->active ?? null);
        $newActive = array_key_exists('active', $payload) ? $payload['active'] : $oldActive;

        // ALTO-02 (auditoria): trocar o papel (role) de um funcionário ou desativá-lo por
        // esta tela de edição não revogava a sessão já aberta — quem estivesse logado
        // mantinha os privilégios do papel antigo até a sessão expirar naturalmente.
        $roleChanged = $oldRole !== null && $payload['role'] !== $oldRole;
        $becameInactive = $oldActive && !$newActive;
        if ($roleChanged || $becameInactive) {
            $reason = $roleChanged ? 'Papel (role) do funcionário alterado' : 'Funcionário desativado';
            $this->sessionSecurityService->revokeAllSessionsForUser($id, $reason);
        }

        return [
            'success' => true,
            'data' => $payload,
            'old_values' => [
                'name' => is_array($employee) ? ($employee['name'] ?? null) : ($employee->name ?? null),
                'email' => is_array($employee) ? ($employee['email'] ?? null) : ($employee->email ?? null),
                'role' => $oldRole,
                'active' => $oldActive,
            ],
        ];
    }

    private function normalizeRegistrationPayload(array $postData): array
    {
        $postData = $this->registerPolicyService->normalizePostData($postData);
        $postData = $this->formSupport->normalizeCatalogFields($postData);

        if (!empty($postData['phone']) && empty($postData['telefone'])) {
            $postData['telefone'] = $postData['phone'];
        }

        if (!empty($postData['telefone']) && empty($postData['phone'])) {
            $postData['phone'] = $postData['telefone'];
        }

        return $postData;
    }
}
