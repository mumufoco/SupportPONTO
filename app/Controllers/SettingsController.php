<?php

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\RoleModel;
use App\Models\SettingModel;
use App\Models\WorkUnitModel;

class SettingsController extends BaseController
{
    private const SETTINGS_GROUPS = [
        'general',
        'workday',
        'geolocation',
        'notifications',
        'biometry',
        'apis',
        'icp',
        'lgpd',
        'backup',
    ];

    protected WorkUnitModel $workUnitModel;
    protected DepartmentModel $departmentModel;
    protected PositionModel $positionModel;
    protected RoleModel $roleModel;
    protected SettingModel $settingModel;

    public function __construct()
    {
        $this->workUnitModel = new WorkUnitModel();
        $this->departmentModel = new DepartmentModel();
        $this->positionModel = new PositionModel();
        $this->roleModel = new RoleModel();
        $this->settingModel = new SettingModel();
    }

    /**
     * Canonical settings dashboard (orchestration only).
     */
    public function index()
    {
        $this->requireRole('admin');

        return redirect()->to(route_to('admin.settings'));
    }

    /**
     * Modern admin settings portal — tile grid linking to specialized settings areas.
     */
    public function adminIndex()
    {
        $this->requireRole('admin');

        $grouped = $this->settingModel->getAllGrouped();

        return view('admin/settings/index', [
            'stats' => [
                'appearance'     => count($grouped['appearance'] ?? []),
                'authentication' => count($grouped['authentication'] ?? []),
                'security'       => count($grouped['security'] ?? []),
                'system'         => count($grouped['system'] ?? []),
            ],
        ]);
    }

    private function getSettingsDashboardGroups(): array
    {
        $grouped = $this->settingModel->getAllGrouped();

        foreach (self::SETTINGS_GROUPS as $group) {
            $grouped[$group] = $grouped[$group] ?? [];
        }

        return $grouped;
    }
}
