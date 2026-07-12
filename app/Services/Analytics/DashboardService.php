<?php
// ARQ-03 FIX: Este arquivo é o núcleo real do serviço.
// A nomenclatura "LegacyCore" é um artefato histórico — não indica código obsoleto.
// Roadmap: renomear para *ServiceCore e a subclasse pública para *Service
// na próxima refatoração maior. (Ver SECURITY_AUDIT_IMPLEMENTATION.md)


namespace App\Services\Analytics;

use App\Services\Analytics\Dashboard\DashboardDataAssembler;
use App\Services\Analytics\Dashboard\DashboardFilterResolver;
use App\Services\Analytics\Dashboard\DashboardMetricsRepository;
use CodeIgniter\Config\Services;

/**
 * Núcleo legado de analytics do dashboard.
 *
 * Mantém API pública enquanto delega consultas, agregações e composição
 * para componentes especializados.
 */
class DashboardService
{
    private DashboardFilterResolver $filterResolver;
    private DashboardMetricsRepository $repository;
    private DashboardDataAssembler $assembler;

    public function __construct()
    {
        $this->repository = new DashboardMetricsRepository(Services::database()->connect());
        $this->filterResolver = new DashboardFilterResolver();
        $this->assembler = new DashboardDataAssembler($this->repository);
    }

    public function getOverviewKPIs(?string $startDate = null, ?string $endDate = null, ?int $departmentId = null): array
    {
        $filters = $this->filterResolver->normalize($startDate, $endDate, $departmentId);
        return $this->assembler->overviewKpis($filters['startDate'], $filters['endDate'], $filters['departmentId']);
    }

    public function getTotalEmployees(?int $departmentId = null): int
    {
        return $this->repository->totalEmployees($departmentId);
    }

    public function getActiveEmployees(?int $departmentId = null): int
    {
        return $this->repository->activeEmployees($departmentId);
    }

    public function getPunchesCount(string $startDate, string $endDate, ?int $departmentId = null): int
    {
        return $this->repository->punchesCount($startDate, $endDate, $departmentId);
    }

    public function getTotalHoursWorked(string $startDate, string $endDate, ?int $departmentId = null): float
    {
        return $this->repository->totalHoursWorked($startDate, $endDate, $departmentId);
    }

    public function getPendingApprovals(?int $departmentId = null): int
    {
        return $this->repository->pendingApprovals($departmentId);
    }

    public function getDepartmentsCount(): int
    {
        return $this->repository->departmentsCount();
    }

    public function getAverageHoursPerEmployee(string $startDate, string $endDate, ?int $departmentId = null): float
    {
        $totalHours = $this->repository->totalHoursWorked($startDate, $endDate, $departmentId);
        $activeEmployees = $this->repository->activeEmployees($departmentId);

        return $activeEmployees > 0 ? round($totalHours / $activeEmployees, 2) : 0;
    }

    public function getPunchesByHour(string $date, ?int $departmentId = null): array
    {
        return $this->assembler->punchesByHour($date, $departmentId);
    }

    public function getHoursByDepartment(string $startDate, string $endDate): array
    {
        return $this->assembler->hoursByDepartment($startDate, $endDate);
    }

    public function getEmployeeStatusDistribution(?int $departmentId = null): array
    {
        return $this->assembler->employeeStatusDistribution($departmentId);
    }

    public function getRecentActivity(int $limit = 10, ?int $departmentId = null): array
    {
        return $this->assembler->recentActivity($limit, $departmentId);
    }

    public function getTopEmployeesByHours(string $startDate, string $endDate, int $limit = 10, ?int $departmentId = null): array
    {
        return $this->assembler->topEmployeesByHours($startDate, $endDate, $limit, $departmentId);
    }

    public function getAttendanceRate(string $startDate, string $endDate, ?int $departmentId = null): float
    {
        return $this->assembler->attendanceRate($startDate, $endDate, $departmentId);
    }

    public function getDepartments(): array
    {
        return $this->assembler->departments();
    }

    public function getDashboardData(array $filters = []): array
    {
        return $this->assembler->dashboardData($this->filterResolver->fromArray($filters));
    }
}
