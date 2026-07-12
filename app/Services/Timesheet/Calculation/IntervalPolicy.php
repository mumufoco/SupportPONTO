<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

class IntervalPolicy
{
    /** @param array<string,mixed> $context */
    public function minimumBreakHours(float $workedHours, array $context = []): float
    {
        $candidate = $context['minimum_break_hours'] ?? $context['min_interval_hours'] ?? null;
        if (is_numeric($candidate) && (float) $candidate >= 0) {
            return (float) $candidate;
        }

        if ($workedHours > 6.0) {
            return 1.0;
        }

        if ($workedHours > 4.0) {
            return 0.25;
        }

        return 0.0;
    }

    /** @param array<string,mixed> $context */
    public function violationHours(float $workedHours, float $breakHours, array $context = []): float
    {
        $minimum = $this->minimumBreakHours($workedHours, $context);
        return round(max(0.0, $minimum - $breakHours), 2);
    }
}
