<?php

namespace App\Services\Notification\LegacyCore;

use App\Enums\Role;
use App\Models\EmployeeModel;

class NotificationRoutingService
{
    public function __construct(private readonly EmployeeModel $employeeModel)
    {
    }

    public function adminIds(): array
    {
        $admins = $this->employeeModel
            ->where('role', 'admin')
            ->where('active', true)
            ->findAll();

        return $this->extractIds($admins);
    }

    public function managerIds(): array
    {
        $managers = $this->employeeModel
            ->whereIn('role', [Role::Admin->value, Role::Gestor->value, Role::RH->value])
            ->where('active', true)
            ->findAll();

        return $this->extractIds($managers);
    }

    public function departmentIds(string $department): array
    {
        $employees = $this->employeeModel
            ->where('department', $department)
            ->where('active', true)
            ->findAll();

        return $this->extractIds($employees);
    }

    private function extractIds(array $employees): array
    {
        return array_values(array_filter(array_map(static fn ($employee) => isset($employee->id) ? (int) $employee->id : null, $employees)));
    }
}
