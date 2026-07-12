<?php

namespace App\Services\Dashboard;

class DashboardCoordinatorService
{
    public function routeByRole(?string $role): string
    {
        return match (strtolower((string) $role)) {
            'admin' => '/dashboard/admin',
            'gestor', 'rh' => '/dashboard/manager',
            'dpo' => '/dashboard/dpo',
            'funcionario' => '/dashboard/employee',
            default => '/dashboard/employee',
        };
    }
}
