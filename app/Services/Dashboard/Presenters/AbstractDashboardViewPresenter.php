<?php

namespace App\Services\Dashboard\Presenters;

abstract class AbstractDashboardViewPresenter
{
    protected function asMap(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    protected function asList(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    protected function text(mixed $value, string $fallback = ''): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }

    protected function intValue(mixed $value, int $fallback = 0): int
    {
        return is_numeric($value) ? (int) $value : $fallback;
    }

    protected function floatValue(mixed $value, float $fallback = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    protected function boolValue(mixed $value, bool $fallback = false): bool
    {
        $resolved = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $resolved ?? $fallback;
    }

    protected function href(mixed $value, string $fallback = '#'): string
    {
        $href = trim((string) ($value ?? ''));

        return $href !== '' ? $href : $fallback;
    }

    protected function variant(mixed $value, string $fallback = 'secondary'): string
    {
        $variant = trim((string) ($value ?? ''));

        return $variant !== '' ? $variant : $fallback;
    }

    protected function value(array|object|null $row, string $key, mixed $default = null): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? $default;
        }

        if (is_object($row)) {
            return $row->{$key} ?? $default;
        }

        return $default;
    }

    protected function normalizePageHeader(array $header, string $defaultTitle, string $defaultSubtitle = '', string $defaultIcon = 'bi bi-grid'): array
    {
        return [
            'title' => $this->text($header['title'] ?? null, $defaultTitle),
            'subtitle' => $this->text($header['subtitle'] ?? null, $defaultSubtitle),
            'icon' => $this->text($header['icon'] ?? null, $defaultIcon),
            'actions' => $this->normalizePageActions($header['actions'] ?? []),
        ];
    }

    protected function normalizePageActions(mixed $actions): array
    {
        return array_map(function (mixed $action): array {
            $item = $this->asMap($action);

            return [
                'label' => $this->text($item['label'] ?? null, lang('DashboardCommon.pageHeader.defaultAction')),
                'icon' => $this->text($item['icon'] ?? null, ''),
                'url' => $this->href($item['url'] ?? null),
            ];
        }, $this->asList($actions));
    }

    protected function normalizeKpis(mixed $kpis): array
    {
        return array_map(function (mixed $kpi): array {
            $item = $this->asMap($kpi);

            return [
                'icon' => $this->text($item['icon'] ?? null, 'fas fa-chart-bar'),
                'iconColor' => $this->variant($item['iconColor'] ?? null, 'primary'),
                'value' => $this->text($item['value'] ?? null, '0'),
                'label' => $this->text($item['label'] ?? null, ''),
                'indicator' => $this->text($item['indicator'] ?? null, ''),
                'indicatorType' => $this->variant($item['indicatorType'] ?? null, 'neutral'),
                'classes' => $this->text($item['classes'] ?? null, ''),
                'url' => $this->href($item['url'] ?? null, ''),
            ];
        }, $this->asList($kpis));
    }

    protected function normalizeSection(array $section, array $defaults = []): array
    {
        $defaults = array_merge([
            'title' => '',
            'description' => '',
            'icon' => '',
            'actionLabel' => '',
            'actionUrl' => '',
            'headers' => [],
            'rows' => [],
            'items' => [],
            'emptyTitle' => '',
            'emptyMessage' => '',
        ], $defaults);

        return [
            'title' => $this->text($section['title'] ?? null, (string) $defaults['title']),
            'description' => $this->text($section['description'] ?? null, (string) $defaults['description']),
            'icon' => $this->text($section['icon'] ?? null, (string) $defaults['icon']),
            'actionLabel' => $this->text($section['actionLabel'] ?? null, (string) $defaults['actionLabel']),
            'actionUrl' => $this->href($section['actionUrl'] ?? null, (string) $defaults['actionUrl']),
            'headers' => $this->asList($section['headers'] ?? $defaults['headers']),
            'rows' => $this->asList($section['rows'] ?? $defaults['rows']),
            'items' => $this->asList($section['items'] ?? $defaults['items']),
            'emptyTitle' => $this->text($section['emptyTitle'] ?? null, (string) $defaults['emptyTitle']),
            'emptyMessage' => $this->text($section['emptyMessage'] ?? null, (string) $defaults['emptyMessage']),
        ];
    }

    protected function normalizeShortcutItems(mixed $shortcuts): array
    {
        return array_map(function (mixed $shortcut): array {
            $item = $this->asMap($shortcut);

            return [
                'href' => $this->href($item['href'] ?? null),
                'icon' => $this->text($item['icon'] ?? null, 'bi bi-link-45deg'),
                'title' => $this->text($item['title'] ?? null, ''),
                'description' => $this->text($item['description'] ?? null, ''),
            ];
        }, $this->asList($shortcuts));
    }

    protected function normalizeAlertItems(mixed $alerts): array
    {
        return array_map(function (mixed $alert): array {
            $item = $this->asMap($alert);

            return [
                'message' => $this->text($item['message'] ?? null, ''),
                'type' => $this->variant($item['type'] ?? null, 'info'),
            ];
        }, $this->asList($alerts));
    }

    protected function formatDate(mixed $value, string $fallback): string
    {
        $timestamp = strtotime((string) ($value ?? ''));

        return $timestamp !== false ? date('d/m/Y', $timestamp) : $fallback;
    }

    protected function formatTime(mixed $value, string $fallback): string
    {
        $timestamp = strtotime((string) ($value ?? ''));

        return $timestamp !== false ? date('H:i', $timestamp) : $fallback;
    }

    protected function formatDateTime(mixed $value, string $fallback): string
    {
        $timestamp = strtotime((string) ($value ?? ''));

        return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : $fallback;
    }

    protected function relativeTime(mixed $value, string $fallback): string
    {
        $timestamp = strtotime((string) ($value ?? ''));

        if ($timestamp === false) {
            return $fallback;
        }

        $diff = time() - $timestamp;
        if ($diff < 3600) {
            $minutes = max(1, (int) round($diff / 60));

            return lang('DashboardManager.common.relativeMinutes', [$minutes]);
        }

        $hours = max(1, (int) round($diff / 3600));

        return lang('DashboardManager.common.relativeHours', [$hours]);
    }
}
