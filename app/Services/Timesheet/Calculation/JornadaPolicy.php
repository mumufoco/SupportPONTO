<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

/**
 * Resolve a jornada esperada do dia sem acoplar o motor de cálculo a Models.
 */
class JornadaPolicy
{
    /** @param array<string,mixed> $context */
    public function expectedDailyHours(array $context = []): float
    {
        $candidates = [
            $context['expected_daily_hours'] ?? null,
            $context['daily_hours'] ?? null,
            $context['jornada_diaria'] ?? null,
        ];

        $employee = $context['employee'] ?? null;
        if (is_object($employee)) {
            $candidates[] = $employee->daily_hours ?? null;
            $candidates[] = $employee->expected_daily_hours ?? null;
            $candidates[] = $employee->jornada_diaria ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (float) $candidate > 0) {
                return round((float) $candidate, 2);
            }
        }

        return 8.0;
    }
}
