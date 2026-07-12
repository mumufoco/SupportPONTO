<?php

namespace App\Services;

class SessionAuthorizationContext
{
    public function getUserId(): int
    {
        helper(['session_context']);

        return (int) (sp_session_user_id() ?? 0);
    }

    public function isActive(): bool
    {
        helper(['session_context']);

        return sp_session_user()['active'];
    }

    public function getNormalizedRole(): string
    {
        helper(['navigation_context']);

        return sp_session_role();
    }

    public function getDashboardUrlForRole(string $role): string
    {
        helper(['navigation_context']);

        return sp_dashboard_url_for_role($role);
    }
}
