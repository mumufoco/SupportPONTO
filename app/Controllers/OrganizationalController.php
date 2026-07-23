<?php

namespace App\Controllers;

use App\Models\WorkUnitModel;
use App\Models\DepartmentModel;
use App\Models\PositionModel;
use App\Models\RoleModel;
use App\Models\HolidayModel;

class OrganizationalController extends BaseController
{
    public function index()
    {
        $this->requireRole('admin');

        $workUnitModel = new WorkUnitModel();
        $departmentModel = new DepartmentModel();
        $positionModel = new PositionModel();
        $roleModel = new RoleModel();
        $holidayModel = new HolidayModel();

        return view('organizational/index', [
            'workUnits' => $workUnitModel->findAll(),
            'departments' => $departmentModel->getActive(),
            'positions' => $positionModel->findAll(),
            'roles' => $roleModel->findAll(),
            'holidays' => $holidayModel->findAll(),
        ]);
    }
}
