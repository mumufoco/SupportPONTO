<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

/**
 * Aplica tolerância diária para evitar apontar débito/extra por poucos minutos.
 */
class TolerancePolicy
{
    /** @param array<string,mixed> $context */
    public function toleranceHours(array $context = []): float
    {
        $minutes = $context['tolerance_minutes'] ?? $context['daily_tolerance_minutes'] ?? 10;
        if (! is_numeric($minutes) || (float) $minutes < 0) {
            $minutes = 0;
        }

        return round(((float) $minutes) / 60, 4);
    }

    public function normalizeDelta(float $deltaHours, float $toleranceHours): float
    {
        return abs($deltaHours) <= $toleranceHours ? 0.0 : $deltaHours;
    }
}
