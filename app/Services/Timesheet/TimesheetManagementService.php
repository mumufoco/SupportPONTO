<?php

namespace App\Services\Timesheet;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\TimesheetConsolidatedModel;
use App\Services\Queue\AsyncJobService;
use App\Enums\Role;

class TimesheetManagementService
{
    public function __construct(
        protected EmployeeModel $employeeModel,
        protected TimesheetConsolidatedModel $consolidatedModel,
        protected TimesheetReadService $timesheetReadService,
        protected AsyncJobService $asyncJobService,
        protected AuditModel $auditModel,
    ) {
    }

    public function buildBalanceViewData(array $actor, int $viewingEmployeeId, object $viewingEmployee, int $days, bool $irregularitiesOnly, ?string $employeeQueryId): array
    {
        $employees = $this->listManageableEmployees($actor);

        if (! $this->consolidatedModel->tableExists()) {
            return [
                'employee' => $actor,
                'viewingEmployee' => $viewingEmployee,
                'balance' => ['extra' => 0.0, 'owed' => 0.0, 'balance' => 0.0],
                'evolution' => [],
                'records' => [],
                'statistics' => [
                    'total_days' => 0,
                    'incomplete_days' => 0,
                    'justified_days' => 0,
                    'total_worked' => 0.0,
                    'total_expected' => 0.0,
                    'total_extra' => 0.0,
                    'total_owed' => 0.0,
                    'total_violations' => 0.0,
                    'avg_worked' => 0.0,
                ],
                'employees' => $employees,
                'incompleteDays' => [],
                'period' => (string) $days,
                'irregularitiesOnly' => $irregularitiesOnly,
                'employee_id' => $employeeQueryId ?? '',
                'warning' => 'Sistema de consolidação não está disponível.',
            ];
        }

        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        $endDate = date('Y-m-d');

        $query = $this->consolidatedModel
            ->where('employee_id', $viewingEmployeeId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);

        if ($irregularitiesOnly) {
            $query->groupStart()
                ->where('incomplete', true)
                ->orWhere('interval_violation >', 0)
                ->orWhere('owed >', 0)
                ->groupEnd();
        }

        // Fetch records FIRST — calling any other method on $this->consolidatedModel
        // resets the shared QueryBuilder, wiping the where conditions built on $query.
        $records = $query->orderBy('date', 'DESC')->findAll();

        return [
            'employee' => $actor,
            'viewingEmployee' => $viewingEmployee,
            'balance' => $this->consolidatedModel->getCurrentBalance($viewingEmployeeId),
            'evolution' => $this->consolidatedModel->getBalanceEvolution($viewingEmployeeId, $days),
            'records' => $records,
            'statistics' => $this->consolidatedModel->getStatistics($viewingEmployeeId, $startDate, $endDate),
            'employees' => $employees,
            'incompleteDays' => $this->consolidatedModel->getIncompleteDays($viewingEmployeeId, $startDate),
            'period' => (string) $days,
            'irregularitiesOnly' => $irregularitiesOnly,
            'employee_id' => $employeeQueryId ?? '',
        ];
    }

    public function queueTimesheetExport(array $actor, int $targetEmployeeId, string $month, string $format): array
    {
        $access = $this->timesheetReadService->resolveAuthorizedEmployee($actor, $targetEmployeeId);
        if (! $access['success']) {
            return $access;
        }

        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_REPORT_GENERATE, [
            'type' => 'folha-ponto',
            'format' => $format,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'employee_ids' => [$targetEmployeeId],
            ],
        ], [
            'employee_id' => (int) $actor['id'],
            'queue' => 'reports',
            'priority' => 65,
            'max_attempts' => 4,
        ]);

        $this->auditModel->log(
            (int) $actor['id'],
            'TIMESHEET_EXPORT_QUEUED',
            'async_jobs',
            null,
            null,
            [
                'format' => $format,
                'employee_id' => $targetEmployeeId,
                'month' => $month,
                'job_id' => $job['job_id'] ?? null,
            ],
            "Exportação {$format} enfileirada - {$access['employee']->name}",
            'info'
        );

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Exportação ' . strtoupper($format) . ' enfileirada com sucesso. Job: ' . ($job['job_id'] ?? '-'),
            'data' => ['job_id' => $job['job_id'] ?? null],
        ];
    }

    protected function listManageableEmployees(array $actor): array
    {
        if (! $this->canManageEmployees($actor)) {
            return [];
        }

        // Administradores do sistema não são colaboradores gerenciáveis via timesheet.
        $query = $this->employeeModel->where('active', true)->where('role !=', 'admin');
        if ($this->hasDepartmentScope($actor)) {
            $query->where('department', $actor['department']);
        }

        return $query->orderBy('name', 'ASC')->findAll();
    }

    private function canManageEmployees(array $actor): bool
    {
        try {
            return Role::normalize((string) ($actor['role'] ?? Role::Funcionario->value))->canManageEmployees();
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasDepartmentScope(array $actor): bool
    {
        try {
            return Role::normalize((string) ($actor['role'] ?? Role::Funcionario->value)) === Role::Gestor;
        } catch (\Throwable) {
            return false;
        }
    }
}
