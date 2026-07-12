<?php

namespace App\Services\Analytics\Dashboard;

class DashboardFilterResolver
{
    public function normalize(?string $startDate = null, ?string $endDate = null, ?int $departmentId = null): array
    {
        return [
            'startDate' => $startDate ?: date('Y-m-d'),
            'endDate' => $endDate ?: date('Y-m-d'),
            'departmentId' => $departmentId,
        ];
    }

    public function fromArray(array $filters): array
    {
        return $this->normalize(
            $filters['startDate'] ?? null,
            $filters['endDate'] ?? null,
            $filters['departmentId'] ?? null,
        );
    }
}
