<?php

namespace App\Services\Dashboard;

class DashboardService
{
    public function getRole(): string
    {
        helper(['navigation_context']);

        return sp_session_role();
    }

    public function getCardsByRole(string $role): array
    {
        helper(['navigation_context']);

        return sp_dashboard_shortcuts_for_role($role);
    }
}
