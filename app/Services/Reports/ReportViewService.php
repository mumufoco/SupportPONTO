<?php

namespace App\Services\Reports;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Services\TimesheetService;

class ReportViewService
{
    public function __construct(
        protected EmployeeModel $employeeModel,
        protected JustificationModel $justificationModel,
        protected TimesheetService $timesheetService,
    ) {
    }

    public function getTimesheetViewData(array $actor, string $month, ?string $department, ?string $employeeId): array
    {
        // Exige um colaborador especifico: gerar o espelho mensal completo de todos os
        // funcionarios de uma vez (sem filtro) nao escala conforme o quadro cresce e nunca
        // foi o proposito real desta tela (ver /reports para relatorios agregados).
        if (empty($employeeId)) {
            return [
                'timesheets' => [],
                'departments' => $this->getDepartments(),
            ];
        }

        // Departamento aqui so filtra a lista do seletor de colaborador (ver
        // reports/timesheet.php) - uma vez que um colaborador especifico foi escolhido, o
        // departamento nao deve co-restringir a consulta (evita retornar vazio
        // silenciosamente se as duas selecoes ficarem dessincronizadas).
        $employees = $this->getEmployeeScope($actor, null, $employeeId);
        $timesheets = [];

        foreach ($employees as $emp) {
            $timesheet = $this->timesheetService->generateMonthlyTimesheet((int) $emp->id, $month);
            if ($timesheet['success'] ?? false) {
                $timesheets[] = [
                    'employee' => $emp,
                    'summary' => $timesheet['summary'],
                    'daily_records' => $timesheet['daily_records'],
                ];
            }
        }

        return [
            'timesheets' => $timesheets,
            'departments' => $this->getDepartments(),
        ];
    }

    public function getAttendanceViewData(array $actor, string $startDate, string $endDate, ?string $department): array
    {
        $employees = $this->getEmployeeScope($actor, $department, null);
        $attendanceData = [];

        foreach ($employees as $emp) {
            $calculation = $this->timesheetService->calculateHoursWorked((int) $emp->id, $startDate, $endDate);
            $lateArrivals = $this->timesheetService->findLateArrivals((int) $emp->id, $startDate, $endDate);
            $missingPunches = $this->timesheetService->findMissingPunches((int) $emp->id, $startDate, $endDate);

            $attendanceData[] = [
                'employee' => $emp,
                'days_worked' => $calculation['total_days'] ?? $calculation['days_worked'] ?? 0,
                'hours_worked' => $calculation['total_hours'],
                'expected_hours' => $calculation['expected_hours'],
                'balance' => $calculation['balance'],
                'late_arrivals' => count($lateArrivals),
                'missing_punches' => count($missingPunches),
                'attendance_rate' => ($calculation['expected_hours'] ?? 0) > 0
                    ? round(($calculation['total_hours'] / $calculation['expected_hours']) * 100, 1)
                    : 0,
            ];
        }

        usort($attendanceData, static fn(array $a, array $b): int => $a['attendance_rate'] <=> $b['attendance_rate']);

        return [
            'attendanceData' => $attendanceData,
            'departments' => $this->getDepartments(),
        ];
    }

    public function getLateArrivalsViewData(array $actor, string $startDate, string $endDate, ?string $department, ?string $employeeId = null): array
    {
        $employees = $this->getEmployeeScope($actor, $department, $employeeId);
        $lateArrivalsData = [];

        foreach ($employees as $emp) {
            $lateArrivals = $this->timesheetService->findLateArrivals((int) $emp->id, $startDate, $endDate);
            if (! empty($lateArrivals)) {
                $lateArrivalsData[] = [
                    'employee' => $emp,
                    'late_arrivals' => $lateArrivals,
                    'total_count' => count($lateArrivals),
                ];
            }
        }

        usort($lateArrivalsData, static fn(array $a, array $b): int => $b['total_count'] <=> $a['total_count']);

        return [
            'lateArrivalsData' => $lateArrivalsData,
            'departments' => $this->getDepartments(),
        ];
    }

    public function getJustificationsViewData(array $actor, string $startDate, string $endDate, ?string $department, ?string $employeeId = null): array
    {
        $query = $this->justificationModel
            ->where('justification_date >=', $startDate)
            ->where('justification_date <=', $endDate);

        if (($actor['role'] ?? '') === 'gestor') {
            $employeeIds = $this->employeeModel->where('department', $actor['department'])->findColumn('id');
            $query->whereIn('employee_id', $employeeIds ?: [0]);
        } elseif ($department) {
            $employeeIds = $this->employeeModel->where('department', $department)->findColumn('id');
            $query->whereIn('employee_id', $employeeIds ?: [0]);
        }

        if (! empty($employeeId)) {
            $query->where('employee_id', (int) $employeeId);
        }

        $justifications = $query->orderBy('created_at', 'DESC')->findAll();

        $employeeMap = [];
        $employeeIds = array_values(array_unique(array_filter(array_map(
            static fn($justification): int => (int) ($justification->employee_id ?? 0),
            $justifications
        ))));

        if ($employeeIds !== []) {
            $employees = $this->employeeModel
                ->select('id, name, department')
                ->whereIn('id', $employeeIds)
                ->findAll();

            foreach ($employees as $employee) {
                $employeeMap[(int) ($employee->id ?? 0)] = $employee;
            }
        }

        foreach ($justifications as &$justification) {
            $emp = $employeeMap[(int) ($justification->employee_id ?? 0)] ?? null;
            $justification->employee_name = $emp ? $emp->name : 'Desconhecido';
            $justification->employee_department = $emp ? $emp->department : 'N/A';
        }
        unset($justification);

        return [
            'justifications' => $justifications,
            'summary' => [
                'total' => count($justifications),
                'pending' => count(array_filter($justifications, static fn($j): bool => $j->status === 'pendente')),
                'approved' => count(array_filter($justifications, static fn($j): bool => $j->status === 'aprovado')),
                'rejected' => count(array_filter($justifications, static fn($j): bool => $j->status === 'rejeitado')),
            ],
            'departments' => $this->getDepartments(),
        ];
    }

    protected function getEmployeeScope(array $actor, ?string $department, ?string $employeeId): array
    {
        // Administradores do sistema não são colaboradores e não entram em relatórios.
        $query = $this->employeeModel->where('active', true)->where('role !=', 'admin');

        if (($actor['role'] ?? '') === 'gestor') {
            $query->where('department', $actor['department']);
        } elseif (! empty($department)) {
            $query->where('department', $department);
        }

        if (! empty($employeeId)) {
            $query->where('id', $employeeId);
        }

        return $query->findAll();
    }

    public function getEmployeesForReports(array $actor): array
    {
        // Administradores do sistema não são colaboradores e não entram em relatórios
        // (mesmo critério de getEmployeeScope() acima).
        $query = $this->employeeModel
            ->select('id, name, department')
            ->where('active', true)
            ->where('role !=', 'admin')
            ->orderBy('name', 'ASC');

        if (($actor['role'] ?? '') === 'gestor') {
            $query->where('department', $actor['department']);
        }

        return $query->findAll();
    }

    protected function getDepartments(): array
    {
        return $this->employeeModel
            ->distinct()
            ->select('department')
            ->where('active', true)
            ->findColumn('department') ?? [];
    }

    public function getDepartmentsForReports(): array
    {
        return $this->getDepartments();
    }
}
