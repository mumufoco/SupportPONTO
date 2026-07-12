<?php

namespace App\Services\Reports;

class ReportControllerActionService
{
    public function monthOrCurrent(?string $month): string
    {
        return ($month && $month !== '') ? $month : date('Y-m');
    }

    public function monthRange(string $month): array
    {
        $startDate = $month . '-01';
        return [
            'start_date' => $startDate,
            'end_date' => date('Y-m-t', strtotime($startDate)),
        ];
    }

    public function filtersFromPost(mixed $filters): array
    {
        return is_array($filters) ? $filters : [];
    }

    public function hasPeriod(array $filters): bool
    {
        return !empty($filters['start_date']) && !empty($filters['end_date']);
    }
}
