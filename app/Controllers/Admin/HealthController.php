<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Health\SystemHealthCheckService;
use App\Services\Support\SupportDiagnosticsService;
use App\Services\Support\ApplicationSupportBundleService;
use CodeIgniter\HTTP\ResponseInterface;

class HealthController extends BaseController
{
    private SystemHealthCheckService $health;

    public function __construct()
    {
        $this->health = new SystemHealthCheckService();
    }

    public function index()
    {
        return view('admin/health/index', [
            'items' => $this->health->adminHealthItems(),
            'details' => $this->health->detailedHealth(),
        ]);
    }

    public function json(): ResponseInterface
    {
        return $this->attachResponseContext($this->response, true)
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON($this->health->detailedHealth())
            ->setStatusCode(200);
    }


    public function supportBundle(): ResponseInterface
    {
        $result = (new ApplicationSupportBundleService())->build();

        return $this->attachResponseContext($this->response, true)
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON($result)
            ->setStatusCode(($result['success'] ?? false) ? 200 : 500);
    }

    public function diagnostics()
    {
        $service = new SupportDiagnosticsService();

        return view('admin/health/support_diagnostics', [
            'report' => $service->build(false),
        ]);
    }
}
