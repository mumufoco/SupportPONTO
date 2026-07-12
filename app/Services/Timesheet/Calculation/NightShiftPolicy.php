<?php

declare(strict_types=1);

namespace App\Services\Timesheet\Calculation;

use DateTimeImmutable;

class NightShiftPolicy
{
    /**
     * CLT art. 73 §1º: a "hora noturna" urbana equivale a 52min30s de relógio, não 60
     * minutos — cada hora de relógio trabalhada no período noturno conta como
     * 60/52,5 ≈ 1,142857 "hora noturna" para fins de adicional (MED-01 na auditoria:
     * antes, o cálculo somava só os segundos de relógio sobrepostos com a janela
     * 22h–5h e dividia por 3600, subestimando o adicional devido em ~14%).
     */
    private const NIGHT_HOUR_SECONDS = 52.5 * 60;

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

        // Divide pela "hora noturna reduzida" (52min30s), não por 3600 — ver
        // NIGHT_HOUR_SECONDS acima.
        return round($seconds / self::NIGHT_HOUR_SECONDS, 2);
    }
}
