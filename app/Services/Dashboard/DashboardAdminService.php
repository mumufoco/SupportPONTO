<?php

namespace App\Services\Dashboard;

use App\Models\AuditModel;
use App\Services\Dashboard\Presenters\AdminDashboardViewPresenter;
use App\Support\Dates\DashboardDateRange;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\TimePunchModel;
use App\Models\WarningModel;

class DashboardAdminService
{
    private readonly EmployeeModel $employeeModel;
    private readonly TimePunchModel $timePunchModel;
    private readonly JustificationModel $justificationModel;
    private readonly WarningModel $warningModel;
    private readonly NotificationModel $notificationModel;
    private readonly AuditModel $auditModel;
    private readonly AdminDashboardViewPresenter $dashboardViewPresenter;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?TimePunchModel $timePunchModel = null,
        ?JustificationModel $justificationModel = null,
        ?WarningModel $warningModel = null,
        ?NotificationModel $notificationModel = null,
        ?AuditModel $auditModel = null,
        ?AdminDashboardViewPresenter $dashboardViewPresenter = null,
    ) {
        $this->employeeModel = $employeeModel ?? model(EmployeeModel::class);
        $this->timePunchModel = $timePunchModel ?? model(TimePunchModel::class);
        $this->justificationModel = $justificationModel ?? model(JustificationModel::class);
        $this->warningModel = $warningModel ?? model(WarningModel::class);
        $this->notificationModel = $notificationModel ?? model(NotificationModel::class);
        $this->auditModel = $auditModel ?? model(AuditModel::class);
        $this->dashboardViewPresenter = $dashboardViewPresenter ?? new AdminDashboardViewPresenter();
    }

    public function buildViewData(object|array|null $currentUser): array
    {
        $userId = (int) $this->userValue($currentUser, 'id', 0);

        $viewData = [
            'currentUser' => $currentUser,
            'statistics' => $this->statistics(),
            'pendingApprovals' => $this->pendingApprovals(),
            'recentActivities' => $this->recentActivities(),
            'systemAlerts' => $this->systemAlerts(),
            'notifications' => $this->userNotifications($userId),
        ];

        $viewData['dashboardPresentation'] = $this->dashboardViewPresenter->present($viewData);

        return $viewData;
    }

    private function statistics(): array
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();
        [$monthStart, $nextMonthStart] = DashboardDateRange::month();

        return [
            'total_employees' => $this->employeeModel->where('active', true)->where('role !=', 'admin')->countAllResults(),
            'total_inactive' => $this->employeeModel->where('active', false)->countAllResults(),
            'pending_registrations' => $this->employeeModel
                ->where('active', false)
                ->where('created_at >=', date('Y-m-d', strtotime('-7 days')))
                ->countAllResults(),
            'punches_today' => $this->timePunchModel
                ->where('punch_time >=', $todayStart)
                ->where('punch_time <', $tomorrowStart)
                ->countAllResults(),
            'punches_month' => $this->timePunchModel
                ->where('punch_time >=', $monthStart)
                ->where('punch_time <', $nextMonthStart)
                ->countAllResults(),
            'pending_justifications' => $this->justificationModel->where('status', 'pending')->countAllResults(),
            'active_warnings' => $this->warningModel->where('status', 'pending')->countAllResults(),
            'employees_present' => $this->employeesPresent(),
        ];
    }

    private function pendingApprovals(): array
    {
        return [
            'employees' => $this->employeeModel
                ->where('active', false)
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->find(),
            'justifications' => $this->justificationModel
                ->select('justifications.*, employees.name as employee_name')
                ->join('employees', 'employees.id = justifications.employee_id')
                ->where('justifications.status', 'pending')
                ->orderBy('justifications.created_at', 'DESC')
                ->limit(5)
                ->find(),
        ];
    }

    private function recentActivities(): array
    {
        $auditTable = $this->auditModel->getTable();

        return $this->auditModel
            ->select($auditTable . '.*, employees.name as user_name')
            ->join('employees', 'employees.id = ' . $auditTable . '.user_id', 'left')
            ->orderBy($auditTable . '.created_at', 'DESC')
            ->limit(10)
            ->find();
    }

    private function systemAlerts(): array
    {
        $alerts = [];

        $criticalErrors = $this->auditModel
            ->where('level', 'critical')
            ->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')))
            ->countAllResults();

        if ($criticalErrors > 0) {
            $alerts[] = [
                'type' => 'danger',
                'message' => lang('DashboardAdmin.alertsData.criticalErrors', [$criticalErrors]),
                'link' => '/admin/logs?level=critical',
            ];
        }

        // Admins não têm obrigação de cadastro biométrico — excluir da contagem.
        $noBiometric = $this->employeeModel
            ->where('active', true)
            ->where('role !=', 'admin')
            ->where('has_face_biometric', false)
            ->where('has_fingerprint_biometric', false)
            ->countAllResults();

        if ($noBiometric > 0) {
            $alerts[] = [
                'type' => 'warning',
                'message' => lang('DashboardAdmin.alertsData.noBiometric', [$noBiometric]),
                'link' => '/employees?filter=no_biometric',
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

    private function employeesPresent(): int
    {
        [$todayStart, $tomorrowStart] = DashboardDateRange::day();

        return $this->timePunchModel
            ->select('DISTINCT employee_id')
            ->where('punch_time >=', $todayStart)
            ->where('punch_time <', $tomorrowStart)
            ->where('punch_type', 'entrada')
            ->countAllResults();
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
