<?php

namespace App\Services;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use App\Services\Timesheet\TimesheetAttendanceService;
use App\Services\Timesheet\TimesheetComputationService;

/**
 * Timesheet Service (facade)
 *
 * Mantém contrato histórico para controllers/reports, delegando cálculos
 * e regras específicas para serviços coesos por caso de uso.
 */
class TimesheetService
{
    protected TimePunchModel $timePunchModel;
    protected EmployeeModel $employeeModel;
    protected JustificationModel $justificationModel;
    protected SettingModel $settingModel;
    protected TimesheetComputationService $computationService;
    protected TimesheetAttendanceService $attendanceService;

    public function __construct()
    {
        $this->timePunchModel = new TimePunchModel();
        $this->employeeModel = new EmployeeModel();
        $this->justificationModel = new JustificationModel();
        $this->settingModel = new SettingModel();

        $this->computationService = new TimesheetComputationService();
        $this->attendanceService = new TimesheetAttendanceService(
            $this->timePunchModel,
            $this->employeeModel,
            $this->justificationModel,
            $this->settingModel,
        );
    }

    public function calculateHoursWorked(int $employeeId, string $startDate, string $endDate): array
    {
        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);
        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $punchesByDate = [];
        foreach ($punches as $punch) {
            $date = date('Y-m-d', strtotime($punch->punch_time));
            $punchesByDate[$date][] = $punch;
        }

        $dailyHours = [];
        $totalHours = 0.0;
        $totalDays = 0;

        foreach ($punchesByDate as $date => $dayPunches) {
            $hours = $this->calculateDailyHours($dayPunches);
            $dailyHours[$date] = $hours;
            $totalHours += $hours['total_hours'];
            if ($hours['total_hours'] > 0) {
                $totalDays++;
            }
        }

        $employee = $this->employeeModel->find($employeeId);
        $expectedDailyHours = (float) ($employee->daily_hours ?? 8.00);
        $expectedTotalHours = $expectedDailyHours * $totalDays;

        return [
            'employee_id' => $employeeId,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_hours' => round($totalHours, 2),
            'total_days' => $totalDays,
            'average_hours_per_day' => $totalDays > 0 ? round($totalHours / $totalDays, 2) : 0,
            'expected_hours' => round($expectedTotalHours, 2),
            'balance' => round($totalHours - $expectedTotalHours, 2),
            'daily_breakdown' => $dailyHours,
        ];
    }

    public function calculateDailyHours(array $punches): array
    {
        return $this->computationService->calculateDailyHours($punches);
    }

    public function validatePunchPairs(array $punches): array
    {
        return $this->computationService->validatePunchPairs($punches);
    }

    public function generateMonthlyTimesheet(int $employeeId, string $month): array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return ['success' => false, 'error' => 'Funcionário não encontrado.'];
        }

        $monthRange = get_month_datetime_range($month);
        $startDate = $monthRange['start_date'];
        $endDate = $monthRange['end_date'];

        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);
        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $punchesByDate = [];
        foreach ($punches as $punch) {
            $date = date('Y-m-d', strtotime($punch->punch_time));
            $punchesByDate[$date][] = $punch;
        }

        $justifications = $this->justificationModel
            ->where('employee_id', $employeeId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->findAll();

        $justificationsByDate = [];
        foreach ($justifications as $justification) {
            $justificationsByDate[$justification->date][] = $justification;
        }

        $dailyRecords = [];
        $currentDate = $startDate;
        while ($currentDate <= $endDate) {
            $dayPunches = $punchesByDate[$currentDate] ?? [];
            $dailyHours = $this->calculateDailyHours($dayPunches);

            $dailyRecords[] = [
                'date' => $currentDate,
                'day_of_week' => date('l', strtotime($currentDate)),
                'punches' => $dailyHours['punches'],
                'hours_worked' => $dailyHours['total_hours'],
                'expected_hours' => $employee->daily_hours,
                'balance' => round($dailyHours['total_hours'] - $employee->daily_hours, 2),
                'justifications' => $justificationsByDate[$currentDate] ?? [],
                'validation' => $this->validatePunchPairs($dayPunches),
            ];

            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }

        $hoursCalculation = $this->calculateHoursWorked($employeeId, $startDate, $endDate);

        return [
            'success' => true,
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'cpf' => $employee->cpf,
                'position' => $employee->position,
                'department' => $employee->department,
            ],
            'period' => ['month' => $month, 'start_date' => $startDate, 'end_date' => $endDate],
            'summary' => [
                'total_hours' => $hoursCalculation['total_hours'],
                'expected_hours' => $hoursCalculation['expected_hours'],
                'balance' => $hoursCalculation['balance'],
                'days_worked' => $hoursCalculation['total_days'],
                'total_punches' => count($punches),
                'nsr_range' => $this->computationService->getNSRRange($punches),
            ],
            'daily_records' => $dailyRecords,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function findMissingPunches(int $employeeId, string $startDate, string $endDate): array
    {
        return $this->attendanceService->findMissingPunches($employeeId, $startDate, $endDate);
    }

    public function findLateArrivals(int $employeeId, string $startDate, string $endDate): array
    {
        return $this->attendanceService->findLateArrivals($employeeId, $startDate, $endDate);
    }

    public function calculateOvertime(int $employeeId, string $startDate, string $endDate): array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return [];
        }

        $calculation = $this->calculateHoursWorked($employeeId, $startDate, $endDate);
        $dailyOvertime = [];
        $totalOvertime = 0.0;

        foreach ($calculation['daily_breakdown'] as $date => $hours) {
            $overtime = $hours['total_hours'] - $employee->daily_hours;
            if ($overtime > 0) {
                $dailyOvertime[] = [
                    'date' => $date,
                    'hours_worked' => $hours['total_hours'],
                    'expected_hours' => $employee->daily_hours,
                    'overtime_hours' => round($overtime, 2),
                ];
                $totalOvertime += $overtime;
            }
        }

        return ['total_overtime' => round($totalOvertime, 2), 'daily_overtime' => $dailyOvertime];
    }

    public function getStatistics(): array
    {
        $today = date('Y-m-d');
        $thisMonth = date('Y-m');

        [$todayStartAt, $todayEndAt] = $this->timePunchModel->getDayBounds($today);
        [$monthStartAt, $monthEndAt] = $this->timePunchModel->getDateRangeBounds($thisMonth . '-01', date('Y-m-t'));

        return [
            'punches_today' => $this->timePunchModel->where('punch_time >=', $todayStartAt)->where('punch_time <', $todayEndAt)->countAllResults(),
            'punches_this_month' => $this->timePunchModel->where('punch_time >=', $monthStartAt)->where('punch_time <', $monthEndAt)->countAllResults(),
            'total_punches' => $this->timePunchModel->countAllResults(),
        ];
    }

    public function countLateArrivals(int $employeeId, string $startDate, string $endDate): int
    {
        return $this->attendanceService->countLateArrivals($employeeId, $startDate, $endDate);
    }

    public function countAbsences(int $employeeId, string $startDate, string $endDate): int
    {
        return $this->attendanceService->countAbsences($employeeId, $startDate, $endDate);
    }
}
