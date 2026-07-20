<?php

namespace App\Services\Shift;

use App\Models\EmployeeModel;
use App\Models\ScheduleModel;
use App\Models\WorkShiftModel;

class ScheduleQueryService
{
    public function __construct(
        private readonly ScheduleModel $scheduleModel = new ScheduleModel(),
        private readonly WorkShiftModel $shiftModel = new WorkShiftModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function calendarData(?string $year, ?string $month, ?string $view): array
    {
        $resolvedYear = $year ?: date('Y');
        $resolvedMonth = $month ?: date('m');
        $resolvedView = $view ?: 'month';

        $startDate = date('Y-m-d', strtotime("{$resolvedYear}-{$resolvedMonth}-01"));
        $endDate = date('Y-m-t', strtotime($startDate));

        $schedules = $this->scheduleModel->getScheduleByDateRange($startDate, $endDate);

        return [
            'year' => $resolvedYear,
            'month' => $resolvedMonth,
            'view' => $resolvedView,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'schedules' => $schedules,
            'shifts' => $this->shiftModel->where('active', true)->findAll(),
            'employees' => $this->employeeModel->getActive(),
            'calendarData' => $this->groupByDate($schedules),
        ];
    }

    public function createFormData(): array
    {
        return [
            'shifts' => $this->shiftModel->where('active', true)->findAll(),
            'employees' => $this->employeeModel->getActive(),
        ];
    }

    public function editData(int $id): ?array
    {
        $schedule = $this->scheduleModel->find($id);
        if (!$schedule || !is_object($schedule)) {
            return null;
        }

        return [
            'schedule' => $schedule,
            'shifts' => $this->shiftModel->where('active', true)->findAll(),
            'employees' => $this->employeeModel->getActive(),
        ];
    }

    public function mySchedulesData(int $employeeId, ?string $startDate, ?string $endDate): array
    {
        $resolvedStart = $startDate ?: date('Y-m-01');
        $resolvedEnd = $endDate ?: date('Y-m-t');

        $schedules = $this->scheduleModel
            ->select('schedules.*, work_shifts.name as shift_name, work_shifts.start_time, work_shifts.end_time, work_shifts.color')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id')
            ->where('schedules.employee_id', $employeeId)
            ->where('schedules.date >=', $resolvedStart)
            ->where('schedules.date <=', $resolvedEnd)
            ->orderBy('schedules.date', 'ASC')
            ->findAll();

        return [
            'schedules' => $schedules,
            'startDate' => $resolvedStart,
            'endDate' => $resolvedEnd,
        ];
    }

    private function groupByDate(array $schedules): array
    {
        $calendarData = [];

        foreach ($schedules as $schedule) {
            $date = $schedule->date;
            $calendarData[$date] ??= [];
            $calendarData[$date][] = $schedule;
        }

        return $calendarData;
    }
}
