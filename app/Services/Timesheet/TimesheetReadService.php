<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeModel;
use App\Models\TimePunchModel;
use App\Models\TimesheetConsolidatedModel;
use App\Enums\Role;

class TimesheetReadService
{
    private TimesheetComputationService $computationService;

    public function __construct(
        protected EmployeeModel $employeeModel,
        protected TimePunchModel $timePunchModel,
        protected TimesheetConsolidatedModel $consolidatedModel,
        ?TimesheetComputationService $computationService = null,
    ) {
        $this->computationService = $computationService ?? new TimesheetComputationService();
    }

    public function resolveAuthorizedEmployee(array $actor, int $targetEmployeeId): array
    {
        if ($targetEmployeeId !== (int) $actor['id'] && ! $this->canManageEmployees($actor)) {
            return ['success' => false, 'status' => 403, 'message' => 'Você não tem permissão para visualizar dados de outros funcionários.'];
        }

        $viewingEmployee = $this->employeeModel->find($targetEmployeeId);
        if (! $viewingEmployee) {
            return ['success' => false, 'status' => 404, 'message' => 'Funcionário não encontrado.'];
        }

        if ($this->hasDepartmentScope($actor) && $viewingEmployee->department !== ($actor['department'] ?? null)) {
            return ['success' => false, 'status' => 403, 'message' => 'Você só pode visualizar funcionários do seu departamento.'];
        }

        return ['success' => true, 'employee' => $viewingEmployee];
    }

    public function getMonthlyTimesheetData(int $employeeId, string $selectedMonth): array
    {
        $startDate = $selectedMonth . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        $dailyRecords = [];
        $totalHours = 0.0;
        $totalExpected = 0.0;
        $totalExtra = 0.0;
        $totalOwed = 0.0;
        $lateArrivals = 0;
        $daysWorked = 0;
        $warning = null;

        if (! $this->consolidatedModel->tableExists()) {
            $warning = 'Dados carregados diretamente (consolidação não disponível).';
            $grouped = $this->punchesGroupedByDate($employeeId, $startDate, $endDate);

            foreach ($grouped as $date => $dayPunches) {
                $calculation = $this->computationService->calculateDailyHours($dayPunches);
                $slots = $this->extractPunchSlots($dayPunches);
                $workedHours = (float) ($calculation['total_hours'] ?? 0);

                $totalHours += $workedHours;
                if ($workedHours > 0) {
                    $daysWorked++;
                }

                $isLate = $this->isLateArrival($slots['entrada']);
                if ($isLate) {
                    $lateArrivals++;
                }

                $dailyRecords[] = $this->buildDailyRecord(
                    $date,
                    $slots,
                    $workedHours,
                    0.0,
                    ! ($slots['entrada'] && $slots['saida']),
                    $isLate
                );
            }
        } else {
            $records = $this->consolidatedModel
                ->where('employee_id', $employeeId)
                ->where('date >=', $startDate)
                ->where('date <=', $endDate)
                ->orderBy('date', 'ASC')
                ->findAll();

            // Batch-fetch all punches for the month in one query — eliminates N+1
            // (was 1 query per consolidated record via punchesForDay)
            $allPunchesByDate = !empty($records)
                ? $this->punchesGroupedByDate($employeeId, $startDate, $endDate)
                : [];

            foreach ($records as $record) {
                $totalHours += (float) ($record->total_worked ?? 0);
                $totalExpected += (float) ($record->expected ?? 0);
                $totalExtra += (float) ($record->extra ?? 0);
                $totalOwed += (float) ($record->owed ?? 0);

                if ((float) ($record->total_worked ?? 0) > 0) {
                    $daysWorked++;
                }

                $dayPunches = $allPunchesByDate[(string) $record->date] ?? [];
                $slots = $this->extractPunchSlots($dayPunches);
                $isLate = $this->isLateArrival($slots['entrada']);
                if ($isLate) {
                    $lateArrivals++;
                }

                $dailyRecords[] = $this->buildDailyRecord(
                    (string) $record->date,
                    $slots,
                    (float) $record->total_worked,
                    (float) ($record->extra - $record->owed),
                    (bool) ($record->incomplete ?? false),
                    $isLate
                );
            }
        }

        return [
            'dailyRecords' => $dailyRecords,
            'summary' => [
                'total_hours' => number_format($totalHours, 2),
                'expected_hours' => number_format($totalExpected, 2),
                'balance' => $totalExtra - $totalOwed,
                'balance_formatted' => number_format(abs($totalExtra - $totalOwed), 2),
                'days_worked' => $daysWorked,
                'expected_days' => 22,
                'late_arrivals' => $lateArrivals,
            ],
            'warning' => $warning,
        ];
    }

