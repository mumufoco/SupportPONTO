<?php

declare(strict_types=1);

namespace App\Support\Dates;

use DateTimeImmutable;

final class DashboardDateRange
{
    public static function day(?string $date = null): array
    {
        $base = new DateTimeImmutable($date ?: 'now');
        $start = $base->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    public static function month(?string $date = null): array
    {
        $base = new DateTimeImmutable($date ?: 'now');
        $start = $base->modify('first day of this month')->setTime(0, 0, 0);
        $end = $start->modify('+1 month');

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }
}
