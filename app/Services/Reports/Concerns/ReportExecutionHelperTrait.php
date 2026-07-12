<?php

namespace App\Services\Reports\Concerns;

trait ReportExecutionHelperTrait
{
    protected function reportDataGenerators(): array
    {
        return [
            'folha-ponto' => 'generateTimesheetReport',
            'horas-extras' => 'generateOvertimeReport',
            'faltas-atrasos' => 'generateAbsenceReport',
            'banco-horas' => 'generateBankHoursReport',
            'consolidado-mensal' => 'generateMonthlyConsolidatedReport',
            'justificativas' => 'generateJustificationsReport',
            'advertencias' => 'generateWarningsReport',
            'personalizado' => 'generateCustomReport',
        ];
    }
    protected function jsonPayload(array $data, array $filters, bool $includeMeta): array
    {
        $payload = [
            'success' => true,
            'data' => $data,
            'filters' => $filters,
        ];

        if ($includeMeta) {
            $payload['generated_at'] = date('Y-m-d H:i:s');
            $payload['total_records'] = count($data);
        }

        return [
            'success' => true,
            'kind' => 'json',
            'payload' => $payload,
        ];
    }
    protected function resolveDateRange(array $filters): array
    {
        return [
            $filters['start_date'] ?? date('Y-m-01'),
            $filters['end_date'] ?? date('Y-m-t'),
        ];
    }
    protected function applyEmployeeIdFilter(object $query, array $filters, string $column): void
    {
        if (! empty($filters['employee_ids'])) {
            $query->whereIn($column, $filters['employee_ids']);
        } elseif (! empty($filters['employee_id'])) {
            $query->where($column, (int) $filters['employee_id']);
        }
    }
    protected function applyDepartmentFilter(object $query, array $filters, string $column = 'employees.department'): void
    {
        if (! empty($filters['department'])) {
            $query->where($column, $filters['department']);
        }
    }
    protected function applyResultLimit(object $query, array $filters, int $defaultLimit = 1000): void
    {
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : $defaultLimit;
        $limit = max(1, min(5000, $limit));
        $query->limit($limit);
    }

    protected function findEmployee(int $employeeId): ?object
    {
        if (! array_key_exists($employeeId, $this->employeeCache)) {
            $this->employeeCache[$employeeId] = $this->employeeModel->find($employeeId);
        }

        return $this->employeeCache[$employeeId];
    }

}
