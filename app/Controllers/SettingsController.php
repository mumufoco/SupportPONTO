<?php

namespace App\Controllers;

use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\RoleModel;
use App\Models\SettingModel;
use App\Models\WorkUnitModel;

class SettingsController extends BaseController
{
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
     * Canonical settings entry point. A tela de visão geral (tile grid) foi
     * removida por ser redundante com os links diretos do menu lateral —
     * aponta direto para a primeira página real de configurações.
     */
    public function index()
    {
        $this->requireRole('admin');

        return redirect()->to(route_to('admin.settings'));
    }
}
