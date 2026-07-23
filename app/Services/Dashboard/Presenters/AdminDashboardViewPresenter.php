<?php

namespace App\Services\Dashboard\Presenters;

class AdminDashboardViewPresenter extends AbstractDashboardViewPresenter
{
    public function present(array $viewData): array
    {
        $statistics = $this->normalizeStatistics($viewData['statistics'] ?? []);
        $pendingApprovals = $this->asMap($viewData['pendingApprovals'] ?? []);
        $justifications = $this->asList($pendingApprovals['justifications'] ?? []);
        $recentActivities = $this->asList($viewData['recentActivities'] ?? []);
        $systemAlerts = $this->normalizeAlerts($viewData['systemAlerts'] ?? []);

        $pendingJustificationCount = count($justifications);
        $recentActivityCount = count($recentActivities);
        $systemAlertCount = count($systemAlerts);

        $presentation = [
            'pageHeader' => $this->normalizePageHeader([
                'title' => lang('DashboardAdmin.title'),
                'subtitle' => lang('DashboardAdmin.subtitle'),
                'icon' => lang('DashboardAdmin.pageIcon'),
            ]),
            'primaryStats' => $this->normalizeStatsList([
                $this->statCard(lang('DashboardAdmin.stats.totalEmployees'), (string) $statistics['total_employees'], 'bi bi-people-fill', 'primary'),
                $this->statCard(lang('DashboardAdmin.stats.activeEmployees'), (string) $statistics['active_employees'], 'bi bi-person-check-fill', 'success'),
                $this->statCard(lang('DashboardAdmin.stats.presentToday'), (string) $statistics['employees_present'], 'bi bi-fingerprint', 'warning'),
                $this->statCard(lang('DashboardAdmin.stats.pendingJustifications'), (string) $statistics['pending_justifications'], 'bi bi-file-earmark-text-fill', 'info'),
            ]),
            'secondaryStats' => $this->normalizeStatsList([
                $this->statCard(lang('DashboardAdmin.stats.inactiveEmployees'), (string) $statistics['total_inactive'], 'bi bi-person-x-fill', 'danger', 'col-md-4'),
                $this->statCard(lang('DashboardAdmin.stats.pendingWarnings'), (string) $statistics['active_warnings'], 'bi bi-exclamation-triangle-fill', 'warning', 'col-md-4'),
                $this->statCard(lang('DashboardAdmin.stats.recentRegistrations'), (string) $statistics['recent_registrations'], 'bi bi-calendar-plus', 'primary', 'col-md-4'),
            ]),
            'hub' => [
                'cards' => $this->normalizeActionCards($this->actionCards($pendingJustificationCount, $recentActivityCount, $systemAlertCount)),
            ],
            'navigationGuide' => $this->normalizeNavigationGuide([
                'title' => lang('DashboardAdmin.navigationGuide.title'),
                'description' => lang('DashboardAdmin.navigationGuide.description'),
                'items' => $this->navigationGuideItems($pendingJustificationCount, $recentActivityCount, $systemAlertCount),
            ]),
            'pendingJustifications' => [
                'count' => $pendingJustificationCount,
                'rows' => $this->normalizeRows(array_map(fn ($row) => $this->presentJustificationRow($row), $justifications), 'justification'),
                'footer' => $this->normalizeSectionFooter([
                    'title' => lang('DashboardAdmin.sections.pending.footer.title'),
                    'description' => lang('DashboardAdmin.sections.pending.footer.description'),
                    'metaItems' => [
                        ['label' => lang('DashboardAdmin.sections.pending.footer.destination'), 'icon' => 'bi bi-box-arrow-up-right', 'variant' => 'light'],
                        ['label' => lang('DashboardAdmin.sections.pending.footer.context'), 'icon' => 'bi bi-list-check', 'variant' => 'warning'],
                    ],
                    'primaryAction' => [
                        'href' => $this->externalFlowUrl('justifications', 'pending-justifications-section', 'pending-queue'),
                        'label' => lang('DashboardAdmin.sections.pending.footer.openQueue'),
                        'flowLabel' => lang('DashboardAdmin.navigationGuide.items.pendingQueue.title'),
                        'flowContext' => lang('DashboardAdmin.navigationGuide.items.pendingQueue.context'),
                        'returnLabel' => lang('DashboardAdmin.sections.pending.title'),
                        'icon' => 'bi bi-box-arrow-up-right',
                        'variant' => 'primary',
                        'class' => 'btn btn-sm btn-primary',
                    ],
                    'secondaryAction' => [
                        'href' => '#admin-action-hub',
                        'label' => lang('DashboardAdmin.sections.pending.footer.backToHub'),
                        'icon' => 'bi bi-arrow-up-circle',
                        'variant' => 'secondary',
                        'scroll' => true,
                    ],
                ]),
            ],
            'recentActivities' => [
                'count' => $recentActivityCount,
                'items' => $this->normalizeRows(array_map(fn ($row) => $this->presentRecentActivity($row), $recentActivities), 'activity'),
                'footer' => $this->normalizeSectionFooter([
                    'title' => lang('DashboardAdmin.sections.recent.footer.title'),
                    'description' => lang('DashboardAdmin.sections.recent.footer.description'),
                    'metaItems' => [
                        ['label' => lang('DashboardAdmin.sections.recent.footer.destination'), 'icon' => 'bi bi-journal-text', 'variant' => 'light'],
                        ['label' => lang('DashboardAdmin.sections.recent.footer.context'), 'icon' => 'bi bi-search', 'variant' => 'primary'],
                    ],
                    'primaryAction' => [
                        'href' => $this->externalFlowUrl('audit', 'recent-activities-section', 'audit-trail'),
                        'label' => lang('DashboardAdmin.sections.recent.footer.openAudit'),
                        'flowLabel' => lang('DashboardAdmin.navigationGuide.items.auditTrail.title'),
                        'flowContext' => lang('DashboardAdmin.navigationGuide.items.auditTrail.contextDefault'),
                        'returnLabel' => lang('DashboardAdmin.sections.recent.title'),
                        'icon' => 'bi bi-box-arrow-up-right',
                        'variant' => 'primary',
                        'class' => 'btn btn-sm btn-primary',
                    ],
                    'secondaryAction' => [
                        'href' => '#system-alerts-section',
                        'label' => lang('DashboardAdmin.sections.recent.footer.goAlerts'),
                        'icon' => 'bi bi-arrow-down-circle',
                        'variant' => 'secondary',
                        'scroll' => true,
                    ],
                ]),
            ],
            'systemAlerts' => [
                'count' => $systemAlertCount,
                'items' => $systemAlerts,
                'footer' => $this->normalizeSectionFooter([
                    'title' => lang('DashboardAdmin.sections.alerts.footer.title'),
                    'description' => $systemAlertCount > 0
                        ? lang('DashboardAdmin.sections.alerts.footer.descriptionActive')
                        : lang('DashboardAdmin.sections.alerts.footer.descriptionEmpty'),
                    'metaItems' => [
                        ['label' => lang('DashboardAdmin.sections.recent.footer.destination'), 'icon' => 'bi bi-journal-text', 'variant' => 'light'],
                        [
                            'label' => $systemAlertCount > 0 ? lang('DashboardAdmin.sections.alerts.footer.contextActive') : lang('DashboardAdmin.sections.alerts.footer.contextEmpty'),
                            'icon' => $systemAlertCount > 0 ? 'bi bi-exclamation-triangle' : 'bi bi-shield-check',
                            'variant' => $systemAlertCount > 0 ? 'danger' : 'success',
                        ],
                    ],
                    'primaryAction' => [
                        'href' => $this->externalFlowUrl('audit', 'system-alerts-section', 'audit-alerts'),
                        'label' => lang('DashboardAdmin.sections.alerts.footer.openAudit'),
                        'flowLabel' => lang('DashboardAdmin.navigationGuide.items.auditTrail.title'),
                        'flowContext' => $systemAlertCount > 0
                            ? lang('DashboardAdmin.navigationGuide.items.auditTrail.contextAlerts')
                            : lang('DashboardAdmin.navigationGuide.items.auditTrail.contextDefault'),
                        'returnLabel' => lang('DashboardAdmin.sections.alerts.title'),
                        'icon' => 'bi bi-journal-text',
                        'variant' => $systemAlertCount > 0 ? 'danger' : 'primary',
                        'class' => 'btn btn-sm btn-' . ($systemAlertCount > 0 ? 'danger' : 'primary'),
                    ],
                    'secondaryAction' => [
                        'href' => '#admin-action-hub',
                        'label' => lang('DashboardAdmin.sections.alerts.footer.backToHub'),
                        'icon' => 'bi bi-arrow-up-circle',
                        'variant' => 'secondary',
                        'scroll' => true,
                    ],
                ]),
            ],
        ];

        $presentation['kpis'] = array_merge($presentation['primaryStats'], $presentation['secondaryStats']);
        $presentation['sections'] = [
            'pendingJustifications' => $presentation['pendingJustifications'],
            'recentActivities' => $presentation['recentActivities'],
            'systemAlerts' => $presentation['systemAlerts'],
        ];

        return $presentation;
    }


