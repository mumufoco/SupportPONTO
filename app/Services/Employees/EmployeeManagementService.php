<?php

namespace App\Services\Employees;

use Config\Services;

use App\Models\EmployeeModel;
use App\Models\NotificationModel;
use App\Models\RoleModel;
use App\Models\SettingModel;
use App\Services\Employees\Management\EmployeeFormSupportService;
use App\Services\Employees\Management\EmployeePayloadBuilder;
use App\Services\Employees\Management\EmployeeRegistrationService;
use App\Services\Employees\Management\EmployeeStatusService;
use App\Services\Employees\Management\EmployeeValidationRulesProvider;
use App\Services\AuthorizationService;
use Config\Services as ServiceFactory;

class EmployeeManagementService
{
    protected EmployeeModel $employeeModel;
    private EmployeeFormSupportService $formSupport;
    private EmployeeRegistrationService $registrationService;
    private EmployeeStatusService $statusService;
    private AuthorizationService $authorizationService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?EmployeeFormSupportService $formSupport = null,
        ?EmployeeRegistrationService $registrationService = null,
        ?EmployeeStatusService $statusService = null,
        ?AuthorizationService $authorizationService = null,
    ) {
        $this->employeeModel       = $employeeModel ?? ServiceFactory::employeeModel();
        $this->formSupport         = $formSupport ?? $this->buildFormSupport();
        $this->registrationService = $registrationService ?? $this->buildRegistrationService($this->formSupport);
        $this->statusService       = $statusService ?? $this->buildStatusService();
        $this->authorizationService = $authorizationService ?? new AuthorizationService();
    }

    public function loadFormData(): array
    {
        return $this->formSupport->loadFormData();
    }

    public function listEmployees(?object $currentUser): array
    {
        // Administradores do sistema não são colaboradores — excluir da listagem
        $query = $this->employeeModel
            ->where('role !=', 'admin')
            ->orderBy('name', 'ASC');

        if ($this->authorizationService->hasRole($currentUser, 'gestor') && $currentUser !== null) {
            // Sem department_id no gestor, força zero resultados em vez de
            // cair para "sem filtro" (mostraria todo mundo para um gestor mal
            // configurado) -- mesmo raciocínio usado em ReportViewService.
            $query->where('department_id', ! empty($currentUser->department_id) ? (int) $currentUser->department_id : 0);
        }

        return $query->findAll();
    }

    public function findEmployee(int $id)
    {
        return $this->employeeModel->find($id);
    }

    public function canActorAccessEmployee(?object $currentUser, $employee): bool
    {
        return $this->authorizationService->canAccessEmployee($currentUser, $employee, false);
    }

    public function getPositionsByDepartment(?string $departmentId): array
    {
        return $this->formSupport->getPositionsByDepartment($departmentId);
    }

    public function validateAndCreate(array $postData): array
    {
        return $this->registrationService->validateAndCreate($postData);
    }

    public function validateAndUpdate(int $id, array $postData): array
    {
        return $this->registrationService->validateAndUpdate($id, $postData);
    }

    public function deleteEmployee(int $id): array
    {
        return $this->statusService->deleteEmployee($id);
    }

    public function setEmployeeActive(int $id, bool $active): array
    {
        return $this->statusService->setEmployeeActive($id, $active);
    }

    public function getPendingRegistrations(): array
    {
        return $this->statusService->getPendingRegistrations();
    }

    public function getTerminatedEmployees(): array
    {
        return $this->statusService->getTerminatedEmployees();
    }

    public function approveRegistration(int $id): array
    {
        return $this->statusService->approveRegistration($id);
    }

    public function rejectRegistration(int $id): array
    {
        return $this->statusService->rejectRegistration($id);
    }

    public function resolveRoleInput($roleInput): ?string
    {
        return $this->formSupport->resolveRoleInput($roleInput);
    }

    private function buildFormSupport(): EmployeeFormSupportService
    {
        // MELHORIA 5: SettingModel substitui ConfigurationService
        return new EmployeeFormSupportService(new SettingModel(), new RoleModel());
    }

    private function buildRegistrationService(EmployeeFormSupportService $formSupport): EmployeeRegistrationService
    {
        $rulesProvider = new EmployeeValidationRulesProvider();
        $payloadBuilder = new EmployeePayloadBuilder($this->employeeModel);

        return new EmployeeRegistrationService(
            $this->employeeModel,
            $rulesProvider,
            $payloadBuilder,
            $formSupport,
        );
    }

    private function buildStatusService(): EmployeeStatusService
    {
        return new EmployeeStatusService(
            $this->employeeModel,
            new NotificationModel(),
        );
    }
}
