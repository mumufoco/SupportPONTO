<?php

namespace App\Services\Employees\Management;

use App\Models\EmployeeModel;
use App\Models\NotificationModel;

class EmployeeStatusService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel,
        private readonly NotificationModel $notificationModel,
    ) {
    }

    public function deleteEmployee(int $id): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        if (!$this->employeeModel->delete($id)) {
            return ['success' => false, 'error' => 'Erro ao excluir funcionário.', 'status' => 500];
        }

        return ['success' => true, 'employee' => $employee];
    }

    public function setEmployeeActive(int $id, bool $active): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        $this->employeeModel->update($id, ['active' => $active]);

        return ['success' => true, 'employee' => $employee, 'active' => $active];
    }

    public function getPendingRegistrations(): array
    {
        // Truly pending: inactive and no demission_date = new registration awaiting approval
        return $this->employeeModel
            ->where('active', false)
            ->where('demission_date IS NULL', null, false)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    public function getTerminatedEmployees(): array
    {
        // Terminated: inactive and demission_date is set (admin deactivated)
        return $this->employeeModel
            ->where('active', false)
            ->where('demission_date IS NOT NULL', null, false)
            ->orderBy('demission_date', 'DESC')
            ->findAll();
    }

    public function approveRegistration(int $id): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        $this->employeeModel->update($id, ['active' => true]);

        $employeeId = is_array($employee) ? (int) ($employee['id'] ?? 0) : (int) ($employee->id ?? 0);

        $this->notificationModel->insert([
            'employee_id' => $employeeId,
            'title' => 'Cadastro aprovado',
            'message' => 'Seu cadastro foi aprovado. Você já pode acessar o sistema.',
            'type' => 'employee_registration',
            'read' => false,
        ]);

        return ['success' => true, 'employee' => $employee];
    }

    public function rejectRegistration(int $id): array
    {
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.', 'status' => 404];
        }

        $this->employeeModel->delete($id);

        return ['success' => true, 'employee' => $employee];
    }
}
