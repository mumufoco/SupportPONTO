<?php

namespace App\Controllers\Compliance;

use App\Controllers\BaseController;
use App\Services\Compliance\PermissionMatrixService;

class PermissionMatrixController extends BaseController
{
    public function index()
    {
        $service = new PermissionMatrixService();

        return view('compliance/permissions_matrix', [
            'rows' => $service->getRows(),
        ]);
    }
}
