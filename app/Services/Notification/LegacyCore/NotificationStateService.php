<?php

namespace App\Services\Notification\LegacyCore;

use App\Models\NotificationModel;

class NotificationStateService
{
    public function __construct(private readonly NotificationModel $notificationModel)
    {
    }

    public function markAsRead(int $notificationId, int $employeeId): bool
    {
        $notification = $this->notificationModel->find($notificationId);

        if (!$notification || $this->ownerId($notification) !== $employeeId) {
            return false;
        }

        return $this->notificationModel->update($notificationId, [
            'read' => true,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markAllAsRead(int $employeeId): int
    {
        return $this->notificationModel
            ->where('user_id', $employeeId)
            ->where('read', false)
            ->set([
                'read' => true,
                'read_at' => date('Y-m-d H:i:s'),
            ])
            ->update();
    }

    public function getUnread(int $employeeId, int $limit = 10): array
    {
        return $this->notificationModel
            ->where('user_id', $employeeId)
            ->where('read', false)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getAll(int $employeeId, int $limit = 20, int $offset = 0): array
    {
        return $this->notificationModel
            ->where('user_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit, $offset)
            ->findAll();
    }

    public function countUnread(int $employeeId): int
    {
        return $this->notificationModel
            ->where('user_id', $employeeId)
            ->where('read', false)
            ->countAllResults();
    }

    public function delete(int $notificationId, int $employeeId): bool
    {
        $notification = $this->notificationModel->find($notificationId);

        if (!$notification || $this->ownerId($notification) !== $employeeId) {
            return false;
        }

        return $this->notificationModel->delete($notificationId);
    }

    public function deleteAllRead(int $employeeId): int
    {
        return $this->notificationModel
            ->where('user_id', $employeeId)
            ->where('read', true)
            ->delete();
    }

    public function deleteOld(int $daysOld = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        return $this->notificationModel
            ->where('created_at <', $cutoffDate)
            ->where('read', true)
            ->delete();
    }

    public function getStatistics(): array
    {
        return [
            'total_notifications' => $this->notificationModel->countAllResults(false),
            'unread_notifications' => $this->notificationModel
                ->where('read', false)
                ->countAllResults(),
            'notifications_today' => $this->notificationModel
                ->where('created_at >=', date('Y-m-d 00:00:00'))
                ->where('created_at <', date('Y-m-d H:i:s', strtotime('+1 day midnight')))
                ->countAllResults(),
        ];
    }

    private function ownerId(object $notification): int
    {
        if (isset($notification->user_id)) {
            return (int) $notification->user_id;
        }

        if (isset($notification->employee_id)) {
            return (int) $notification->employee_id;
        }

        return 0;
    }
}
