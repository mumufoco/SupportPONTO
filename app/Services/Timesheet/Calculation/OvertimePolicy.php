<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

class OvertimePolicy
{
    public function calculate(float $workedHours, float $expectedHours, float $toleranceHours = 0.0): array
    {
        $delta = $workedHours - $expectedHours;
        if (abs($delta) <= $toleranceHours) {
            $delta = 0.0;
        }

        return [
            'expected_hours' => round($expectedHours, 2),
            'delta_hours' => round($delta, 2),
            'extra_hours' => round(max(0.0, $delta), 2),
            'owed_hours' => round(max(0.0, -$delta), 2),
        ];
    }
}
