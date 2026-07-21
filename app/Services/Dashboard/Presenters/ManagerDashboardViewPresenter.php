<?php

namespace App\Services\Dashboard\Presenters;

class ManagerDashboardViewPresenter extends AbstractDashboardViewPresenter
{
    public function present(array $viewData): array
    {
        $teamStats = $this->asMap($viewData['teamStats'] ?? []);
        $pendingJustifications = $this->asList($viewData['pendingJustifications'] ?? []);
        $teamActivity = $this->asList($viewData['teamActivity'] ?? []);
        $alerts = $this->asList($viewData['alerts'] ?? []);

        $presentation = [
            'pageHeader' => $this->normalizePageHeader([], lang('DashboardManager.title'), lang('DashboardManager.subtitle'), lang('DashboardManager.icon')),
            'kpis' => $this->normalizeKpis([
                [
                    'icon' => 'fas fa-users',
                    'iconColor' => 'primary',
                    'value' => (string) $this->intValue($teamStats['total_employees'] ?? 0),
                    'label' => lang('DashboardManager.kpis.totalEmployees.label'),
                    'indicator' => lang('DashboardManager.kpis.totalEmployees.indicator'),
                    'indicatorType' => 'neutral',
                    'classes' => 'grid-col-3',
                    'url' => sp_employees_index_url(),
                ],
                [
                    'icon' => 'fas fa-check-circle',
                    'iconColor' => 'success',
                    'value' => $this->intValue($teamStats['attendance_rate'] ?? 0) . '%',
                    'label' => lang('DashboardManager.kpis.attendanceRate.label'),
                    'indicator' => $this->text($teamStats['attendance_change'] ?? null, lang('DashboardManager.kpis.attendanceRate.indicator')),
                    'indicatorType' => 'success',
                    'classes' => 'grid-col-3',
                ],
                [
                    'icon' => 'fas fa-clock',
                    'iconColor' => 'warning',
                    'value' => (string) $this->intValue($teamStats['pending_approvals'] ?? 0),
                    'label' => lang('DashboardManager.kpis.pendingApprovals.label'),
                    'indicator' => $this->intValue($teamStats['pending_approvals'] ?? 0) > 0
                        ? lang('DashboardManager.kpis.pendingApprovals.indicatorWarning')
                        : lang('DashboardManager.kpis.pendingApprovals.indicatorOk'),
                    'indicatorType' => $this->intValue($teamStats['pending_approvals'] ?? 0) > 0 ? 'warning' : 'success',
                    'classes' => 'grid-col-3',
                    'url' => sp_justifications_index_url() . '?status=pending',
                ],
                [
                    'icon' => 'fas fa-user-slash',
                    'iconColor' => 'danger',
                    'value' => (string) $this->intValue($teamStats['absent_today'] ?? 0),
                    'label' => lang('DashboardManager.kpis.absentToday.label'),
                    'indicator' => lang('DashboardManager.kpis.absentToday.indicator'),
                    'indicatorType' => 'danger',
                    'classes' => 'grid-col-3',
                ],
            ]),
            'pendingJustifications' => $this->normalizeSection([
                'title' => lang('DashboardManager.pending.title'),
                'actionLabel' => lang('DashboardManager.pending.action'),
                'actionUrl' => sp_justifications_index_url(),
                'headers' => [
                    lang('DashboardManager.pending.headers.employee'),
                    lang('DashboardManager.pending.headers.type'),
                    lang('DashboardManager.pending.headers.date'),
                    lang('DashboardManager.pending.headers.submitted'),
                    lang('DashboardManager.pending.headers.actions'),
                ],
                'rows' => array_map(fn ($row) => $this->presentPendingRow($row), $pendingJustifications),
                'emptyTitle' => lang('DashboardManager.pending.emptyTitle'),
                'emptyMessage' => lang('DashboardManager.pending.emptyMessage'),
            ], [
                'title' => lang('DashboardManager.pending.title'),
                'actionLabel' => lang('DashboardManager.pending.action'),
                'actionUrl' => sp_justifications_index_url(),
                'emptyTitle' => lang('DashboardManager.pending.emptyTitle'),
                'emptyMessage' => lang('DashboardManager.pending.emptyMessage'),
            ]),
            'teamActivity' => $this->normalizeSection([
                'title' => lang('DashboardManager.activity.title'),
                'headers' => [
                    lang('DashboardManager.activity.headers.employee'),
                    lang('DashboardManager.activity.headers.action'),
                    lang('DashboardManager.activity.headers.time'),
                    lang('DashboardManager.activity.headers.status'),
                ],
                'rows' => array_map(fn ($row) => $this->presentActivityRow($row), $teamActivity),
                'emptyTitle' => lang('DashboardManager.activity.emptyTitle'),
                'emptyMessage' => lang('DashboardManager.activity.emptyMessage'),
            ], [
                'title' => lang('DashboardManager.activity.title'),
                'emptyTitle' => lang('DashboardManager.activity.emptyTitle'),
                'emptyMessage' => lang('DashboardManager.activity.emptyMessage'),
            ]),
            'quickActions' => $this->normalizeSection([
                'title' => lang('DashboardManager.quickActions.title'),
                'items' => [
                    ['href' => sp_employees_create_url(), 'icon' => 'fas fa-user-plus', 'label' => lang('DashboardManager.quickActions.items.createEmployee'), 'class' => 'btn btn-primary'],
                    ['href' => sp_reports_index_url(), 'icon' => 'fas fa-file-pdf', 'label' => lang('DashboardManager.quickActions.items.reports'), 'class' => 'btn btn-outline'],
                    ['href' => sp_schedules_index_url(), 'icon' => 'fas fa-calendar', 'label' => lang('DashboardManager.quickActions.items.schedules'), 'class' => 'btn btn-outline'],
                    ['href' => sp_warning_index_url(), 'icon' => 'fas fa-exclamation-triangle', 'label' => lang('DashboardManager.quickActions.items.warnings'), 'class' => 'btn btn-outline'],
                ],
            ], [
                'title' => lang('DashboardManager.quickActions.title'),
            ]),
            'alerts' => $this->normalizeSection([
                'title' => lang('DashboardManager.alerts.title'),
                'items' => $this->normalizeAlertItems(array_map(fn ($item) => [
                    'message' => $this->text($this->value($item, 'message'), lang('DashboardManager.alerts.fallbackMessage')),
                    'type' => $this->variant($this->value($item, 'type'), 'info'),
                ], $alerts)),
            ], [
                'title' => lang('DashboardManager.alerts.title'),
            ]),
        ];

        $presentation['sections'] = [
            'pendingJustifications' => $presentation['pendingJustifications'],
            'teamActivity' => $presentation['teamActivity'],
            'quickActions' => $presentation['quickActions'],
            'alerts' => $presentation['alerts'],
        ];

        return $presentation;
    }

