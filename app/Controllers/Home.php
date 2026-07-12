<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\Dashboard\DashboardCoordinatorService;

class Home extends BaseController
{
    /**
     * Home page - Redirect to appropriate location based on authentication
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function index()
    {
        if ($this->session && $this->session->has('user_id')) {
            $role = $this->session->get('user_role');
            $redirectUrl = $this->getRoleBasedRedirect($role);
            return redirect()->to($redirectUrl);
        }

        // Not authenticated - redirect to login
        return redirect()->to(sp_login_url());
    }

    protected function getRoleBasedRedirect(?string $role): string
    {
        return (new DashboardCoordinatorService())->routeByRole($role);
    }
}
