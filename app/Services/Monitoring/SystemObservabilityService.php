<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Services\Health\SystemHealthCheckService;

/**
 * Camada de compatibilidade para chamadas legadas de observabilidade.
 * A implementação canônica fica em App\Services\Health\SystemHealthCheckService.
 */
class SystemObservabilityService extends SystemHealthCheckService
{
    public function publicHealth(): array
    {
        return $this->liveness();
    }
}
