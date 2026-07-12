<?php

namespace App\Services\Dashboard\Presenters;

class EmployeeDashboardViewPresenter extends AbstractDashboardViewPresenter
{
    public function present(array $viewData): array
    {
        $currentUser = $viewData['currentUser'] ?? null;
        $employeeData = $this->asMap($viewData['employeeData'] ?? []);
        $employeeStats = $this->asMap($viewData['employeeStats'] ?? []);

        $userName = $this->text(
            $this->value($currentUser, 'user_name') ?? $this->value($currentUser, 'name'),
            lang('DashboardEmployee.common.defaultUser')
        );
        $currentStatus = $this->text($employeeData['current_status'] ?? null, 'clocked_out');
        $isClockedIn = $currentStatus === 'clocked_in';
        $balanceNumeric = $this->floatValue($employeeStats['balance_hours_numeric'] ?? 0);
        $pendingJustifications = $this->intValue($employeeStats['pending_justifications'] ?? 0);

        $presentation = [
            'pageHeader' => $this->normalizePageHeader([
                'actions' => [
                    ['label' => lang('DashboardEmployee.actions.punch'), 'icon' => 'bi bi-clock-fill', 'url' => sp_timesheet_punch_url()],
                    ['label' => lang('DashboardEmployee.actions.history'), 'icon' => 'bi bi-calendar3', 'url' => sp_timesheet_history_url()],
                    ['label' => lang('DashboardEmployee.actions.justifications'), 'icon' => 'bi bi-file-earmark-text', 'url' => sp_justifications_index_url()],
                ],
            ], lang('DashboardEmployee.title'), lang('DashboardEmployee.subtitle'), lang('DashboardEmployee.icon')),
            'hero' => [
                'title' => lang('DashboardEmployee.hero.greeting', [$userName]),
                'statusText' => $isClockedIn ? lang('DashboardEmployee.hero.clockedIn') : lang('DashboardEmployee.hero.clockedOut'),
                'statusBadgeClass' => $isClockedIn ? 'text-bg-success' : 'text-bg-secondary',
                'ctaLabel' => lang('DashboardEmployee.hero.cta'),
                'ctaUrl' => sp_timesheet_punch_url(),
            ],
            'kpis' => $this->normalizeKpis([
                [
                    'icon' => 'fas fa-clock',
                    'iconColor' => 'primary',
                    'value' => $this->text($employeeStats['hours_worked_month'] ?? null, '0h'),
                    'label' => lang('DashboardEmployee.kpis.hoursWorked.label'),
                    'indicator' => lang('DashboardEmployee.kpis.hoursWorked.indicator'),
                    'indicatorType' => 'neutral',
                ],
                [
                    'icon' => 'fas fa-balance-scale',
                    'iconColor' => $balanceNumeric >= 0 ? 'success' : 'warning',
                    'value' => $this->text($employeeStats['balance_hours'] ?? null, '+0h'),
                    'label' => lang('DashboardEmployee.kpis.balance.label'),
                    'indicator' => $balanceNumeric >= 0 ? lang('DashboardEmployee.kpis.balance.positive') : lang('DashboardEmployee.kpis.balance.negative'),
                    'indicatorType' => $balanceNumeric >= 0 ? 'success' : 'warning',
                    'url' => sp_timesheet_balance_url(),
                ],
                [
                    'icon' => 'fas fa-chart-line',
                    'iconColor' => 'accent',
                    'value' => $this->text($employeeStats['attendance_rate'] ?? null, '100%'),
                    'label' => lang('DashboardEmployee.kpis.attendance.label'),
                    'indicator' => lang('DashboardEmployee.kpis.attendance.indicator'),
                    'indicatorType' => 'neutral',
                ],
                [
                    'icon' => 'fas fa-bell',
                    'iconColor' => 'warning',
                    'value' => (string) $pendingJustifications,
                    'label' => lang('DashboardEmployee.kpis.pending.label'),
                    'indicator' => lang('DashboardEmployee.kpis.pending.indicator'),
                    'indicatorType' => $pendingJustifications > 0 ? 'warning' : 'success',
                    'url' => sp_justifications_index_url(),
                ],
            ]),
            'shortcuts' => $this->normalizeShortcutItems([
                [
                    'href' => sp_timesheet_punch_url(),
                    'icon' => 'bi bi-clock-fill',
                    'title' => lang('DashboardEmployee.shortcuts.punch.title'),
                    'description' => lang('DashboardEmployee.shortcuts.punch.description'),
                ],
                [
                    'href' => sp_timesheet_history_url(),
                    'icon' => 'bi bi-calendar3',
                    'title' => lang('DashboardEmployee.shortcuts.history.title'),
                    'description' => lang('DashboardEmployee.shortcuts.history.description'),
                ],
                [
                    'href' => sp_justifications_create_url(),
                    'icon' => 'bi bi-file-earmark-plus',
                    'title' => lang('DashboardEmployee.shortcuts.justification.title'),
                    'description' => lang('DashboardEmployee.shortcuts.justification.description'),
                ],
                [
                    'href' => sp_profile_url(),
                    'icon' => 'bi bi-person-circle',
                    'title' => lang('DashboardEmployee.shortcuts.profile.title'),
                    'description' => lang('DashboardEmployee.shortcuts.profile.description'),
                ],
            ]),
        ];

        $presentation['sections'] = [
            'shortcuts' => [
                'title' => lang('DashboardEmployee.shortcuts.sectionTitle'),
                'items' => $presentation['shortcuts'],
            ],
        ];

        return $presentation;

    }
}
