<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SettingModel;
use App\Services\Health\SystemHealthCheckService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Health endpoints para liveness/readiness.
 *
 * Endpoints públicos não expõem detalhes sensíveis. O diagnóstico detalhado
 * exige admin autenticado ou token interno X-Health-Token/HEALTH_DETAILS_TOKEN.
 */
class HealthController extends BaseController
{
    private SystemHealthCheckService $health;

    public function __construct()
    {
        $this->health = new SystemHealthCheckService();
    }

    public function index(): ResponseInterface
    {
        return $this->liveness();
    }

    public function healthz(): ResponseInterface
    {
        return $this->liveness();
    }

    public function liveness(): ResponseInterface
    {
        $payload = $this->health->liveness();

        return $this->attachResponseContext($this->response, true)
            ->setHeader('X-Health-Status', 'alive')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON($payload)
            ->setStatusCode(200);
    }

    public function readiness(): ResponseInterface
    {
        $payload = $this->health->readiness();
        $ready = ($payload['status'] ?? 'not_ready') === 'ready';

        return $this->attachResponseContext($this->response, true)
            ->setHeader('X-Readiness-Status', $ready ? 'ready' : 'not_ready')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON($payload)
            ->setStatusCode($ready ? 200 : 503);
    }

    public function detailed(): ResponseInterface
    {
        if (! $this->isDetailedAccessAuthorized()) {
            return $this->attachResponseContext($this->response, true)
                ->setHeader('Cache-Control', 'no-store, max-age=0')
                ->setJSON(['error' => 'health_details_forbidden'])
                ->setStatusCode(403);
        }

        return $this->attachResponseContext($this->response, true)
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON($this->health->detailedHealth())
            ->setStatusCode(200);
    }

    private function isDetailedAccessAuthorized(): bool
    {
        if ($this->isAuthenticated() && $this->hasRole('admin')) {
            return true;
        }

        $provided = trim((string) $this->request->getHeaderLine('X-Health-Token'));
        $expected = trim((string) (getenv('HEALTH_DETAILS_TOKEN') ?: (new SettingModel())->get('health_details_token', '')));

        if ($provided !== '' && $expected !== '' && hash_equals($expected, $provided)) {
            return true;
        }

        return ENVIRONMENT !== 'production' && $expected === '';
    }
}