    public function getHistoryData(int $employeeId, array $filters): array
    {
        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($filters['start_date'], $filters['end_date']);

        $query = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt);

        if (! empty($filters['type'])) {
            $query->where('punch_type', $filters['type']);
        }

        if (! empty($filters['method'])) {
            $query->where('method', $filters['method']);
        }

        $punches = $query->orderBy('punch_time', 'DESC')->findAll();
        $grouped = [];
        foreach ($punches as $punch) {
            $grouped[date('Y-m-d', strtotime((string) $punch->punch_time))][] = $punch;
        }

        $totalHours = 0.0;
        $missingPunches = 0;
        foreach ($grouped as $dayPunches) {
            $calculation = $this->computationService->calculateDailyHours($dayPunches);
            $validation = $this->computationService->validatePunchPairs($dayPunches);
            $totalHours += (float) ($calculation['total_hours'] ?? 0);
            if (! ($validation['valid'] ?? false)) {
                $missingPunches++;
            }
        }

        return [
            'punches' => $punches,
            'summary' => [
                'total_days' => count($grouped),
                'total_hours' => number_format($totalHours, 2),
                'missing_punches' => $missingPunches,
            ],
            'balance' => ['extra' => 0.0, 'owed' => 0.0, 'balance' => 0.0],
        ];
    }

    public function getCurrentMonthKpis(int $employeeId): array
    {
        if (! $this->consolidatedModel->tableExists()) {
            return ['total_horas' => '0.00', 'faltas' => 0, 'dias_trabalhados' => 0];
        }

        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');

        $records = $this->consolidatedModel
            ->where('employee_id', $employeeId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->findAll();

        $totalHoras = 0.0;
        $faltas = 0;
        $diasTrabalhados = 0;

        foreach ($records as $record) {
            $worked = (float) ($record->total_worked ?? 0);
            $totalHoras += $worked;
            if ($worked > 0) {
                $diasTrabalhados++;
            }
            if ($worked === 0.0 && ! in_array(date('N', strtotime((string) $record->date)), [6, 7], true)) {
                $faltas++;
            }
        }

        return [
            'total_horas' => number_format($totalHoras, 2),
            'faltas' => $faltas,
            'dias_trabalhados' => $diasTrabalhados,
        ];
    }

    public function getFormattedRecords(int $employeeId, ?string $date = null): array
    {
        if (! $this->consolidatedModel->tableExists()) {
            return [];
        }

        $query = $this->consolidatedModel->where('employee_id', $employeeId);
        if (! empty($date)) {
            $query->where('date', $date);
        }

        $records = $query->orderBy('date', 'DESC')->findAll();
        $formatted = [];

        // Batch-fetch all punches for the relevant dates in one query — eliminates N+1
        // (was 1 query per record via punchesForDay when no specific date is given)
        $allPunchesByDate = [];
        if (!empty($records)) {
            $dates = array_map(static fn($r) => (string) $r->date, $records);
            $minDate = min($dates);
            $maxDate = max($dates);
            $allPunchesByDate = $this->punchesGroupedByDate($employeeId, $minDate, $maxDate);
        }

        foreach ($records as $record) {
            $slots = $this->extractPunchSlots($allPunchesByDate[(string) $record->date] ?? []);
            $formatted[] = [
                'date' => $record->date,
                'date_formatted' => date('d/m/Y', strtotime((string) $record->date)),
                'day_of_week' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][date('w', strtotime((string) $record->date))],
                'entrada' => $slots['entrada'],
                'inicio_intervalo' => $slots['inicio_intervalo'],
                'fim_intervalo' => $slots['fim_intervalo'],
                'saida' => $slots['saida'],
                'total_hours' => number_format((float) $record->total_worked, 2),
                'missing_punches' => (bool) ($record->incomplete ?? false),
            ];
        }

        return $formatted;
    }

    protected function buildDailyRecord(string $date, array $slots, float $totalHours, float $balance, bool $missingPunches, bool $late): array
    {
        return [
            'date' => $date,
            'date_formatted' => date('d/m/Y', strtotime($date)),
            'day_of_week' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'][date('w', strtotime($date))],
            'entrada' => $slots['entrada'],
            'entrada_late' => $late,
            'inicio_intervalo' => $slots['inicio_intervalo'],
            'fim_intervalo' => $slots['fim_intervalo'],
            'saida' => $slots['saida'],
            'total_hours' => number_format($totalHours, 2),
            'balance' => $balance,
            'balance_formatted' => number_format(abs($balance), 2),
            'missing_punches' => $missingPunches,
            'is_weekend' => in_array(date('N', strtotime($date)), [6, 7], true),
            'is_holiday' => false,
            'holiday_name' => null,
        ];
    }

    protected function extractPunchSlots(array $dayPunches): array
    {
        $slots = [
            'entrada' => null,
            'inicio_intervalo' => null,
            'fim_intervalo' => null,
            'saida' => null,
        ];

        foreach ($dayPunches as $punch) {
            $type = (string) ($punch->punch_type ?? '');
            $time = date('H:i', strtotime((string) $punch->punch_time));

            if ($type === 'entrada' && $slots['entrada'] === null) {
                $slots['entrada'] = $time;
                continue;
            }

            if ($type === 'intervalo_inicio' && $slots['inicio_intervalo'] === null) {
                $slots['inicio_intervalo'] = $time;
                continue;
            }

            if ($type === 'intervalo_fim' && $slots['fim_intervalo'] === null) {
                $slots['fim_intervalo'] = $time;
                continue;
            }

            if ($type === 'saida') {
                $slots['saida'] = $time;
            }
        }

        return $slots;
    }

    protected function calculateWorkedHours(?string $entrada, ?string $inicioIntervalo, ?string $fimIntervalo, ?string $saida): float
    {
        if (! $entrada || ! $saida) {
            return 0.0;
        }

        $start = strtotime($entrada);
        $end = strtotime($saida);
        if ($end <= $start) {
            return 0.0;
        }

        $worked = ($end - $start) / 3600;
        if ($inicioIntervalo && $fimIntervalo) {
            $intervalStart = strtotime($inicioIntervalo);
            $intervalEnd = strtotime($fimIntervalo);
            if ($intervalEnd > $intervalStart) {
                $worked -= ($intervalEnd - $intervalStart) / 3600;
            }
        }

        return max(0.0, $worked);
    }

    protected function isLateArrival(?string $entrada): bool
    {
        return $entrada !== null && strtotime($entrada) > strtotime('08:10');
    }

    /** @return array<string,list<object>> */
    private function punchesGroupedByDate(int $employeeId, string $startDate, string $endDate): array
    {
        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);
        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($punches as $punch) {
            $grouped[date('Y-m-d', strtotime((string) $punch->punch_time))][] = $punch;
        }

        return $grouped;
    }

    /** @return list<object> */
    private function punchesForDay(int $employeeId, string $date): array
    {
        [$dayStartAt, $dayEndAt] = $this->timePunchModel->getDayBounds($date);

        return $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $dayStartAt)
            ->where('punch_time <', $dayEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();
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