    protected function normalizePageHeader(mixed $value, string $defaultTitle = '', string $defaultSubtitle = '', string $defaultIcon = 'bi bi-grid'): array
    {
        $header = $this->asMap($value);

        return [
            'title' => $this->normalizedText($header['title'] ?? null, lang('DashboardAdmin.title')),
            'subtitle' => $this->normalizedText($header['subtitle'] ?? null, lang('DashboardAdmin.subtitle')),
            'icon' => $this->normalizedText($header['icon'] ?? null, lang('DashboardAdmin.pageIcon')),
        ];
    }

    protected function normalizeStatistics(mixed $value): array
    {
        $statistics = $this->asMap($value);

        return [
            'total_employees' => $this->intValue($statistics['total_employees'] ?? 0),
            'active_employees' => $this->intValue($statistics['active_employees'] ?? 0),
            'employees_present' => $this->intValue($statistics['employees_present'] ?? 0),
            'pending_justifications' => $this->intValue($statistics['pending_justifications'] ?? 0),
            'total_inactive' => $this->intValue($statistics['total_inactive'] ?? 0),
            'active_warnings' => $this->intValue($statistics['active_warnings'] ?? 0),
            // A chave gerada por DashboardAdminService::statistics() é
            // 'pending_registrations' — este card lia 'recent_registrations', que
            // nunca existia, e sempre mostrava 0.
            'recent_registrations' => $this->intValue($statistics['pending_registrations'] ?? 0),
        ];
    }

