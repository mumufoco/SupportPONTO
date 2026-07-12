<?php

namespace App\Controllers\Operations;

use App\Controllers\BaseController;
use App\Services\Operations\NotificationCenterService;

class NotificationCenterController extends BaseController
{
    public function index()
    {
        try {
            $service = new NotificationCenterService();
            $notifications = $service->getNotifications();
            log_message('debug', '[NotifCenter] userId=' . (session()->get('user_id') ?? 'null') . ' count=' . count($notifications));
        } catch (\Throwable $e) {
            log_message('error', '[NotifCenter] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $notifications = null;
        }

        return view('operations/notifications_center', [
            'notifications' => $notifications ?? null,
        ]);
    }
}
