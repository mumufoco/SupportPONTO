<?php

namespace App\Services\Operations;

use Config\Database;

class NotificationCenterService
{
    private const TYPE_META = [
        'pending_expiry_urgent'  => ['status' => 'Crítico',  'variant' => 'danger'],
        'pending_expiry_warning' => ['status' => 'Atenção',  'variant' => 'warning'],
        'pending_punch_expiry'   => ['status' => 'Crítico',  'variant' => 'danger'],
        'justification'          => ['status' => 'Pendente', 'variant' => 'pending'],
        'employee_registration'  => ['status' => 'Pendente', 'variant' => 'pending'],
        'warning'                => ['status' => 'Atenção',  'variant' => 'warning'],
        'success'                => ['status' => 'OK',       'variant' => 'success'],
        'info'                   => ['status' => 'Info',     'variant' => 'info'],
    ];

    public function getNotifications(?int $userId = null, int $limit = 50): array
    {
        if ($userId === null) {
            $userId = (int) (session()->get('user_id') ?? 0);
        }

        if ($userId === 0) {
            log_message('critical', '[NotifCenter] user_id=0, nenhuma sessão ativa');
            return [];
        }

        $db = Database::connect();

        $rows = $db->query(
            "SELECT id, user_id, type, title, message, link, icon, priority, read, created_at
             FROM notifications
             WHERE user_id = ?
               AND type NOT LIKE '%_dedup_stamp'
               AND title NOT LIKE '_dedup_%'
             ORDER BY (priority = 'high') DESC, read ASC, created_at DESC
             LIMIT ?",
            [$userId, $limit]
        )->getResultArray();

        log_message('critical', '[NotifCenter] userId=' . $userId . ' rows=' . count($rows));

        $result = [];
        foreach ($rows as $row) {
            $meta = self::TYPE_META[$row['type']] ?? ['status' => 'Pendente', 'variant' => 'pending'];
            $url  = !empty($row['link']) ? $row['link'] : site_url('operations/notifications-center');

            $result[] = [
                'id'      => (int) $row['id'],
                'title'   => $row['title'],
                'message' => $row['message'],
                'when'    => $this->humanDate((string) $row['created_at']),
                'status'  => $meta['status'],
                'variant' => $meta['variant'],
                'url'     => $url,
                'read'    => (bool) $row['read'],
                'priority'=> $row['priority'],
            ];
        }

        return $result;
    }

    private function humanDate(string $dateStr): string
    {
        $ts   = strtotime($dateStr);
        $date = date('Y-m-d', $ts);
        $time = date('H:i', $ts);

        if ($date === date('Y-m-d')) {
            return 'Hoje, ' . $time;
        }
        if ($date === date('Y-m-d', strtotime('-1 day'))) {
            return 'Ontem, ' . $time;
        }
        return date('d/m/Y', $ts) . ', ' . $time;
    }
}
