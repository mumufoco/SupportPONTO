<?php

namespace App\Services\Notification\Push;

use CodeIgniter\Database\ConnectionInterface;

class PushTokenRepository
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function register(int $employeeId, string $deviceToken, string $platform, string $deviceName): bool
    {
        $existing = $this->db->table('push_notification_tokens')
            ->where('device_token', $deviceToken)
            ->get()
            ->getRow();

        if ($existing) {
            return $this->db->table('push_notification_tokens')
                ->where('id', $existing->id)
                ->update([
                    'employee_id' => $employeeId,
                    'platform' => $platform,
                    'device_name' => $deviceName,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]) > 0;
        }

        return (bool) $this->db->table('push_notification_tokens')->insert([
            'employee_id' => $employeeId,
            'device_token' => $deviceToken,
            'platform' => $platform,
            'device_name' => $deviceName,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unregister(string $deviceToken): bool
    {
        return $this->db->table('push_notification_tokens')
            ->where('device_token', $deviceToken)
            ->delete() > 0;
    }

    public function employeeTokens(int $employeeId): array
    {
        $tokens = $this->db->table('push_notification_tokens')
            ->select('device_token')
            ->where('employee_id', $employeeId)
            ->where('is_valid', true)
            ->get()
            ->getResult();

        return array_column($tokens, 'device_token');
    }

    public function markInvalid(string $deviceToken): void
    {
        $this->db->table('push_notification_tokens')
            ->where('device_token', $deviceToken)
            ->update(['is_valid' => false]);
    }

    public function cleanupInvalid(): int
    {
        return $this->db->table('push_notification_tokens')
            ->where('is_valid', false)
            ->delete();
    }
}
