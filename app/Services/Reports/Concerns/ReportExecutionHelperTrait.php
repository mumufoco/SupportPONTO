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
        $startDate = (string) ($filters['start_date'] ?? date('Y-m-01'));
        $endDate   = (string) ($filters['end_date'] ?? date('Y-m-t'));

        // `start_date`/`end_date` normalmente chegam como datas "puras" (AAAA-MM-DD). Comparar
        // diretamente uma timestamp como '2026-06-07 12:00:05' com '<= 2026-06-07' a excluiria
        // (pois o banco trata a data como meia-noite) — por isso ancoramos os limites no
        // início e no fim do dia quando nenhum horário foi informado explicitamente. Mesma
        // convenção já usada em TXTService::fetchClockAdjustmentsForAfd() e afins.
        if (strlen($startDate) <= 10) {
            $startDate .= ' 00:00:00';
        }
        if (strlen($endDate) <= 10) {
            $endDate .= ' 23:59:59';
        }

        return [$startDate, $endDate];
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

    /**
     * Limite de resultados para o AFD (Portaria MTE 671/2021). Ao contrário de
     * applyResultLimit() — usado pelos relatórios de tela, onde truncar silenciosamente é
     * aceitável — o AFD é um arquivo de conformidade legal entregue à fiscalização e não pode
     * omitir registros do período solicitado sem que isso seja explícito. Por isso só aplicamos
     * limite quando o chamador pede um explicitamente (ex.: pré-visualização), e nunca um teto
     * implícito de 1000/5000 registros.
     */
    protected function applyAfdResultLimit(object $query, array $filters): void
    {
        if (! isset($filters['limit'])) {
            return;
        }

        $limit = max(1, min(50000, (int) $filters['limit']));
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