    protected function presentPendingRow(array|object $row): array
    {
        $id = $this->intValue($this->value($row, 'id'));
        $type = $this->text($this->value($row, 'type'), 'absence');
        $typeLabel = lang('DashboardManager.justificationTypes.' . $type);
        if ($typeLabel === 'DashboardManager.justificationTypes.' . $type) {
            $typeLabel = lang('DashboardManager.justificationTypes.other');
        }

        return [
            'employeeName' => $this->text($this->value($row, 'employee_name'), lang('DashboardManager.common.missingText')),
            'typeLabel' => $typeLabel,
            'dateLabel' => $this->formatDate($this->value($row, 'date'), lang('DashboardManager.common.missingText')),
            'submittedLabel' => $this->relativeTime($this->value($row, 'created_at'), lang('DashboardManager.common.missingText')),
            'approveUrl' => sp_justifications_show_url((int) $id),
            'rejectUrl' => sp_justifications_show_url((int) $id),
        ];
    }

    protected function presentActivityRow(array|object $row): array
    {
        $employeeName = $this->text($this->value($row, 'employee_name'), lang('DashboardManager.common.missingText'));
        $status = $this->text($this->value($row, 'status'), 'active');

        return [
            'employeeId' => (int) $this->value($row, 'employee_id'),
            'employeeName' => $employeeName,
            'employeeInitial' => mb_strtoupper(mb_substr($employeeName, 0, 2)),
            'actionLabel' => $this->text($this->value($row, 'action'), lang('DashboardManager.activity.fallbackAction')),
            'timeLabel' => $this->formatTime($this->value($row, 'timestamp'), lang('DashboardManager.common.missingText')),
            'statusLabel' => $status === 'approved' ? lang('DashboardManager.activity.statusApproved') : lang('DashboardManager.activity.statusActive'),
            'statusVariant' => $status === 'approved' ? 'success' : 'warning',
        ];
    }
}
