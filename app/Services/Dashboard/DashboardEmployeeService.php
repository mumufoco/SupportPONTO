<?php

namespace App\Services\Dashboard;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\TimePunchModel;
use App\Services\Dashboard\Presenters\EmployeeDashboardViewPresenter;
use App\Support\Dates\DashboardDateRange;

class DashboardEmployeeService
{
    public function __construct(
        private ?EmployeeModel $employeeModel = null,
        private ?TimePunchModel $timePunchModel = null,
        private ?JustificationModel $justificationModel = null,
        private ?NotificationModel $notificationModel = null,
        private ?EmployeeDashboardViewPresenter $dashboardViewPresenter = null,
    ) {
        $this->employeeModel ??= model(EmployeeModel::class);
        $this->timePunchModel ??= model(TimePunchModel::class);
        $this->justificationModel ??= model(JustificationModel::class);
        $this->notificationModel ??= model(NotificationModel::class);
        $this->dashboardViewPresenter ??= new EmployeeDashboardViewPresenter();
    }

    public function buildViewData(object|array|null $currentUser): array
    {
        $employeeId = (int) $this->userValue($currentUser, 'id', 0);
        $stats = $this->statistics($currentUser, $employeeId);
        $hoursBalance = $this->hoursBalance($currentUser);

        $todayPunchesRaw = $this->todayPunches($employeeId);
        $lastPunch = !empty($todayPunchesRaw) ? end($todayPunchesRaw) : null;
        $currentStatus = ($lastPunch && ($lastPunch->punch_type ?? null) === 'entrada') ? 'clocked_in' : 'clocked_out';

        $balanceNumeric = (float) ($hoursBalance['current_balance'] ?? 0);
        $balanceFormatted = ($balanceNumeric >= 0 ? '+' : '') . round($balanceNumeric, 1) . 'h';

        $viewData = [
            'currentUser' => $currentUser,
            'employeeData' => [
                'current_status' => $currentStatus,
            ],
            'employeeStats' => [
                'hours_worked_month' => round((float) $stats['hours_worked_month'], 1) . 'h',
                'balance_hours' => $balanceFormatted,
                'balance_hours_numeric' => $balanceNumeric,
                'attendance_rate' => $this->attendanceRate($employeeId),
                'pending_justifications' => $stats['pending_justifications'] ?? 0,
            ],
            'todayPunches' => $this->formatTodayPunches($todayPunchesRaw),
            'weekSummary' => $this->weekSummary($employeeId),
            'upcomingEvents' => $this->upcomingEvents(),
            'notifications' => $this->formatNotifications($this->userNotifications($employeeId)),
        ];

        $viewData['dashboardPresentation'] = $this->dashboardViewPresenter->present($viewData);

        return $viewData;
    }

    private function statistics(object|array|null $currentUser, int $employeeId): array
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();

