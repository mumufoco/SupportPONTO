<?php

namespace App\Support\Navigation;

use CodeIgniter\HTTP\RequestInterface;

class AdminFlowContextResolver
{
    public static function fromRequest(RequestInterface $request, string $screen): array
    {
        $from = trim((string) ($request->getGet('from') ?? ''));
        if ($from !== 'dashboard-admin') {
            return self::disabled();
        }

        $flow = trim((string) ($request->getGet('flow') ?? ''));
        $returnSection = trim((string) ($request->getGet('return_section') ?? ''));
        $returnUrl = self::sanitizeReturnUrl((string) ($request->getGet('return_url') ?? ''), $returnSection);
        $screenKey = in_array($screen, ['justifications', 'audit'], true) ? $screen : 'generic';

        return [
            'enabled' => true,
            'from' => $from,
            'flow' => $flow,
            'flowLabel' => self::flowLabel($flow),
            'sourceLabel' => lang('OperationalNavigation.source.dashboardAdmin'),
            'screenLabel' => lang('OperationalNavigation.screens.' . $screenKey . '.label'),
            'title' => lang('OperationalNavigation.screens.' . $screenKey . '.title'),
            'description' => lang('OperationalNavigation.screens.' . $screenKey . '.description'),
            'backUrl' => $returnUrl,
            'backLabel' => lang('OperationalNavigation.actions.backToDashboard'),
            'returnLabel' => self::returnLabel($returnSection),
        ];
    }

    private static function disabled(): array
    {
        return [
            'enabled' => false,
            'from' => null,
            'flow' => null,
            'flowLabel' => null,
            'sourceLabel' => null,
            'screenLabel' => null,
            'title' => null,
            'description' => null,
            'backUrl' => site_url('dashboard/admin'),
            'backLabel' => lang('OperationalNavigation.actions.backToDashboard'),
            'returnLabel' => null,
        ];
    }

    private static function sanitizeReturnUrl(string $returnUrl, string $returnSection): string
    {
        $fallback = site_url('dashboard/admin' . self::normalizeHash($returnSection));
        $returnUrl = trim($returnUrl);
        if ($returnUrl === '') {
            return $fallback;
        }

        $dashboardBase = site_url('dashboard/admin');
        if (str_starts_with($returnUrl, $dashboardBase)) {
            return $returnUrl;
        }

        $parts = parse_url($returnUrl);
        if ($parts === false) {
            return $fallback;
        }

        $path = (string) ($parts['path'] ?? '');
        $fragment = isset($parts['fragment']) ? '#' . ltrim((string) $parts['fragment'], '#') : '';
        $dashboardPath = (string) parse_url($dashboardBase, PHP_URL_PATH);

        if ($path !== '' && $dashboardPath !== '' && rtrim($path, '/') === rtrim($dashboardPath, '/')) {
            return site_url('dashboard/admin') . $fragment;
        }

        return $fallback;
    }

    private static function normalizeHash(string $returnSection): string
    {
        $returnSection = trim($returnSection);
        if ($returnSection === '') {
            return '';
        }

        return '#' . ltrim($returnSection, '#');
    }

    private static function flowLabel(string $flow): string
    {
        return match ($flow) {
            'pending-queue', 'guide-pending-queue' => lang('OperationalNavigation.flows.pendingQueue'),
            'audit-trail', 'guide-audit-trail', 'hub-audit', 'audit-alerts' => lang('OperationalNavigation.flows.auditTrail'),
            default => lang('OperationalNavigation.flows.generic'),
        };
    }

    private static function returnLabel(string $returnSection): string
    {
        return match (trim($returnSection, '# ')) {
            'pending-justifications-section' => lang('OperationalNavigation.returns.pendingSection'),
            'recent-activities-section' => lang('OperationalNavigation.returns.recentSection'),
            'system-alerts-section' => lang('OperationalNavigation.returns.alertsSection'),
            default => lang('OperationalNavigation.returns.dashboard'),
        };
    }
}
