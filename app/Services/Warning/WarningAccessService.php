<?php

namespace App\Services\Warning;

use App\Models\EmployeeModel;

class WarningAccessService
{
    public function __construct(private readonly EmployeeModel $employeeModel)
    {
    }

    public function getAuthenticatedEmployee(): ?array
    {
        helper('session_context');
        $userId = sp_session_user_id();

        if ($userId === null) {
            return null;
        }

        $employee = $this->employeeModel->find($userId);
        if (!$employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'department' => $employee->department,
            'department_id' => $employee->department_id ?? null,
        ];
    }

    public function canManageWarnings(array $employee): bool
    {
        return in_array($employee['role'], ['admin', 'gestor', 'rh'], true);
    }

    public function isAdmin(array $employee): bool
    {
        return $employee['role'] === 'admin';
    }

    public function isOwner(array $employee, object $warning): bool
    {
        return (int) $warning->employee_id === (int) $employee['id'];
    }

    public function canManageEmployee(array $actor, int $targetEmployeeId): bool
    {
        if ($actor['role'] === 'admin' || $actor['role'] === 'rh') {
            return true;
        }

        if ($actor['role'] !== 'gestor') {
            return false;
        }

        $target = $this->employeeModel->find($targetEmployeeId);
        return $target !== null
            && ! empty($actor['department_id'])
            && (int) ($target->department_id ?? 0) === (int) $actor['department_id'];
    }

    public function canViewWarning(array $actor, object $warning): bool
    {
        if ($actor['role'] === 'funcionario') {
            return $this->isOwner($actor, $warning);
        }

        if ($actor['role'] === 'gestor') {
            $warningEmployee = $this->employeeModel->find($warning->employee_id);
            return $warningEmployee !== null
                && ! empty($actor['department_id'])
                && (int) ($warningEmployee->department_id ?? 0) === (int) $actor['department_id'];
        }

        return true;
    }

    public function canViewEmployeeDashboard(array $actor, ?object $targetEmployee): bool
    {
        if ($targetEmployee === null) {
            return false;
        }
        if ($actor['role'] === 'funcionario') {
            return (int) $actor['id'] === (int) $targetEmployee->id;
        }

        if ($actor['role'] === 'gestor') {
            return ! empty($actor['department_id'])
                && (int) ($targetEmployee->department_id ?? 0) === (int) $actor['department_id'];
        }

        if (in_array($actor['role'], ['admin', 'rh'], true)) {
            return true;
        }

        return false;
    }

    public function managedEmployeeIds(array $actor): ?array
    {
        if ($actor['role'] !== 'gestor') {
            return null;
        }

        $ids = $this->employeeModel
            ->where('department_id', ! empty($actor['department_id']) ? (int) $actor['department_id'] : 0)
            ->findColumn('id');

        return array_map('intval', $ids ?? []);
    }
}
