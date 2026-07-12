<?php

namespace App\Services\Analytics\Dashboard;

class DashboardDataAssembler
{
    public function __construct(private readonly DashboardMetricsRepository $repository)
    {
    }

    public function overviewKpis(string $startDate, string $endDate, ?int $departmentId = null): array
    {
        $totalHours = $this->repository->totalHoursWorked($startDate, $endDate, $departmentId);
        $activeEmployees = $this->repository->activeEmployees($departmentId);

        return [
            'total_employees' => $this->repository->totalEmployees($departmentId),
            'active_employees' => $activeEmployees,
            'punches_today' => $this->repository->punchesCount($startDate, $endDate, $departmentId),
            'total_hours' => $totalHours,
            'pending_approvals' => $this->repository->pendingApprovals($departmentId),
            'departments_count' => $this->repository->departmentsCount(),
            'avg_hours_per_employee' => $activeEmployees > 0 ? round($totalHours / $activeEmployees, 2) : 0,
        ];
    }

    public function punchesByHour(string $date, ?int $departmentId = null): array
    {
        $results = $this->repository->punchesByHour($date, $departmentId);
        $data = array_fill(0, 24, 0);

        foreach ($results as $row) {
            $data[(int) $row->hour] = (int) $row->count;
        }

        return [
            'labels' => array_map(static fn($h) => sprintf('%02d:00', $h), range(0, 23)),
            'data' => array_values($data),
        ];
    }

    public function hoursByDepartment(string $startDate, string $endDate): array
    {
        $labels = [];
        $data = [];

        foreach ($this->repository->hoursByDepartment($startDate, $endDate) as $row) {
            $labels[] = $row->department;
            $data[] = round((float) $row->hours, 2);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public function employeeStatusDistribution(?int $departmentId = null): array
    {
        $working = $this->repository->employeesWorkingToday($departmentId);
        $inactive = $this->repository->inactiveEmployees($departmentId);
        $totalActive = $this->repository->activeEmployees($departmentId);
        $available = $totalActive - $working;

        return [
            'labels' => ['Trabalhando', 'Disponível', 'Inativo'],
            'data' => [$working, $available, $inactive],
            'colors' => ['#4CAF50', '#2196F3', '#9E9E9E'],
        ];
    }

    public function recentActivity(int $limit = 10, ?int $departmentId = null): array
    {
        return array_map(static fn($row) => [
            'id' => $row->id,
            'employee_id' => $row->employee_id,
            'employee_name' => $row->employee_name,
            'department' => $row->department_name,
            'punch_time' => $row->punch_time,
            'punch_out_time' => $row->punch_out_time,
            'punch_type' => $row->punch_type,
            'formatted_time' => date('d/m/Y H:i', strtotime($row->punch_time)),
        ], $this->repository->recentActivity($limit, $departmentId));
    }

    public function topEmployeesByHours(string $startDate, string $endDate, int $limit = 10, ?int $departmentId = null): array
    {
        return array_map(static fn($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'department' => $row->department,
            'total_hours' => round((float) $row->total_hours, 2),
        ], $this->repository->topEmployeesByHours($startDate, $endDate, $limit, $departmentId));
    }

    public function attendanceRate(string $startDate, string $endDate, ?int $departmentId = null): float
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $days = $start->diff($end)->days + 1;

        $employeesCount = $this->repository->activeEmployees($departmentId);
        if ($employeesCount === 0) {
            return 0;
        }

        $expectedPunches = $employeesCount * $days;
        $actualPunches = $this->repository->punchesCount($startDate, $endDate, $departmentId);

        return round(min(($actualPunches / $expectedPunches) * 100, 100), 2);
    }

    public function departments(): array
    {
        return array_map(static fn($row) => ['id' => $row->id, 'name' => $row->name], $this->repository->departments());
    }

    public function dashboardData(array $filters): array
    {
        $startDate = $filters['startDate'];
        $endDate = $filters['endDate'];
        $departmentId = $filters['departmentId'];

        return [
            'kpis' => $this->overviewKpis($startDate, $endDate, $departmentId),
            'charts' => [
                'punches_by_hour' => $this->punchesByHour($startDate, $departmentId),
                'hours_by_department' => $this->hoursByDepartment($startDate, $endDate),
                'employee_status' => $this->employeeStatusDistribution($departmentId),
            ],
            'recent_activity' => $this->recentActivity(10, $departmentId),
            'top_employees' => $this->topEmployeesByHours($startDate, $endDate, 10, $departmentId),
            'attendance_rate' => $this->attendanceRate($startDate, $endDate, $departmentId),
            'departments' => $this->departments(),
            'filters' => [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'departmentId' => $departmentId,
            ],
        ];
    }
}
