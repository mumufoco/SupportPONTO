<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Enums\Role;

class JustificationQueryService
{
    public function __construct(
        private readonly JustificationModel $justificationModel = new JustificationModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function authenticatedEmployee(): ?array
    {
        if (!session()->has('user_id')) {
            return null;
        }

        $employee = $this->employeeModel->find((int) session()->get('user_id'));
        if (!$employee || !is_object($employee)) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => Role::normalize((string) $employee->role)->value,
            'department' => $employee->department,
        ];
    }

    /** Mapeamento de valores de filtro (interface) para valores reais no banco. */
    private const STATUS_MAP = [
        'pending'   => 'pendente',
        'approved'  => 'aprovado',
        'rejected'  => 'rejeitado',
        'pendente'  => 'pendente',
        'aprovado'  => 'aprovado',
        'rejeitado' => 'rejeitado',
    ];

    public function indexData(array $actor, array $filters, int $perPage = 20): array
    {
        $statusFilter = (string) ($filters['status'] ?? '');
        $typeFilter   = (string) ($filters['type']   ?? '');
        $searchFilter = (string) ($filters['search'] ?? '');

        // Map English UI status values to Portuguese DB values
        $dbStatus = self::STATUS_MAP[$statusFilter] ?? null;

        // Resolve employeeIds once for gestor role (avoids duplicate query below)
        $employeeIds = null;
        if ($actor['role'] === Role::Gestor->value) {
            $employeeIds = $this->employeeModel->where('department', $actor['department'])->findColumn('id') ?: [];
        }

        $query = $this->justificationModel
            ->select("justifications.*, COALESCE(employees.name, 'Desconhecido') AS employee_name", false)
            ->join('employees', 'employees.id = justifications.employee_id', 'left');

        if ($actor['role'] === Role::Funcionario->value) {
            $query->where('justifications.employee_id', $actor['id']);
        } elseif ($actor['role'] === Role::Gestor->value && !empty($employeeIds)) {
            $query->whereIn('justifications.employee_id', $employeeIds);
        } elseif ($actor['role'] === Role::Gestor->value) {
            $query->where('1', '0', false);
        }

        if ($dbStatus !== null) {
            $query->where('justifications.status', $dbStatus);
        }

        if ($typeFilter !== '') {
            $query->where('justifications.justification_type', $typeFilter);
        }

        if ($searchFilter !== '') {
            $query->groupStart()
                ->like('employees.name', $searchFilter)
                ->orLike('justifications.reason', $searchFilter)
            ->groupEnd();
        }

        $justifications = $query->asArray()->orderBy('justifications.created_at', 'DESC')->paginate($perPage);

        $builder = $this->justificationModel->builder();
        if ($actor['role'] === Role::Funcionario->value) {
            $builder->where('employee_id', $actor['id']);
        } elseif ($actor['role'] === Role::Gestor->value && !empty($employeeIds)) {
            $builder->whereIn('employee_id', $employeeIds);
        } elseif ($actor['role'] === Role::Gestor->value) {
            $builder->where('1', '0', false);
        }

        return [
            'justifications' => $justifications,
            'pager'          => $this->justificationModel->pager,
            'counts'         => [
                'all'      => (clone $builder)->countAllResults(false),
                'pending'  => (clone $builder)->where('status', 'pendente')->countAllResults(false),
                'approved' => (clone $builder)->where('status', 'aprovado')->countAllResults(false),
                'rejected' => (clone $builder)->where('status', 'rejeitado')->countAllResults(false),
            ],
        ];
    }

        public function showData(int $id, array $actor): ?array
    {
        $justification = $this->justificationModel->find($id);
        if (!$justification || !is_object($justification)) {
            return null;
        }

        if (!$this->canAccess($actor, $justification)) {
            return ['access_denied' => true];
        }

        $justificationEmployee = $this->employeeModel->find($justification->employee_id);
        $reviewer = null;
        if (!empty($justification->reviewed_by)) {
            $reviewer = $this->employeeModel->find($justification->reviewed_by);
        }

        return [
            'justification' => $justification,
            'justificationEmployee' => $justificationEmployee,
            'reviewer' => $reviewer,
        ];
    }

    public function canReview(array $actor, int $justificationId): bool
    {
        if (!$this->canReviewJustifications($actor)) {
            return false;
        }

        $justification = $this->justificationModel->find($justificationId);
        if (!$justification || !is_object($justification)) {
            return false;
        }

        return $this->canAccess($actor, $justification);
    }


    private function canReviewJustifications(array $actor): bool
    {
        try {
            return Role::normalize((string) ($actor['role'] ?? Role::Funcionario->value))->canManageEmployees();
        } catch (\Throwable) {
            return false;
        }
    }

    private function canAccess(array $actor, object $justification): bool
    {
        if (in_array($actor['role'], [Role::Admin->value, Role::RH->value], true)) {
            return true;
        }

        if ($actor['role'] === Role::Funcionario->value) {
            return (int) $justification->employee_id === (int) $actor['id'];
        }

        if ($actor['role'] === Role::Gestor->value) {
            $target = $this->employeeModel->find($justification->employee_id);
            return $target && is_object($target) && $target->department === $actor['department'];
        }

        return false;
    }
}
