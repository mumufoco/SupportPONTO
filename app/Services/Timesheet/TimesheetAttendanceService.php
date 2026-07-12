<?php

namespace App\Services\Timesheet;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;

class TimesheetAttendanceService
{
    public function __construct(
        protected TimePunchModel $timePunchModel,
        protected EmployeeModel $employeeModel,
        protected JustificationModel $justificationModel,
        protected SettingModel $settingModel,
    ) {
    }

    public function findMissingPunches(int $employeeId, string $startDate, string $endDate): array
    {
        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);

        $punchDates = [];
        foreach ($this->timePunchModel
            ->select('punch_time')
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->findAll() as $punch) {
            $punchDates[date('Y-m-d', strtotime((string) $punch->punch_time))] = true;
        }
        $punchDates = array_keys($punchDates);

        // Batch fetch all justifications for the range — eliminates N+1 (was 1 query per missing date)
        $justificationsByDate = [];
        $rangeJustifications = $this->justificationModel
            ->where('employee_id', $employeeId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->findAll();
        foreach ($rangeJustifications as $j) {
            $justificationsByDate[(string) $j->date] = $j;
        }

        $missingDates = [];
        $currentDate = $startDate;

        while ($currentDate <= $endDate) {
            if ((int) date('N', strtotime($currentDate)) <= 5 && !in_array($currentDate, $punchDates, true)) {
                $justification = $justificationsByDate[$currentDate] ?? null;

                $missingDates[] = [
                    'date' => $currentDate,
                    'day_of_week' => date('l', strtotime($currentDate)),
                    'has_justification' => $justification !== null,
                    'justification' => $justification,
                ];
            }

            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }

        return $missingDates;
    }

    public function findLateArrivals(int $employeeId, string $startDate, string $endDate): array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee || !$employee->work_start_time) {
            return [];
        }

        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);
        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_type', 'entrada')
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $toleranceMinutes = (int) $this->settingModel->get('late_tolerance_minutes', 10);
        $lateArrivals = [];

        foreach ($punches as $punch) {
            $punchTime = date('H:i:s', strtotime($punch->punch_time));
            $expectedTime = $employee->work_start_time;
            $expectedWithTolerance = date('H:i:s', strtotime($expectedTime) + ($toleranceMinutes * 60));

            if ($punchTime > $expectedWithTolerance) {
                $lateArrivals[] = [
                    'date' => date('Y-m-d', strtotime($punch->punch_time)),
                    'punch_time' => $punchTime,
                    'expected_time' => $expectedTime,
                    'minutes_late' => round((strtotime($punchTime) - strtotime($expectedTime)) / 60, 0),
                    'punch' => $punch,
                ];
            }
        }

        return $lateArrivals;
    }

    public function countLateArrivals(int $employeeId, string $startDate, string $endDate): int
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee || !$employee->work_schedule_start) {
            return 0;
        }

        $toleranceMinutes = $this->settingModel->where('key', 'tolerance_minutes_late')->first()?->value ?? 10;
        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);
        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_type', 'entrada')
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        $lateCount = 0;
        foreach ($punches as $punch) {
            $punchDate = date('Y-m-d', strtotime($punch->punch_time));
            $punchTime = date('H:i:s', strtotime($punch->punch_time));
            $scheduledStart = new \DateTime($punchDate . ' ' . $employee->work_schedule_start);
            $scheduledStart->modify("+{$toleranceMinutes} minutes");

            if ($punchTime > $scheduledStart->format('H:i:s')) {
                $lateCount++;
            }
        }

        return $lateCount;
    }

    public function countAbsences(int $employeeId, string $startDate, string $endDate): int
    {
        $justifications = $this->justificationModel
            ->where('employee_id', $employeeId)
            ->where('justification_date >=', $startDate)
            ->where('justification_date <=', $endDate)
            ->where('status', 'approved')
            ->whereIn('type', ['absence', 'medical_leave', 'vacation'])
            ->countAllResults();

        if (! $this->employeeModel->find($employeeId)) {
            return $justifications;
        }

        [$rangeStartAt, $rangeEndAt] = $this->timePunchModel->getDateRangeBounds($startDate, $endDate);

        $punchDatesList = [];
        foreach ($this->timePunchModel
            ->select('punch_time')
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $rangeStartAt)
            ->where('punch_time <', $rangeEndAt)
            ->findAll() as $punch) {
            $punchDatesList[date('Y-m-d', strtotime((string) $punch->punch_time))] = true;
        }
        $punchDatesList = array_keys($punchDatesList);

        $justifiedDatesList = array_map(static fn($j) => $j->justification_date, $this->justificationModel
            ->select('justification_date')
            ->where('employee_id', $employeeId)
            ->where('justification_date >=', $startDate)
            ->where('justification_date <=', $endDate)
            ->where('status', 'approved')
            ->findAll());

        $currentDate = new \DateTime($startDate);
        $endDateTime = new \DateTime($endDate);
        $unjustifiedAbsences = 0;

        while ($currentDate <= $endDateTime) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = (int) $currentDate->format('N');

            if ($dayOfWeek < 6 && !in_array($dateStr, $punchDatesList, true) && !in_array($dateStr, $justifiedDatesList, true)) {
                $unjustifiedAbsences++;
            }

            $currentDate->modify('+1 day');
        }

        return $justifications + $unjustifiedAbsences;
    }
}
