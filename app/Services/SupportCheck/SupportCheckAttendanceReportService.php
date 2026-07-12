<?php

namespace App\Services\SupportCheck;

use App\Libraries\SupportCheck\SupportCheckApiClient;
use App\Models\EmployeeModel;
use App\Models\TimesheetConsolidatedModel;
use App\Models\TimePunchModel;
use App\Models\JustificationModel;
use App\Models\HolidayModel;

/**
 * Gera e envia o relatório mensal de ponto de um funcionário ao SupportCHECK.
 *
 * Fase 3 da integração: executado no dia 1° de cada mês para o mês anterior.
 */
class SupportCheckAttendanceReportService
{
    public function __construct(
        private readonly SupportCheckApiClient $client         = new SupportCheckApiClient(),
        private readonly EmployeeModel $employeeModel          = new EmployeeModel(),
        private readonly TimesheetConsolidatedModel $tsModel   = new TimesheetConsolidatedModel(),
        private readonly TimePunchModel $punchModel            = new TimePunchModel(),
        private readonly JustificationModel $justModel         = new JustificationModel(),
        private readonly HolidayModel $holidayModel            = new HolidayModel(),
    ) {
    }

    /**
     * Envia o relatório de ponto do mês anterior para todos os funcionários ativos.
     *
     * @return array{sent: int, failed: int, errors: array<string>}
     */
    public function sendPreviousMonthForAll(): array
    {
        [$year, $month] = $this->previousYearMonth();
        return $this->sendMonthForAll($year, $month);
    }

    /**
     * @return array{sent: int, failed: int, errors: array<string>}
     */
    public function sendMonthForAll(int $year, int $month): array
    {
        $employees = $this->employeeModel->where('active', true)->findAll();

        $sent   = 0;
        $failed = 0;
        $errors = [];

        foreach ($employees as $employee) {
            try {
                $this->sendMonthForEmployee((int) $employee->id, $year, $month);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = 'Funcionário #' . $employee->id . ' (' . $employee->name . '): ' . $e->getMessage();
            }
        }

        return compact('sent', 'failed', 'errors');
    }

    public function sendMonthForEmployee(int $employeeId, int $year, int $month): array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (! $employee) {
            throw new \RuntimeException('Funcionário #' . $employeeId . ' não encontrado.');
        }

        $periodStart = sprintf('%04d-%02d-01', $year, $month);
        $periodEnd   = date('Y-m-t', strtotime($periodStart));

        // Busca consolidações do mês
        $rows = $this->tsModel
            ->where('employee_id', $employeeId)
            ->where('date >=', $periodStart)
            ->where('date <=', $periodEnd)
            ->orderBy('date', 'ASC')
            ->findAll();

        // Feriados do período para classificar dias
        $holidays = $this->fetchHolidayDates($periodStart, $periodEnd);

        // Justificativas indexadas por data
        $justifications = $this->fetchJustifications($employeeId, $periodStart, $periodEnd);

        // Pontos indexados por data
        $punchesByDate = $this->fetchPunchesByDate($employeeId, $periodStart, $periodEnd);

        // Monta entradas diárias
        $entries       = [];
        $workedDays    = 0;
        $expectedDays  = 0;
        $totalWorked   = 0.0;
        $totalExpected = 0.0;
        $extraBalance  = 0.0;
        $owedBalance   = 0.0;
        $nightTotal    = 0.0;
        $intervalTotal = 0.0;