    protected function normalizeStatsList(mixed $value): array
    {
        $stats = [];

        foreach ($this->asList($value) as $stat) {
            $statMap = $this->asMap($stat);
            $stats[] = [
                'label' => $this->normalizedText($statMap['label'] ?? null),
                'value' => $this->normalizedText($statMap['value'] ?? null, '0'),
                'icon' => $this->normalizedText($statMap['icon'] ?? null, 'bi bi-circle-fill'),
                'variant' => $this->normalizedVariant($statMap['variant'] ?? null, 'secondary'),
                'columnClass' => $this->normalizedText($statMap['columnClass'] ?? null, 'col-md-3'),
            ];
        }

        return $stats;
    }

    protected function normalizeNavigationGuide(mixed $value): array
    {
        $guide = $this->asMap($value);

        return [
            'title' => $this->normalizedText($guide['title'] ?? null, lang('DashboardAdmin.navigationGuide.title')),
            'description' => $this->normalizedText($guide['description'] ?? null, lang('DashboardAdmin.navigationGuide.description')),
            'items' => $this->normalizeNavigationGuideItems($guide['items'] ?? []),
        ];
    }

    protected function normalizeNavigationGuideItems(mixed $value): array
    {
        $items = [];

        foreach ($this->asList($value) as $item) {
            $itemMap = $this->asMap($item);
            $items[] = [
                'title' => $this->normalizedText($itemMap['title'] ?? null),
                'description' => $this->normalizedText($itemMap['description'] ?? null),
                'href' => $this->normalizedHref($itemMap['href'] ?? null),
                'icon' => $this->normalizedText($itemMap['icon'] ?? null, 'bi bi-arrow-right-circle'),
                'variant' => $this->normalizedVariant($itemMap['variant'] ?? null, 'secondary'),
                'scopeLabel' => $this->normalizedText($itemMap['scopeLabel'] ?? null),
                'contextLabel' => $this->normalizedText($itemMap['contextLabel'] ?? null),
                'external' => $this->boolValue($itemMap['external'] ?? false),
                'returnLabel' => $this->normalizedText($itemMap['returnLabel'] ?? null, ''),
            ];
        }

        return $items;
    }

