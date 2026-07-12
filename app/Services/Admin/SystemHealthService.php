<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Services\Monitoring\SystemObservabilityService;

class SystemHealthService
{
    public function getHealthItems(): array
    {
        return (new SystemObservabilityService())->adminHealthItems();
    }

    public function getHealthDetails(): array
    {
        return (new SystemObservabilityService())->detailedHealth();
    }
}
