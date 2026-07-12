<?php

namespace App\Controllers\Operations;

use App\Controllers\BaseController;
use App\Services\Operations\PendingCenterService;

class PendingCenterController extends BaseController
{
    public function index()
    {
        $service = new PendingCenterService();

        return view('operations/pending_center', [
            'pendingItems' => $service->getPendingItems(),
        ]);
    }
}
