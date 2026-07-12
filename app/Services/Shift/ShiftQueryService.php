<?php

namespace App\Services\Shift;

use App\Models\ScheduleModel;
use App\Models\WorkShiftModel;

class ShiftQueryService
{
    public function __construct(
        private readonly WorkShiftModel $shiftModel = new WorkShiftModel(),
        private readonly ScheduleModel $scheduleModel = new ScheduleModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function createFormData(): array
    {
        return ['shiftTypes' => $this->shiftTypes()];
    }

    public function indexData(array $filters): array
    {
        $type = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $search = $filters['search'] ?? null;

        $query = clone $this->shiftModel;

        if ($type && in_array($type, array_keys($this->shiftTypes()), true)) {
            $query->where('type', $type);
        }

        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        if (!empty($search)) {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('description', $search)
                ->groupEnd();
        }

        $shifts = $query
            ->orderBy('type', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->findAll();

        // Batch fetch employee counts — eliminates N+1 (was 1 query per shift)
        $shiftIds = array_column((array) $shifts, 'id');
        $countsByShift = [];
        if (!empty($shiftIds)) {
            $rows = $this->scheduleModel
                ->select('shift_id, COUNT(*) AS cnt', false)
                ->whereIn('shift_id', $shiftIds)
                ->groupBy('shift_id')
                ->findAll();
            foreach ($rows as $row) {
                $countsByShift[(int) $row->shift_id] = (int) $row->cnt;
            }
        }

        foreach ($shifts as &$shift) {
            $shift->employee_count = $countsByShift[$shift->id] ?? 0;
            $shift->duration = $this->shiftModel->calculateDuration($shift);
        }

        return [
            'shifts' => $shifts,
            'filters' => [
                'type' => $type,
                'status' => $status,
                'search' => $search,
            ],
            'statistics' => $this->overallStatistics(),
        ];
    }

    public function showData(int $id): ?array
    {
        $shift = $this->shiftModel->find($id);
        if (!$shift || !is_object($shift)) {
            return null;
        }

        return [
            'shift' => $shift,
            'duration' => $this->shiftModel->calculateDuration($shift),
            'assignedEmployees' => $this->scheduleModel->getEmployeesByShift($id),
            'statistics' => $this->shiftStatistics($id),
        ];
    }

    public function editData(int $id): ?array
    {
        $shift = $this->shiftModel->find($id);
        if (!$shift || !is_object($shift)) {
            return null;
        }

        return [
            'shift' => $shift,
            'shiftTypes' => $this->shiftTypes(),
        ];
    }

    public function statisticsData(): array
    {
        return [
            'overallStats' => $this->overallStatistics(),
            'shiftBreakdown' => $this->shiftBreakdown(),
            'coverageReport' => $this->coverageReport(),
        ];
    }

    private function shiftTypes(): array
    {
        return [
            'morning' => 'Manhã',
            'afternoon' => 'Tarde',
            'night' => 'Noite',
            'custom' => 'Personalizado',
        ];
    }

    private function shiftStatistics(int $shiftId): array
    {
        return [
            'total_schedules' => $this->scheduleModel
                ->where('shift_id', $shiftId)
                ->countAllResults(),
            'upcoming_schedules' => $this->scheduleModel
                ->where('shift_id', $shiftId)
                ->where('date >=', date('Y-m-d'))
                ->countAllResults(),
        ];
    }

    private function overallStatistics(): array
    {
        $totalShifts = $this->shiftModel->countAllResults();
        $activeShifts = $this->shiftModel->where('active', true)->countAllResults();

        $totalEmployeesScheduled = $this->scheduleModel
            ->select('employee_id')
            ->where('date >=', date('Y-m-d'))
            ->groupBy('employee_id')
            ->countAllResults();

        $upcomingSchedules = $this->scheduleModel
            ->where('date >=', date('Y-m-d'))
            ->where('date <=', date('Y-m-d', strtotime('+30 days')))
            ->countAllResults();

        return [
            'total_shifts' => $totalShifts,
            'active_shifts' => $activeShifts,
            'inactive_shifts' => $totalShifts - $activeShifts,
            'employees_scheduled' => $totalEmployeesScheduled,
            'upcoming_schedules' => $upcomingSchedules,
        ];
    }

    private function shiftBreakdown(): array
    {
        // Single GROUP BY query — eliminates 4 separate countAllResults() calls (was 1 query per type)
        $rows = $this->shiftModel
            ->select('type, COUNT(*) AS cnt', false)
            ->where('active', true)
            ->groupBy('type')
            ->findAll();

        $countsByType = [];
        foreach ($rows as $row) {
            $countsByType[$row->type] = (int) $row->cnt;
        }

        $breakdown = [];
        foreach (array_keys($this->shiftTypes()) as $type) {
            $breakdown[$type] = $countsByType[$type] ?? 0;
        }

        return $breakdown;
    }

    private function coverageReport(): array
    {
        // Build date list upfront, then fetch all counts in one query — eliminates 7 separate queries
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $dates[] = date('Y-m-d', strtotime("+{$i} days"));
        }

        $rows = $this->scheduleModel
            ->select('date, COUNT(*) AS cnt', false)
            ->whereIn('date', $dates)
            ->groupBy('date')
            ->findAll();

        $countsByDate = [];
        foreach ($rows as $row) {
            $countsByDate[$row->date] = (int) $row->cnt;
        }

        $coverage = [];
        foreach ($dates as $date) {
            $coverage[] = [
                'date' => $date,
                'day_name' => date('l', strtotime($date)),
                'scheduled_count' => $countsByDate[$date] ?? 0,
            ];
        }

        return $coverage;
    }
}
