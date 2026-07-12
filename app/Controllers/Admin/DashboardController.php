<?php

namespace App\Controllers\Admin;

/**
 * Legacy compatibility wrapper for the admin dashboard.
 *
 * Canonical dashboard flow lives in \App\Controllers\Dashboard\DashboardController.
 * Keep this class lean to avoid duplicated dashboard business rules.
 */
class DashboardController extends \App\Controllers\Dashboard\DashboardController
{
    public function index()
    {
        return $this->admin();
    }
}