    protected function normalizeActionCards(mixed $value): array
    {
        $cards = [];

        foreach ($this->asList($value) as $card) {
            $cardMap = $this->asMap($card);
            $cards[] = [
                'title' => $this->normalizedText($cardMap['title'] ?? null),
                'description' => $this->normalizedText($cardMap['description'] ?? null),
                'icon' => $this->normalizedText($cardMap['icon'] ?? null, 'bi bi-grid-1x2'),
                'href' => $this->normalizedHref($cardMap['href'] ?? null),
                'variant' => $this->normalizedVariant($cardMap['variant'] ?? null, 'secondary'),
                'actionLabel' => $this->normalizedText($cardMap['actionLabel'] ?? null),
                'count' => isset($cardMap['count']) && $cardMap['count'] !== '' ? (int) $cardMap['count'] : null,
                'external' => $this->boolValue($cardMap['external'] ?? false),
                'scopeLabel' => $this->normalizedText($cardMap['scopeLabel'] ?? null, ''),
                'scopeVariant' => $this->normalizedVariant($cardMap['scopeVariant'] ?? null, 'light'),
                'contextHint' => $this->normalizedText($cardMap['contextHint'] ?? null, ''),
                'externalContext' => $this->normalizedText($cardMap['externalContext'] ?? null, ''),
                'returnLabel' => $this->normalizedText($cardMap['returnLabel'] ?? null, ''),
            ];
        }

        return $cards;
    }

    protected function normalizeRows(mixed $value, string $type): array
    {
        $rows = [];

        foreach ($this->asList($value) as $row) {
            $rowMap = $this->asMap($row);
            $rows[] = $type === 'justification'
                ? [
                    'employeeName' => $this->normalizedText($rowMap['employeeName'] ?? null),
                    'employeeInitial' => $this->normalizedText($rowMap['employeeInitial'] ?? null, lang('DashboardAdmin.common.missingInitial')),
                    'createdAtLabel' => $this->normalizedText($rowMap['createdAtLabel'] ?? null),
                    'typeLabel' => $this->normalizedText($rowMap['typeLabel'] ?? null),
                    'statusLabel' => $this->normalizedText($rowMap['statusLabel'] ?? null, lang('DashboardAdmin.common.pending')),
                ]
                : [
                    'title' => $this->normalizedText($rowMap['title'] ?? null, lang('DashboardAdmin.sections.recent.defaultTitle')),
                    'userLabel' => $this->normalizedText($rowMap['userLabel'] ?? null, lang('DashboardAdmin.sections.recent.defaultUser')),
                    'createdAtLabel' => $this->normalizedText($rowMap['createdAtLabel'] ?? null),
                    'icon' => $this->normalizedText($rowMap['icon'] ?? null, 'bi bi-activity'),
                ];
        }

        return $rows;
    }

    protected function normalizeAlerts(mixed $value): array
    {
        $alerts = [];

        foreach ($this->asList($value) as $alert) {
            $alertMap = $this->asMap($alert);
            $alerts[] = [
                'type' => $this->normalizedVariant($alertMap['type'] ?? null, 'info'),
                'message' => $this->normalizedText($alertMap['message'] ?? null),
                'icon' => $this->normalizedText($alertMap['icon'] ?? null, 'bi bi-info-circle'),
                'title' => $this->normalizedText($alertMap['title'] ?? null, ''),
                'actions' => $this->asList($alertMap['actions'] ?? []),
            ];
        }

        return $alerts;
    }