        $current = $periodStart;
        while ($current <= $periodEnd) {
            $dayOfWeek = (int) date('N', strtotime($current)); // 1=Mon 7=Sun
            $isWeekend = $dayOfWeek >= 6;
            $isHoliday = in_array($current, $holidays, true);

            // Busca linha consolidada para este dia
            $row = null;
            foreach ($rows as $r) {
                if (substr((string) $r->date, 0, 10) === $current) {
                    $row = $r;
                    break;
                }
            }

            $dayPunches  = $punchesByDate[$current] ?? [];
            $justification = $justifications[$current] ?? null;

            // Classifica status do dia
            if ($isWeekend) {
                $dayStatus = 'weekend';
            } elseif ($isHoliday) {
                $dayStatus = 'holiday';
            } elseif ($justification !== null) {
                $dayStatus = 'justified';
            } elseif ($row === null || ((float) ($row->total_worked ?? 0)) < 0.01) {
                $dayStatus = 'absent';
            } else {
                $dayStatus = 'normal';
            }

            $worked   = (float) ($row->total_worked ?? 0);
            $expected = (float) ($row->expected ?? 0);
            $extra    = (float) ($row->extra ?? 0);
            $owed     = (float) ($row->owed ?? 0);
            $interval = (float) ($row->total_interval ?? 0);

            if (! $isWeekend && ! $isHoliday) {
                $expectedDays++;
                if ($worked > 0.01) {
                    $workedDays++;
                }
            }

            $totalWorked   += $worked;
            $totalExpected += $expected;
            $extraBalance  += $extra;
            $owedBalance   += $owed;
            $intervalTotal += $interval;

            // Monta punches para o payload
            $punchList = [];
            foreach ($dayPunches as $punch) {
                $punchList[] = [
                    'time'   => date('H:i:s', strtotime((string) $punch->punch_time)),
                    'type'   => $punch->punch_type ?? 'entrada',
                    'method' => $punch->method ?? 'manual',
                ];
            }

            $entries[] = [
                'work_date'       => $current,
                'first_punch'     => $row ? ($row->first_punch ? substr((string) $row->first_punch, 0, 8) : null) : null,
                'last_punch'      => $row ? ($row->last_punch ? substr((string) $row->last_punch, 0, 8) : null) : null,
                'punches'         => $punchList ?: null,
                'worked_hours'    => round($worked, 2),
                'expected_hours'  => round($expected, 2),
                'extra_hours'     => round($extra, 2),
                'owed_hours'      => round($owed, 2),
                'night_hours'     => 0,
                'interval_hours'  => round($interval, 2),
                'punch_pairs'     => (int) ($row->punches_count ?? count($dayPunches)),
                'day_status'      => $dayStatus,
                'within_geofence' => null,
                'justification'   => $justification ? ($justification->description ?? null) : null,
                'notes'           => $row ? ($row->notes ?? null) : null,
            ];

            $current = date('Y-m-d', strtotime($current . ' +1 day'));
        }

        $payload = [
            'external_employee_id' => (string) $employee->id,
            'employee_name'        => $employee->name,
            'employee_cpf'         => $employee->cpf ?? null,
            'employee_email'       => $employee->email ?? null,
            'position_name'        => $employee->position ?? null,
            'department_name'      => $employee->department ?? null,
            'work_unit_name'       => $employee->work_unit ?? null,
            'reference_year'       => $year,
            'reference_month'      => $month,
            'period_start'         => $periodStart,
            'period_end'           => $periodEnd,
            'worked_days'          => $workedDays,
            'expected_days'        => $expectedDays,
            'total_worked_hours'   => round($totalWorked, 2),
            'total_expected_hours' => round($totalExpected, 2),
            'extra_hours_balance'  => round($extraBalance, 2),
            'owed_hours_balance'   => round($owedBalance, 2),
            'night_hours_total'    => round($nightTotal, 2),
            'interval_hours_total' => round($intervalTotal, 2),
            'sent_from_version'    => defined('APP_VERSION') ? APP_VERSION : null,
            'generated_at'         => date('Y-m-d H:i:s'),
            'entries'              => $entries,
        ];

        return $this->client->sendAttendanceReport($payload);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** @return array<int, string> */
    private function fetchHolidayDates(string $from, string $to): array
    {
        try {
            $rows = $this->holidayModel
                ->where('date >=', $from)
                ->where('date <=', $to)
                ->findAll();
            return array_map(fn ($r) => substr((string) $r->date, 0, 10), $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array<string, object> */
    private function fetchJustifications(int $employeeId, string $from, string $to): array
    {
        $rows = $this->justModel
            ->where('employee_id', $employeeId)
            ->where('date >=', $from)
            ->where('date <=', $to)
            ->findAll();

        $indexed = [];
        foreach ($rows as $j) {
            $indexed[substr((string) $j->date, 0, 10)] = $j;
        }
        return $indexed;
    }

    /** @return array<string, array<object>> */
    private function fetchPunchesByDate(int $employeeId, string $from, string $to): array
    {
        [$rangeStart, $rangeEnd] = $this->punchModel->getDateRangeBounds($from, $to);

        $punches = $this->punchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStart)
            ->where('punch_time <', $rangeEnd)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $indexed = [];
        foreach ($punches as $punch) {
            $date = date('Y-m-d', strtotime((string) $punch->punch_time));
            $indexed[$date][] = $punch;
        }
        return $indexed;
    }

    /** @return array{0: int, 1: int} */
    private function previousYearMonth(): array
    {
        $ts = strtotime('first day of last month');
        return [(int) date('Y', $ts), (int) date('n', $ts)];
    }
}