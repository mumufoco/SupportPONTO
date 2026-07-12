<?php

namespace App\Controllers\Compliance;

use App\Controllers\BaseController;
use App\Services\Compliance\SecurityCenterService;

class SecurityCenterController extends BaseController
{
    public function index()
    {
        $service = new SecurityCenterService();

        return view('compliance/security_center', [
            'checks' => $service->getChecks(),
        ]);
    }
}