    protected function normalizeSectionFooter(mixed $value): array
    {
        $footer = $this->asMap($value);

        return [
            'title' => $this->normalizedText($footer['title'] ?? null),
            'description' => $this->normalizedText($footer['description'] ?? null),
            'metaItems' => $this->normalizeMetaItems($footer['metaItems'] ?? []),
            'primaryAction' => $this->normalizeAction($footer['primaryAction'] ?? []),
            'secondaryAction' => $this->normalizeAction($footer['secondaryAction'] ?? []),
        ];
    }

    protected function normalizeMetaItems(mixed $value): array
    {
        $items = [];

        foreach ($this->asList($value) as $item) {
            $itemMap = $this->asMap($item);
            $items[] = [
                'label' => $this->normalizedText($itemMap['label'] ?? null),
                'icon' => $this->normalizedText($itemMap['icon'] ?? null, 'bi bi-dot'),
                'variant' => $this->normalizedVariant($itemMap['variant'] ?? null, 'light'),
            ];
        }

        return $items;
    }

    protected function normalizeAction(mixed $value): array
    {
        $action = $this->asMap($value);

        return [
            'href' => $this->normalizedHref($action['href'] ?? null),
            'label' => $this->normalizedText($action['label'] ?? null),
            'flowLabel' => $this->normalizedText($action['flowLabel'] ?? null, ''),
            'flowContext' => $this->normalizedText($action['flowContext'] ?? null, ''),
            'returnLabel' => $this->normalizedText($action['returnLabel'] ?? null, ''),
            'icon' => $this->normalizedText($action['icon'] ?? null, 'bi bi-arrow-right-circle'),
            'variant' => $this->normalizedVariant($action['variant'] ?? null, 'secondary'),
            'class' => $this->normalizedText($action['class'] ?? null, ''),
            'scroll' => $this->boolValue($action['scroll'] ?? false),
        ];
    }

