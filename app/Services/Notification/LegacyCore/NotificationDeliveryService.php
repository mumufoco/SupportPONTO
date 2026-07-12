<?php

namespace App\Services\Notification\LegacyCore;

use App\Models\NotificationModel;

class NotificationDeliveryService
{
    public function __construct(private readonly NotificationModel $notificationModel)
    {
    }

    public function notify(int $employeeId, string $title, string $message, string $type = 'info', ?string $link = null)
    {
        return $this->notificationModel->insert([
            'user_id' => $employeeId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'read' => false,
        ]);
    }

    public function notifyMultiple(array $employeeIds, string $title, string $message, string $type = 'info', ?string $link = null): int
    {
        $count = 0;

        foreach ($employeeIds as $employeeId) {
            if ($this->notify((int) $employeeId, $title, $message, $type, $link)) {
                $count++;
            }
        }

        return $count;
    }
}
