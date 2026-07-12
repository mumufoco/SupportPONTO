<?php

declare(strict_types=1);

namespace App\Controllers\Compatibility;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\RedirectResponse;

final class LegacyRouteRedirectController extends BaseController
{
    public function settingsCenter(): RedirectResponse
    {
        return redirect()->to(route_to('admin.settings'));
    }

    public function settingsAppearance(): RedirectResponse
    {
        return redirect()->to(route_to('admin.settings.appearance'));
    }

    public function settingsSecurity(): RedirectResponse
    {
        return redirect()->to(route_to('admin.settings.security'));
    }

    public function settingsSystem(): RedirectResponse
    {
        return redirect()->to(route_to('admin.settings.system'));
    }

    public function settingsAuthentication(): RedirectResponse
    {
        return redirect()->to(route_to('admin.settings.authentication'));
    }

    public function geofencesIndex(): RedirectResponse
    {
        return redirect()->to(route_to('geofences'));
    }

    public function geofencesCreate(): RedirectResponse
    {
        return redirect()->to(route_to('geofences.create'));
    }

    public function geofencesMap(): RedirectResponse
    {
        return redirect()->to(route_to('geofences.map'));
    }

    public function geofencesShow(int $id): RedirectResponse
    {
        return redirect()->to(route_to('geofences.show', $id));
    }

    public function geofencesEdit(int $id): RedirectResponse
    {
        return redirect()->to(route_to('geofences.edit', $id));
    }
}