    protected function asMap(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected function statCard(string $label, string $value, string $icon, string $variant, string $columnClass = 'col-md-3'): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'icon' => $icon,
            'variant' => $variant,
            'columnClass' => $columnClass,
        ];
    }

    protected function actionCards(int $pendingJustificationCount, int $recentActivityCount, int $systemAlertCount): array
    {
        return [
            [
                'title' => lang('DashboardAdmin.hub.cards.pending.title'),
                'description' => $pendingJustificationCount > 0
                    ? lang('DashboardAdmin.hub.cards.pending.descriptionActive', [$pendingJustificationCount])
                    : lang('DashboardAdmin.hub.cards.pending.descriptionEmpty'),
                'icon' => 'bi bi-clipboard-check',
                'href' => '#pending-justifications-section',
                'variant' => $pendingJustificationCount > 0 ? 'warning' : 'success',
                'actionLabel' => $pendingJustificationCount > 0 ? lang('DashboardAdmin.hub.cards.pending.actionActive') : lang('DashboardAdmin.hub.cards.pending.actionIdle'),
                'count' => $pendingJustificationCount,
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.internal'),
                'scopeVariant' => 'light',
                'contextHint' => lang('DashboardAdmin.hub.cards.pending.contextHint'),
            ],
            [
                'title' => lang('DashboardAdmin.hub.cards.recent.title'),
                'description' => $recentActivityCount > 0
                    ? lang('DashboardAdmin.hub.cards.recent.descriptionActive', [$recentActivityCount])
                    : lang('DashboardAdmin.hub.cards.recent.descriptionEmpty'),
                'icon' => 'bi bi-clock-history',
                'href' => '#recent-activities-section',
                'variant' => $recentActivityCount > 0 ? 'primary' : 'secondary',
                'actionLabel' => lang('DashboardAdmin.hub.cards.recent.action'),
                'count' => $recentActivityCount,
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.internal'),
                'scopeVariant' => 'light',
                'contextHint' => lang('DashboardAdmin.hub.cards.recent.contextHint'),
            ],
            [
                'title' => lang('DashboardAdmin.hub.cards.alerts.title'),
                'description' => $systemAlertCount > 0
                    ? lang('DashboardAdmin.hub.cards.alerts.descriptionActive', [$systemAlertCount])
                    : lang('DashboardAdmin.hub.cards.alerts.descriptionEmpty'),
                'icon' => 'bi bi-exclamation-triangle',
                'href' => '#system-alerts-section',
                'variant' => $systemAlertCount > 0 ? 'danger' : 'success',
                'actionLabel' => $systemAlertCount > 0 ? lang('DashboardAdmin.hub.cards.alerts.actionActive') : lang('DashboardAdmin.hub.cards.alerts.actionIdle'),
                'count' => $systemAlertCount,
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.internal'),
                'scopeVariant' => 'light',
                'contextHint' => lang('DashboardAdmin.hub.cards.alerts.contextHint'),
            ],
            [
                'title' => lang('DashboardAdmin.hub.cards.audit.title'),
                'description' => lang('DashboardAdmin.hub.cards.audit.description'),
                'icon' => 'bi bi-journal-text',
                'href' => $this->externalFlowUrl('audit', 'recent-activities-section', 'hub-audit'),
                'variant' => 'info',
                'actionLabel' => lang('DashboardAdmin.hub.cards.audit.action'),
                'count' => null,
                'external' => true,
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.external'),
                'scopeVariant' => 'info',
                'contextHint' => lang('DashboardAdmin.hub.cards.audit.contextHint'),
                'externalContext' => lang('DashboardAdmin.navigationGuide.items.auditTrail.contextDefault'),
                'returnLabel' => lang('DashboardAdmin.sections.recent.title'),
            ],
        ];
    }

    protected function navigationGuideItems(int $pendingJustificationCount, int $recentActivityCount, int $systemAlertCount): array
    {
        return [
            [
                'title' => lang('DashboardAdmin.navigationGuide.items.pendingSummary.title'),
                'description' => $pendingJustificationCount > 0
                    ? lang('DashboardAdmin.navigationGuide.items.pendingSummary.descriptionActive', [$pendingJustificationCount])
                    : lang('DashboardAdmin.navigationGuide.items.pendingSummary.descriptionEmpty'),
                'href' => '#pending-justifications-section',
                'icon' => 'bi bi-clipboard-check',
                'variant' => $pendingJustificationCount > 0 ? 'warning' : 'success',
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.internal'),
                'contextLabel' => lang('DashboardAdmin.navigationGuide.items.pendingSummary.context'),
                'external' => false,
            ],
            [
                'title' => lang('DashboardAdmin.navigationGuide.items.pendingQueue.title'),
                'description' => lang('DashboardAdmin.navigationGuide.items.pendingQueue.description'),
                'href' => $this->externalFlowUrl('justifications', 'pending-justifications-section', 'guide-pending-queue'),
                'icon' => 'bi bi-box-arrow-up-right',
                'variant' => 'primary',
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.external'),
                'contextLabel' => lang('DashboardAdmin.navigationGuide.items.pendingQueue.context'),
                'external' => true,
                'returnLabel' => lang('DashboardAdmin.sections.pending.title'),
            ],
            [
                'title' => lang('DashboardAdmin.navigationGuide.items.recentSummary.title'),
                'description' => $recentActivityCount > 0
                    ? lang('DashboardAdmin.navigationGuide.items.recentSummary.descriptionActive', [$recentActivityCount])
                    : lang('DashboardAdmin.navigationGuide.items.recentSummary.descriptionEmpty'),
                'href' => '#recent-activities-section',
                'icon' => 'bi bi-clock-history',
                'variant' => $recentActivityCount > 0 ? 'primary' : 'secondary',
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.internal'),
                'contextLabel' => lang('DashboardAdmin.navigationGuide.items.recentSummary.context'),
                'external' => false,
            ],
            [
                'title' => lang('DashboardAdmin.navigationGuide.items.auditTrail.title'),
                'description' => lang('DashboardAdmin.navigationGuide.items.auditTrail.description'),
                'href' => $this->externalFlowUrl('audit', 'recent-activities-section', 'guide-audit-trail'),
                'icon' => 'bi bi-journal-text',
                'variant' => $systemAlertCount > 0 ? 'danger' : 'info',
                'scopeLabel' => lang('DashboardAdmin.hub.cards.scope.external'),
                'contextLabel' => $systemAlertCount > 0
                    ? lang('DashboardAdmin.navigationGuide.items.auditTrail.contextAlerts')
                    : lang('DashboardAdmin.navigationGuide.items.auditTrail.contextDefault'),
                'external' => true,
                'returnLabel' => lang('DashboardAdmin.sections.recent.title'),
            ],
        ];
    }

    protected function presentJustificationRow(array|object $row): array
    {
        $employeeName = $this->normalizedText($this->value($row, 'employee_name'));

        return [
            'employeeId' => (int) $this->value($row, 'employee_id'),
            'employeeName' => $employeeName,
            'employeeInitial' => $employeeName === lang('DashboardAdmin.common.missingText') ? lang('DashboardAdmin.common.missingInitial') : mb_strtoupper(mb_substr($employeeName, 0, 1)),
            'createdAtLabel' => $this->formatDate((string) $this->value($row, 'created_at', '')),
            'typeLabel' => $this->normalizedText($this->value($row, 'type')),
            'statusLabel' => lang('DashboardAdmin.common.pending'),
        ];
    }

    protected function presentRecentActivity(array|object $row): array
    {
        return [
            'title' => $this->normalizedText($this->value($row, 'action'), lang('DashboardAdmin.sections.recent.defaultTitle')),
            'userLabel' => $this->normalizedText($this->value($row, 'user_name'), lang('DashboardAdmin.sections.recent.defaultUser')),
            'createdAtLabel' => $this->formatDateTime((string) $this->value($row, 'created_at', '')),
        ];
    }



    protected function externalFlowUrl(string $path, string $returnSectionId, string $flowKey): string
    {
        $query = http_build_query([
            'from' => 'dashboard-admin',
            'flow' => $flowKey,
            'return_url' => $this->dashboardUrl('#' . ltrim($returnSectionId, '#')),
            'return_section' => $returnSectionId,
        ]);

        return site_url($path) . ($query !== '' ? '?' . $query : '');
    }

    protected function dashboardUrl(string $hash = ''): string
    {
        $hash = trim($hash);

        return site_url('dashboard/admin') . ($hash !== '' ? $hash : '');
    }

    protected function normalizedText(mixed $value, ?string $fallback = null): string
    {
        $text = trim((string) ($value ?? ''));
        $resolvedFallback = $fallback ?? lang('DashboardAdmin.common.missingText');

        return $text !== '' ? $text : $resolvedFallback;
    }

    protected function formatDate(mixed $value, string $fallback = ''): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d/m/Y', $timestamp) : lang('DashboardAdmin.common.missingText');
    }

    protected function formatDateTime(mixed $value, string $fallback = ''): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d/m H:i', $timestamp) : lang('DashboardAdmin.common.missingText');
    }

    protected function value(array|object|null $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }

        return $row->{$key} ?? $default;
    }

    protected function asList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    protected function normalizedHref(mixed $value): string
    {
        $href = trim((string) ($value ?? ''));

        return $href !== '' ? $href : '#';
    }

    protected function normalizedVariant(mixed $value, string $fallback = 'secondary'): string
    {
        $variant = trim((string) ($value ?? ''));

        return $variant !== '' ? $variant : $fallback;
    }

    protected function intValue(mixed $value, int $fallback = 0): int
    {
        return is_numeric($value) ? (int) $value : $fallback;
    }

    protected function boolValue(mixed $value, bool $fallback = false): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }
}
