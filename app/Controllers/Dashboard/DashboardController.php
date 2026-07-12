<?php

namespace App\Controllers\Dashboard;

use App\Controllers\BaseController;
use App\Services\Dashboard\DashboardAdminService;
use App\Services\Dashboard\DashboardCoordinatorService;
use App\Services\Dashboard\DashboardEmployeeService;
use App\Services\Dashboard\DashboardManagerService;
use App\Services\Dashboard\DashboardDpoService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Canonical dashboard controller.
 * Legacy wrappers must delegate here without carrying business logic.
 */
class DashboardController extends BaseController
{
    protected DashboardCoordinatorService $dashboardCoordinatorService;
    protected DashboardAdminService $dashboardAdminService;
    protected DashboardManagerService $dashboardManagerService;
    protected DashboardEmployeeService $dashboardEmployeeService;
    protected DashboardDpoService $dashboardDpoService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->dashboardCoordinatorService = new DashboardCoordinatorService();
        $this->dashboardAdminService = new DashboardAdminService();
        $this->dashboardManagerService = new DashboardManagerService();
        $this->dashboardEmployeeService = new DashboardEmployeeService();
        $this->dashboardDpoService = new DashboardDpoService();
    }

    /**
     * Main dashboard - redirects based on role
     */
    public function index(): ResponseInterface
    {
        if (!$this->session || !$this->session->has('user_id')) {
            supportponto_log_event('warning', 'dashboard', 'redirect_login_missing_session', [
                'path' => $this->request->getUri()->getPath(),
            ]);
            return redirect()->to(sp_login_url());
        }

        $role = $this->session->get('user_role');
        $target = $this->dashboardCoordinatorService->routeByRole(is_string($role) ? $role : null);

        supportponto_log_event('info', 'dashboard', 'index_redirect', [
            'user_id' => $this->session->get('user_id'),
            'role' => $role,
            'target' => $target,
        ]);

        return redirect()->to($target);
    }

    /**
     * Admin dashboard
     */
    public function admin(): ResponseInterface|string
    {
        $this->requireRole('admin');

        supportponto_log_event('info', 'dashboard', 'admin_opened', [
            'user_id' => $this->session?->get('user_id'),
        ]);

        return view('dashboard/admin', $this->dashboardAdminService->buildViewData($this->currentUser));
    }

    /**
     * Manager dashboard
     */
    public function manager(): ResponseInterface|string
    {
        $this->requireAnyRole(['gestor', 'rh']);

        supportponto_log_event('info', 'dashboard', 'manager_opened', [
            'user_id' => $this->session?->get('user_id'),
        ]);

        return view('dashboard/manager', $this->dashboardManagerService->buildViewData($this->currentUser));
    }


    /**
     * DPO dashboard
     */
    public function dpo(): ResponseInterface|string
    {
        $this->requireAnyRole(['admin', 'dpo']);

        supportponto_log_event('info', 'dashboard', 'dpo_opened', [
            'user_id' => $this->session?->get('user_id'),
        ]);

        return view('dashboard/dpo', $this->dashboardDpoService->buildViewData($this->currentUser));
    }

    /**
     * Employee dashboard
     */
    public function employee(): ResponseInterface|string
    {
        $this->requireAuth();

        supportponto_log_event('info', 'dashboard', 'employee_opened', [
            'user_id' => $this->session?->get('user_id'),
        ]);

        return view('dashboard/employee', $this->dashboardEmployeeService->buildViewData($this->currentUser));
    }
}
