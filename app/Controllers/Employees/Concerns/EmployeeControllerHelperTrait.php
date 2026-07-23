<?php

namespace App\Controllers\Employees\Concerns;

trait EmployeeControllerHelperTrait
{
    private function redirectBackWithValidationErrors()
    {
        return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
    }
    private function resolveManagedEmployeeForView(int $id): array
    {
        return $this->resolveManagerEmployeeAccess($id);
    }
    private function resolveManagedEmployeeForJson(int $id): array
    {
        $employeeResult = $this->employeeControllerActionService->resolveManagerAccess($this->currentUser, $id);
        if (!($employeeResult['success'] ?? false)) {
            $employeeResult['response'] = $this->response
                ->setStatusCode((int) ($employeeResult['status'] ?? 404))
                ->setJSON(['error' => $employeeResult['message'] ?? 'Colaborador não encontrado']);
        }

        return $employeeResult;
    }
    private function resolveManagerEmployeeAccess(int $id): array
    {
        $access = $this->employeeControllerActionService->resolveManagerAccess($this->currentUser, $id);
        if (!($access['success'] ?? false)) {
            $this->setError($access['message'] ?? 'Colaborador não encontrado.');
            $access['redirect'] = redirect()->to(sp_employees_index_url());
        }

        return $access;
    }
    private function redirectIfGuest()
    {
        if (!$this->currentUser) {
            return redirect()->to(route_to('login'));
        }

        return null;
    }

}
