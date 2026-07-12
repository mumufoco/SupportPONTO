<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

use DateTimeImmutable;

class NightShiftPolicy
{
    /** @param list<array<string,mixed>> $workPairs */
    public function calculateNightHours(array $workPairs): float
    {
        $seconds = 0;

        foreach ($workPairs as $pair) {
            if (! isset($pair['start_at'], $pair['end_at'])) {
                continue;
            }

            try {
                $start = new DateTimeImmutable((string) $pair['start_at']);
                $end = new DateTimeImmutable((string) $pair['end_at']);
            } catch (\Throwable) {
                continue;
            }

            if ($end <= $start) {
                continue;
            }

            $cursor = $start->setTime(0, 0);
            $limit = $end->modify('+1 day')->setTime(0, 0);

            while ($cursor < $limit) {
                $nightStart = $cursor->setTime(22, 0);
                $nightEnd = $cursor->modify('+1 day')->setTime(5, 0);
                $overlapStart = max($start->getTimestamp(), $nightStart->getTimestamp());
                $overlapEnd = min($end->getTimestamp(), $nightEnd->getTimestamp());

                if ($overlapEnd > $overlapStart) {
                    $seconds += $overlapEnd - $overlapStart;
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        return round($seconds / 3600, 2);
    }
}
