<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'type',
        'title',
        'message',
        'link',
        'icon',
        'priority',
        'read',
        'read_at',
        'sent_email',
        'sent_push',
        'sent_sms',
        'related_entity_type',
        'related_entity_id',
        'expires_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = false; // Notifications are immutable

    // Validation
    protected $validationRules = [
        'user_id'  => 'required|integer',
        'type'     => 'required|in_list[punch_registered,justification_approved,justification_rejected,warning_issued,balance_alert,chat_message,system]',
        'title'    => 'required|max_length[255]',
        'message'  => 'required',
        'priority' => 'in_list[low,normal,high,urgent]',
    ];

    protected $validationMessages = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Create notification
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        ?string $icon = null,
        string $priority = 'normal',
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $expiresAt = null
    ): int|false {
        $data = [
            'user_id'             => $userId,
            'type'                => $type,
            'title'               => $title,
            'message'             => $message,
            'link'                => $link,
            'icon'                => $icon,
            'priority'            => $priority,
            'related_entity_type' => $relatedEntityType,
            'related_entity_id'   => $relatedEntityId,
            'expires_at'          => $expiresAt,
        ];

        return $this->insert($data);
    }

    /**
     * Get unread notifications for user
     */
    public function getUnread(int $userId, ?int $limit = 50): array
    {
        return $this->where('user_id', $userId)
            ->where('read', false)
            ->groupStart()
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orWhere('expires_at', null)
            ->groupEnd()
            ->orderBy('priority DESC, created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get unread count
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->where('user_id', $userId)
            ->where('read', false)
            ->groupStart()
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->orWhere('expires_at', null)
            ->groupEnd()
            ->countAllResults();
    }

    /**
     * Get all notifications for user
     */
    public function getByUser(int $userId, ?int $limit = 100): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): bool
    {
        return $this->update($notificationId, [
            'read'    => true,
            'read_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead(int $userId): bool
    {
        return $this->where('user_id', $userId)
            ->where('read', false)
            ->set([
                'read'    => true,
                'read_at' => date('Y-m-d H:i:s'),
            ])
            ->update();
    }

    /**
     * Delete notification
     */
    public function deleteNotification(int $notificationId): bool
    {
        return $this->delete($notificationId);
    }

    /**
     * Delete old notifications (cleanup)
     */
    public function deleteOld(int $days = 30): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $this->where('created_at <', $cutoffDate)
            ->where('read', true)
            ->delete();
    }

    /**
     * Delete expired notifications
     */
    public function deleteExpired(): int
    {
        return $this->where('expires_at <=', date('Y-m-d H:i:s'))
            ->delete();
    }

    /**
     * Mark email as sent
     */
    public function markEmailSent(int $notificationId): bool
    {
        return $this->update($notificationId, ['sent_email' => true]);
    }

    /**
     * Mark push as sent
     */
    public function markPushSent(int $notificationId): bool
    {
        return $this->update($notificationId, ['sent_push' => true]);
    }

    /**
     * Mark SMS as sent
     */
    public function markSmsSent(int $notificationId): bool
    {
        return $this->update($notificationId, ['sent_sms' => true]);
    }

    /**
     * Get notifications by type
     */
    public function getByType(string $type, int $userId): array
    {
        return $this->where('user_id', $userId)
            ->where('type', $type)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get urgent notifications
     */
    public function getUrgent(int $userId): array
    {
        return $this->where('user_id', $userId)
            ->where('priority', 'urgent')
            ->where('read', false)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Notify multiple users
     */
    public function notifyMultiple(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        string $priority = 'normal'
    ): int {
        $count = 0;

        foreach ($userIds as $userId) {
            if ($this->notify($userId, $type, $title, $message, $link, null, $priority)) {
                $count++;
            }
        }

        return $count;
    }
}
