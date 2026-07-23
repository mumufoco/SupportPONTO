<?php

namespace App\Services\Analytics\Dashboard;

use App\Support\Dates\DashboardDateRange;
use CodeIgniter\Database\ConnectionInterface;

class DashboardMetricsRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function totalEmployees(?int $departmentId = null): int
    {
        // Administradores do sistema não são colaboradores — ficam fora das
        // métricas de headcount do dashboard.
        $builder = $this->db->table('employees')->where('role !=', 'admin');
        if ($departmentId) {
            $builder->where('department_id', $departmentId);
        }

        return $builder->countAllResults();
    }

    public function activeEmployees(?int $departmentId = null): int
    {
        $builder = $this->db->table('employees')->where('active', true)->where('role !=', 'admin');
        if ($departmentId) {
            $builder->where('department_id', $departmentId);
        }

        return $builder->countAllResults();
    }

    public function punchesCount(string $startDate, string $endDate, ?int $departmentId = null): int
    {
        [$rangeStart, $rangeEnd] = $this->normalizeInclusiveDateRange($startDate, $endDate);

        $builder = $this->db->table('timesheets t')
            ->where('t.punch_time >=', $rangeStart)
            ->where('t.punch_time <', $rangeEnd);

        if ($departmentId) {
            $builder->join('employees e', 'e.id = t.employee_id')->where('e.department_id', $departmentId);
        }

        return $builder->countAllResults();
    }

    public function totalHoursWorked(string $startDate, string $endDate, ?int $departmentId = null): float
    {
        [$rangeStart, $rangeEnd] = $this->normalizeInclusiveDateRange($startDate, $endDate);

        $builder = $this->db->table('timesheets t')
            ->select('SUM(TIMESTAMPDIFF(SECOND, t.punch_time, t.punch_out_time) / 3600) as total_hours')
            ->where('t.punch_time >=', $rangeStart)
            ->where('t.punch_time <', $rangeEnd)
            ->where('t.punch_out_time IS NOT NULL');

        if ($departmentId) {
            $builder->join('employees e', 'e.id = t.employee_id')->where('e.department_id', $departmentId);
        }

        $result = $builder->get()->getRow();
        return round((float) ($result->total_hours ?? 0), 2);
    }

    public function pendingApprovals(?int $departmentId = null): int
    {
        $builder = $this->db->table('timesheets t')->where('t.status', 'pending');
        if ($departmentId) {
            $builder->join('employees e', 'e.id = t.employee_id')->where('e.department_id', $departmentId);
        }

        return $builder->countAllResults();
    }

    public function departmentsCount(): int
    {
        return $this->db->table('departments')->where('active', true)->countAllResults();
    }

    public function punchesByHour(string $date, ?int $departmentId = null): array
    {
        [$dayStart, $dayEnd] = DashboardDateRange::day($date);

        $builder = $this->db->table('timesheets t')
            ->select('HOUR(t.punch_time) as hour, COUNT(*) as count')
            ->where('t.punch_time >=', $dayStart)
            ->where('t.punch_time <', $dayEnd)
            ->groupBy('HOUR(t.punch_time)')
            ->orderBy('hour', 'ASC');

        if ($departmentId) {
            $builder->join('employees e', 'e.id = t.employee_id')->where('e.department_id', $departmentId);
        }

        return $builder->get()->getResult();
    }

    public function hoursByDepartment(string $startDate, string $endDate): array
    {
        [$rangeStart, $rangeEnd] = $this->normalizeInclusiveDateRange($startDate, $endDate);

        return $this->db->table('timesheets t')
            ->select('d.name as department, SUM(TIMESTAMPDIFF(SECOND, t.punch_time, t.punch_out_time) / 3600) as hours')
            ->join('employees e', 'e.id = t.employee_id')
            ->join('departments d', 'd.id = e.department_id')
            ->where('t.punch_time >=', $rangeStart)
            ->where('t.punch_time <', $rangeEnd)
            ->where('t.punch_out_time IS NOT NULL')
            ->groupBy('d.id')
            ->orderBy('hours', 'DESC')
            ->get()
            ->getResult();
    }

    public function employeesWorkingToday(?int $departmentId = null): int
    {
        [$dayStart, $dayEnd] = DashboardDateRange::day();

        $builder = $this->db->table('employees e')
            ->select('COUNT(*) as count')
            ->join('timesheets t', 't.employee_id = e.id')
            ->where('t.punch_time >=', $dayStart)
            ->where('t.punch_time <', $dayEnd)
            ->where('t.punch_out_time IS NULL')
            ->where('e.active', true);

        if ($departmentId) {
            $builder->where('e.department_id', $departmentId);
        }

        return (int) ($builder->get()->getRow()->count ?? 0);
    }

    public function inactiveEmployees(?int $departmentId = null): int
    {
        $builder = $this->db->table('employees')->where('active', false)->where('role !=', 'admin');
        if ($departmentId) {
            $builder->where('department_id', $departmentId);
        }

        return $builder->countAllResults();
    }

    public function recentActivity(int $limit = 10, ?int $departmentId = null): array
    {
        $builder = $this->db->table('timesheets t')
            ->select('t.id, t.employee_id, t.punch_time, t.punch_out_time, t.punch_type, e.name as employee_name, d.name as department_name')
            ->join('employees e', 'e.id = t.employee_id')
            ->join('departments d', 'd.id = e.department_id')
            ->orderBy('t.punch_time', 'DESC')
            ->limit($limit);

        if ($departmentId) {
            $builder->where('e.department_id', $departmentId);
        }

        return $builder->get()->getResult();
    }

    public function topEmployeesByHours(string $startDate, string $endDate, int $limit = 10, ?int $departmentId = null): array
    {
        [$rangeStart, $rangeEnd] = $this->normalizeInclusiveDateRange($startDate, $endDate);

        $builder = $this->db->table('timesheets t')
            ->select('e.id, e.name, d.name as department, SUM(TIMESTAMPDIFF(SECOND, t.punch_time, t.punch_out_time) / 3600) as total_hours')
            ->join('employees e', 'e.id = t.employee_id')
            ->join('departments d', 'd.id = e.department_id')
            ->where('t.punch_time >=', $rangeStart)
            ->where('t.punch_time <', $rangeEnd)
            ->where('t.punch_out_time IS NOT NULL')
            ->groupBy('e.id')
            ->orderBy('total_hours', 'DESC')
            ->limit($limit);

        if ($departmentId) {
            $builder->where('e.department_id', $departmentId);
        }

        return $builder->get()->getResult();
    }

    public function departments(): array
    {
        return $this->db->table('departments')
            ->select('id, name')
            ->where('active', true)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResult();
    }

    private function normalizeInclusiveDateRange(string $startDate, string $endDate): array
    {
        $rangeStart = (new \DateTimeImmutable($startDate))->setTime(0, 0, 0);
        $rangeEnd = (new \DateTimeImmutable($endDate))->setTime(0, 0, 0)->modify('+1 day');

        return [$rangeStart->format('Y-m-d H:i:s'), $rangeEnd->format('Y-m-d H:i:s')];
    }
}

