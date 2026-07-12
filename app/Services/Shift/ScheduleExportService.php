<?php

namespace App\Services\Shift;

use App\Models\ScheduleModel;

class ScheduleExportService
{
    public function __construct(private readonly ScheduleModel $scheduleModel = new ScheduleModel())
    {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function csvData(?string $startDate, ?string $endDate): array
    {
        $resolvedStart = $startDate ?: date('Y-m-01');
        $resolvedEnd = $endDate ?: date('Y-m-t');

        $schedules = $this->scheduleModel
            ->select('schedules.*, employees.name as employee_name, work_shifts.name as shift_name, work_shifts.start_time, work_shifts.end_time')
            ->join('employees', 'employees.id = schedules.employee_id')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id')
            ->where('schedules.date >=', $resolvedStart)
            ->where('schedules.date <=', $resolvedEnd)
            ->orderBy('schedules.date', 'ASC')
            ->orderBy('employees.name', 'ASC')
            ->findAll();

        return [
            'filename' => "escalas_{$resolvedStart}_to_{$resolvedEnd}.csv",
            'schedules' => $schedules,
        ];
    }

    public function translateStatus(string $status): string
    {
        return [
            'scheduled' => 'Agendado',
            'completed' => 'Concluído',
            'cancelled' => 'Cancelado',
            'absent' => 'Ausente',
        ][$status] ?? $status;
    }
}