        return [
            'punches_today' => $this->timePunchModel
                ->where('employee_id', $employeeId)
                ->where('punch_time >=', $todayStart)
                ->where('punch_time <', $tomorrowStart)
                ->countAllResults(),
            'punches_month' => $this->timePunchModel
                ->where('employee_id', $employeeId)
                ->where('punch_time >=', $monthStart)
                ->where('punch_time <', $nextMonthStart)
                ->countAllResults(),
            'hours_worked_month' => $this->hoursWorkedMonth($employeeId),
            'hours_balance' => (float) $this->userValue($currentUser, 'hours_balance', 0),
            'pending_justifications' => $this->justificationModel
                ->where('employee_id', $employeeId)
                ->where('status', 'pending')
                ->countAllResults(),
            'late_count_month' => $this->lateCountMonth($employeeId),
        ];
    }

    private function todayPunches(int $employeeId): array
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();

        return $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $todayStart)
            ->where('punch_time <', $tomorrowStart)
            ->orderBy('punch_time', 'ASC')
            ->findAll();
    }

    private function hoursBalance(object|array|null $currentUser): array
    {
        return [
            'current_balance' => (float) $this->userValue($currentUser, 'hours_balance', 0),
            'extra_hours' => (float) $this->userValue($currentUser, 'extra_hours_balance', 0),
            'owed_hours' => (float) $this->userValue($currentUser, 'owed_hours_balance', 0),
        ];
    }

    private function hoursWorkedMonth(int $employeeId): float
    {
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();

        $punches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $monthStart)
            ->where('punch_time <', $nextMonthStart)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        return $this->calculateTotalHours($punches);
    }

    private function lateCountMonth(int $employeeId): int
    {
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();
        $employee = $this->employeeModel->find($employeeId);

        if (!is_object($employee) || empty($employee->work_start_time)) {
            return 0;
        }

        return $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $monthStart)
            ->where('punch_time <', $nextMonthStart)
            ->where('punch_type', 'entrada')
            ->where("punch_time::time > '" . str_replace("'", "''", (string) $employee->work_start_time) . "'::time", null, false)
            ->countAllResults();
    }

    private function weekSummary(int $employeeId): array
    {
        $weekDays = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        $today = (int) date('N');

        // Compute the 7 dates once
        $dates = [];
        for ($i = 1; $i <= 7; $i++) {
            $dates[] = date('Y-m-d', strtotime("monday this week +{$i} days -1 day"));
        }

        $weekStart = $dates[0] . ' 00:00:00';
        $weekEnd   = date('Y-m-d H:i:s', strtotime($dates[6] . ' +1 day'));

        // Batch-fetch all punches for the week in one query — eliminates N+1 (was 1 query per day)
        $allPunches = $this->timePunchModel
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $weekStart)
            ->where('punch_time <', $weekEnd)
            ->orderBy('punch_time', 'ASC')
            ->findAll();

        // Group by date in PHP
        $punchesByDate = [];
        foreach ($allPunches as $punch) {
            $punchesByDate[substr((string) ($punch->punch_time ?? ''), 0, 10)][] = $punch;
        }

        $summary = [];
        foreach ($dates as $idx => $date) {
            $dayPunches = $punchesByDate[$date] ?? [];
            $hours = !empty($dayPunches) ? $this->calculateTotalHours($dayPunches) : 0;

            $summary[] = [
                'name'     => $weekDays[$idx],
                'hours'    => $hours > 0 ? round($hours, 1) . 'h' : '',
                'is_today' => ($idx + 1 === $today),
            ];
        }

        return $summary;
    }

    private function attendanceRate(int $employeeId): string
    {
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();
        $workDays = $this->workDaysInMonth();

        $punches = $this->timePunchModel
            ->select('punch_time')
            ->where('employee_id', $employeeId)
            ->where('punch_time >=', $monthStart)
            ->where('punch_time <', $nextMonthStart)
            ->findAll();

        $presentDays = count(array_unique(array_map(static fn($punch) => substr((string) ($punch->punch_time ?? ''), 0, 10), $punches)));

        $rate = $workDays > 0 ? round(($presentDays / $workDays) * 100) : 100;

        return $rate . '%';
    }

    private function workDaysInMonth(): int
    {
        $month = (int) date('m');
        $year = (int) date('Y');
        $daysInMonth = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
        $workDays = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayOfWeek = (int) date('N', strtotime("{$year}-{$month}-{$day}"));
            if ($dayOfWeek < 6) {
                $workDays++;
            }
        }

        return $workDays;
    }

    private function upcomingEvents(): array
    {
        return [];
    }

    private function userNotifications(int $employeeId): array
    {
        if ($employeeId <= 0) {
            return [];
        }

        return $this->notificationModel
            ->where('user_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->findAll();
    }

    private function formatTodayPunches(array $punches): array
    {
        return array_map(static fn($punch) => [
            'type' => $punch->punch_type ?? 'entrada',
            'timestamp' => $punch->punch_time ?? date('Y-m-d H:i:s'),
            'location' => $punch->location ?? null,
            'status' => 'active',
        ], $punches);
    }

    private function formatNotifications(array $notifications): array
    {
        return array_map(static fn($notification) => [
            'message' => $notification->message ?? 'Notification',
            'type' => $notification->type ?? 'info',
        ], array_slice($notifications, 0, 3));
    }

    private function calculateTotalHours(array $punches): float
    {
        $totalSeconds = 0;
        $openPunch = null;

        foreach ($punches as $punch) {
            $type = $punch->punch_type ?? null;
            $timestamp = strtotime((string) ($punch->punch_time ?? ''));
            if ($timestamp === false || $type === null) {
                continue;
            }

            if (in_array($type, ['entrada', 'intervalo_fim'], true)) {
                $openPunch = $timestamp;
                continue;
            }

            if ($openPunch !== null && in_array($type, ['saida', 'intervalo_inicio'], true)) {
                $totalSeconds += max(0, $timestamp - $openPunch);
                $openPunch = null;
            }
        }

        return round($totalSeconds / 3600, 2);
    }

    private function userValue(object|array|null $user, string $key, mixed $default = null): mixed
    {
        if (is_array($user)) {
            return $user[$key] ?? $default;
        }

        if (is_object($user)) {
            return $user->{$key} ?? $default;
        }

        return $default;
    }
}
