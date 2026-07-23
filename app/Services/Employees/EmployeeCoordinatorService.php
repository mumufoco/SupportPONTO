<?php

namespace App\Services\Employees;

use Config\Services as ServiceFactory;

class EmployeeCoordinatorService
{
    public function __construct(
        ?EmployeeManagementService $managementService = null,
        ?EmployeeInsightsService $insightsService = null,
        ?EmployeeAccountService $accountService = null,
    ) {
        $this->managementService = $managementService ?? ServiceFactory::employeeManagementService();
        $this->insightsService = $insightsService ?? ServiceFactory::employeeInsightsService();
        $this->accountService = $accountService ?? ServiceFactory::employeeAccountService();
    }

    private readonly EmployeeManagementService $managementService;
    private readonly EmployeeInsightsService $insightsService;
    private readonly EmployeeAccountService $accountService;

    public function listEmployees(?object $currentUser): array
    {
        return $this->managementService->listEmployees($currentUser);
    }

    public function createFormData(): array
    {
        return $this->managementService->loadFormData();
    }

    public function findEmployeeForManagerAccess(?object $currentUser, int $employeeId): array
    {
        $employee = $this->managementService->findEmployee($employeeId);

        if (!$employee) {
            return ['success' => false, 'message' => 'Colaborador não encontrado.', 'status' => 404];
        }

        if (!$this->managementService->canActorAccessEmployee($currentUser, $employee)) {
            return ['success' => false, 'message' => 'Você não tem permissão para acessar este colaborador.', 'status' => 403];
        }

        return ['success' => true, 'employee' => $employee];
    }

    public function employeeOverview(int $employeeId): array
    {
        $overview = $this->insightsService->getEmployeeOverview($employeeId);

        if ($overview === null) {
            return ['success' => false, 'message' => 'Colaborador não encontrado.', 'status' => 404];
        }

        return ['success' => true, 'overview' => $overview];
    }

    public function createEmployee(array $payload): array
    {
        return $this->managementService->validateAndCreate($payload);
    }

    public function updateEmployee(int $employeeId, array $payload): array
    {
        return $this->managementService->validateAndUpdate($employeeId, $payload);
    }

    public function employeeIsActive(mixed $employee): bool
    {
        return (bool) (is_array($employee) ? ($employee['active'] ?? false) : ($employee->active ?? false));
    }

    public function getPositionsByDepartment(?string $departmentId): array
    {
        return $this->managementService->getPositionsByDepartment($departmentId);
    }

    public function deleteEmployee(int $employeeId): array
    {
        return $this->managementService->deleteEmployee($employeeId);
    }

    public function setEmployeeActive(int $employeeId, bool $active): array
    {
        return $this->managementService->setEmployeeActive($employeeId, $active);
    }

    public function pendingRegistrations(): array
    {
        return $this->managementService->getPendingRegistrations();
    }

    public function terminatedEmployees(): array
    {
        return $this->managementService->getTerminatedEmployees();
    }

    public function approveRegistration(int $employeeId): array
    {
        return $this->managementService->approveRegistration($employeeId);
    }

    public function rejectRegistration(int $employeeId): array
    {
        return $this->managementService->rejectRegistration($employeeId);
    }

    public function ownProfileData(int $employeeId): array
    {
        return $this->insightsService->getOwnProfileData($employeeId);
    }

    public function updateProfile(int $employeeId, array $payload): array
    {
        return $this->accountService->updateProfile($employeeId, $payload);
    }

    public function exportEmployeeData(int $employeeId): ?array
    {
        return $this->insightsService->exportEmployeeData($employeeId);
    }

    public function biometricPageData(int $employeeId): ?array
    {
        return $this->insightsService->getBiometricPageData($employeeId);
    }

    public function grantBiometricConsent(int $employeeId, string $ipAddress): array
    {
        return $this->accountService->grantBiometricConsent($employeeId, $ipAddress);
    }

    public function revokeBiometricConsent(int $employeeId): array
    {
        return $this->accountService->revokeBiometricConsent($employeeId);
    }

    public function changePassword(object $currentUser, string $currentPassword, string $newPassword): array
    {
        return $this->accountService->changePassword($currentUser, $currentPassword, $newPassword);
    }
}
