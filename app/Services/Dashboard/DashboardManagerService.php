<?php

namespace App\Services\Dashboard;

use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\TimePunchModel;
use App\Services\Dashboard\Presenters\ManagerDashboardViewPresenter;
use App\Support\Dates\DashboardDateRange;

class DashboardManagerService
{
    public function __construct(
        private ?EmployeeModel $employeeModel = null,
        private ?TimePunchModel $timePunchModel = null,
        private ?JustificationModel $justificationModel = null,
        private ?NotificationModel $notificationModel = null,
        private ?ManagerDashboardViewPresenter $dashboardViewPresenter = null,
    ) {
        $this->employeeModel ??= model(EmployeeModel::class);
        $this->timePunchModel ??= model(TimePunchModel::class);
        $this->justificationModel ??= model(JustificationModel::class);
        $this->notificationModel ??= model(NotificationModel::class);
        $this->dashboardViewPresenter ??= new ManagerDashboardViewPresenter();
    }

    public function buildViewData(object|array|null $currentUser): array
    {
        $department = (string) $this->userValue($currentUser, 'department', 'default');
        $userId = (int) $this->userValue($currentUser, 'id', 0);
        $stats = $this->statistics($department);

        // Reuse stats['team_size'] — eliminates duplicate findAll() + count() query
        $teamSize = $stats['team_size'] ?? 0;
        $attendanceRate = $teamSize > 0
            ? round(($stats['present_today'] / $teamSize) * 100)
            : 0;

        // Pass already-computed pending count to alerts() — eliminates duplicate countAllResults() query
        $viewData = [
            'currentUser' => $currentUser,
            'teamStats' => [
                'total_employees' => $teamSize,
                'attendance_rate' => $attendanceRate,
                'pending_approvals' => $stats['pending_justifications'] ?? 0,
                'absent_today' => $stats['absent_today'] ?? 0,
            ],
            'pendingJustifications' => $this->pendingJustifications($department),
            'teamActivity' => $this->teamActivity($department),
            'alerts' => $this->alerts($department, $stats['pending_justifications'] ?? 0),
            'notifications' => $this->userNotifications($userId),
        ];

        $viewData['dashboardPresentation'] = $this->dashboardViewPresenter->present($viewData);

        return $viewData;
    }

    private function statistics(string $department): array
    {
        $teamSize = $this->employeeModel
            ->where('department', $department)
            ->where('active', true)
            ->countAllResults();

        // Compute present count once and derive absent inline —
        // eliminates the duplicate employeesPresent() call that was in absentEmployees()
        $presentToday = $this->employeesPresent($department);

        return [
            'team_size' => $teamSize,
            'present_today' => $presentToday,
            'absent_today' => max(0, $teamSize - $presentToday),
            'late_today' => $this->lateEmployees($department),
            'pending_justifications' => $this->justificationModel
                ->join('employees', 'employees.id = justifications.employee_id')
                ->where('employees.department', $department)
                ->where('justifications.status', 'pending')
                ->countAllResults(),
            'team_hours_month' => $this->departmentHoursMonth($department),
        ];
    }

    private function pendingJustifications(string $department): array
    {
        return $this->justificationModel
            ->select('justifications.*, employees.name as employee_name, employees.position')
            ->join('employees', 'employees.id = justifications.employee_id')
            ->where('employees.department', $department)
            ->where('justifications.status', 'pending')
            ->orderBy('justifications.created_at', 'ASC')
            ->limit(10)
            ->find();
    }

    private function teamActivity(string $department): array
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();

        $activities = $this->timePunchModel
            ->select('time_punches.*, employees.name as employee_name')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('employees.department', $department)
            ->where('time_punches.punch_time >=', $todayStart)
            ->where('time_punches.punch_time <', $tomorrowStart)
            ->orderBy('time_punches.punch_time', 'DESC')
            ->limit(10)
            ->findAll();

        return array_map(fn($activity) => [
            'employee_name' => $activity->employee_name ?? lang('DashboardManager.common.missingText'),
            'action' => $this->formatPunchAction($activity->punch_type ?? 'entrada'),
            'timestamp' => $activity->punch_time ?? '',
            'status' => 'active',
        ], $activities);
    }

    private function alerts(string $department, int $pendingCount = 0): array
    {
        // $pendingCount is passed in from statistics() — eliminates duplicate countAllResults() query
        $alerts = [];

        if ($pendingCount > 5) {
            $alerts[] = [
                'message' => lang('DashboardManager.alerts.pendingApprovalsMessage', [$pendingCount]),
                'type' => 'warning',
            ];
        }

        return $alerts;
    }

    private function userNotifications(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return $this->notificationModel
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(10)
            ->findAll();
    }

    private function employeesPresent(string $department): int
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();

        return $this->timePunchModel
            ->select('DISTINCT employee_id')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('employees.department', $department)
            ->where('punch_time >=', $todayStart)
            ->where('punch_time <', $tomorrowStart)
            ->where('punch_type', 'entrada')
            ->countAllResults();
    }

    private function lateEmployees(string $department): int
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();

        return $this->timePunchModel
            ->select('DISTINCT time_punches.employee_id')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('employees.department', $department)
            ->where('time_punches.punch_time >=', $todayStart)
            ->where('time_punches.punch_time <', $tomorrowStart)
            ->where('time_punches.punch_type', 'entrada')
            ->where("time_punches.punch_time::time > employees.work_start_time", null, false)
            ->countAllResults();
    }

    private function departmentHoursMonth(string $department): float
    {
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();

        $punches = $this->timePunchModel
            ->select('time_punches.*')
            ->join('employees', 'employees.id = time_punches.employee_id')
            ->where('employees.department', $department)
            ->where('time_punches.punch_time >=', $monthStart)
            ->where('time_punches.punch_time <', $nextMonthStart)
            ->orderBy('time_punches.punch_time', 'ASC')
            ->findAll();

        return $this->calculateTotalHours($punches);
    }

    private function formatPunchAction(string $punchType): string
    {
        $actions = [
            'entrada' => lang('DashboardManager.punchActions.entrada'),
            'saida' => lang('DashboardManager.punchActions.saida'),
            'intervalo_inicio' => lang('DashboardManager.punchActions.intervalo_inicio'),
            'intervalo_fim' => lang('DashboardManager.punchActions.intervalo_fim'),
        ];

        return $actions[$punchType] ?? lang('DashboardManager.punchActions.default');
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
