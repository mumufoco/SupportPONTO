<?php

namespace App\Services\Employees\API;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Services\Employees\EmployeeIdentityService;
use App\Services\TimesheetService;
use App\Enums\Role;

class EmployeeApiService
{
    public function __construct(
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
        private readonly JustificationModel $justificationModel = new JustificationModel(),
        private readonly TimesheetService $timesheetService = new TimesheetService(),
        private readonly EmployeeIdentityService $identityService = new EmployeeIdentityService(),
    ) {
    }

    public function profileData(object $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'cpf' => format_cpf($employee->cpf),
            'role' => $employee->role,
            'department' => $employee->department,
            'position' => $employee->position,
            'unique_code' => $employee->unique_code,
            'phone' => $employee->phone ? format_phone_br($employee->phone) : null,
            'admission_date' => $employee->admission_date ? format_date_br($employee->admission_date) : null,
            'daily_hours' => $employee->daily_hours,
            'weekly_hours' => $employee->weekly_hours,
            'work_schedule' => [
                'start' => format_time($employee->work_start_time),
                'end' => format_time($employee->work_end_time),
                'lunch_start' => format_time($employee->lunch_start_time),
                'lunch_end' => format_time($employee->lunch_end_time),
            ],
            'biometric' => [
                'has_face' => $employee->has_face_biometric,
                'has_fingerprint' => $employee->has_fingerprint_biometric,
            ],
            'active' => $employee->active,
        ];
    }

    public function balanceData(object $employee): array
    {
        $balanceData = format_balance($employee->hours_balance ?? 0);

        return [
            'hours_balance' => $employee->hours_balance ?? 0,
            'hours_balance_formatted' => $balanceData['formatted'],
            'extra_hours_balance' => $employee->extra_hours_balance ?? 0,
            'owed_hours_balance' => $employee->owed_hours_balance ?? 0,
        ];
    }

    public function statisticsData(object $employee, string $month): array
    {
        $monthRange = get_month_datetime_range($month);
        $startDate = $monthRange['start_date'];
        $endDate = $monthRange['end_date'];

        $calculation = $this->timesheetService->calculateHoursWorked($employee->id, $startDate, $endDate);
        $lateArrivals = $this->timesheetService->findLateArrivals($employee->id, $startDate, $endDate);
        $missingPunches = $this->timesheetService->findMissingPunches($employee->id, $startDate, $endDate);

        $justifications = $this->justificationModel
            ->where('employee_id', $employee->id)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->findAll();

        return [
            'period' => [
                'month' => format_month_year_br($monthRange['month']),
                'start' => format_date_br($startDate),
                'end' => format_date_br($endDate),
            ],
            'hours' => [
                'worked' => $calculation['total_hours'],
                'expected' => $calculation['expected_hours'],
                'balance' => $calculation['balance'],
                'average_per_day' => $calculation['average_hours_per_day'],
            ],
            'attendance' => [
                'days_worked' => $calculation['total_days'],
                'late_arrivals' => count($lateArrivals),
                'missing_days' => count($missingPunches),
            ],
            'justifications' => [
                'total' => count($justifications),
                'pending' => count(array_filter($justifications, fn($j) => $j->status === 'pendente')),
                'approved' => count(array_filter($justifications, fn($j) => $j->status === 'aprovado')),
                'rejected' => count(array_filter($justifications, fn($j) => $j->status === 'rejeitado')),
            ],
        ];
    }

    public function updateProfilePhone(int $employeeId, ?string $phone): bool
    {
        return $this->employeeModel->update($employeeId, ['phone' => $phone]);
    }

    public function teamData(object $employee): array
    {
        if (!$this->canManageEmployees((string) $employee->role)) {
            return ['success' => false, 'status' => 403, 'message' => 'Acesso negado.'];
        }

        // Administradores do sistema não são colaboradores e não aparecem em "minha equipe".
        $query = $this->employeeModel->where('active', true)->where('role !=', 'admin');
        if ($this->hasDepartmentScope((string) $employee->role)) {
            $query->where('department_id', ! empty($employee->department_id) ? (int) $employee->department_id : 0);
        }

        $members = $query->orderBy('name', 'ASC')->findAll();

        return [
            'success' => true,
            'data' => array_map(fn($member) => $this->summaryData($member), $members),
        ];
    }

    /**
     * Lista completa de colaboradores ativos para integrações de sistema
     * (ex.: SupportSEV) -- autenticadas por token estático, sem um "ator"
     * logado, então sem escopo por departamento (equivalente ao que RH já
     * via em teamData(), que não é department-scoped).
     */
    public function teamDataForIntegration(): array
    {
        // getActive() já retorna array (não builder) -- não dá pra encadear
        // orderBy() nele, então ordena aqui.
        $members = $this->employeeModel->getActive();
        usort($members, static fn ($a, $b): int => strcasecmp((string) ($a->name ?? ''), (string) ($b->name ?? '')));

        return [
            'success' => true,
            'data' => array_map(fn($member) => $this->summaryData($member), $members),
        ];
    }

    public function employeeByCode(object $requester, ?string $code): array
    {
        if (!$this->canManageEmployees((string) $requester->role)) {
            return ['success' => false, 'status' => 403, 'message' => 'Acesso negado.'];
        }

        $targetEmployee = $this->employeeModel->findByCode($code);
        if (!$targetEmployee) {
            return ['success' => false, 'status' => 404, 'message' => 'Funcionário não encontrado.'];
        }

        if ($this->hasDepartmentScope((string) $requester->role)) {
            $requesterDepartmentId = ! empty($requester->department_id) ? (int) $requester->department_id : null;
            $targetDepartmentId = ! empty($targetEmployee->department_id) ? (int) $targetEmployee->department_id : null;

            if ($requesterDepartmentId === null || $requesterDepartmentId !== $targetDepartmentId) {
                return ['success' => false, 'status' => 403, 'message' => 'Acesso negado.'];
            }
        }

        return [
            'success' => true,
            'data' => array_merge($this->summaryData($targetEmployee), [
                'active' => $targetEmployee->active,
            ]),
        ];
    }


    private function canManageEmployees(string $role): bool
    {
        try {
            return Role::normalize($role)->canManageEmployees();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasDepartmentScope(string $role): bool
    {
        try {
            return Role::normalize($role) === Role::Gestor;
        } catch (\Throwable) {
            return false;
        }
    }

    private function summaryData(mixed $employee): array
    {
        $identity = $this->identityService->toArray($employee);

        return [
            'id' => is_array($employee) ? ($employee['id'] ?? null) : ($employee->id ?? null),
            'name' => $identity['name'],
            'email' => $identity['email'],
            'role' => is_array($employee) ? ($employee['role'] ?? null) : ($employee->role ?? null),
            'department' => is_array($employee) ? ($employee['department'] ?? null) : ($employee->department ?? null),
            'position' => is_array($employee) ? ($employee['position'] ?? null) : ($employee->position ?? null),
            'unique_code' => $identity['unique_code'],
        ];
    }
}
