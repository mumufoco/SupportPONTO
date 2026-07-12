<?php

namespace App\Services\Notification\Push;

class PushPayloadBuilder
{
    public function forDevices(array $deviceTokens, string $title, string $body, array $data = [], array $options = []): array
    {
        $notification = [
            'title' => $title,
            'body' => $body,
            'icon' => $options['icon'] ?? 'ic_notification',
            'sound' => $options['sound'] ?? 'default',
            'badge' => $options['badge'] ?? 1,
            'click_action' => $options['click_action'] ?? 'FLUTTER_NOTIFICATION_CLICK',
        ];

        return [
            'registration_ids' => $deviceTokens,
            'notification' => $notification,
            'data' => array_merge($data, [
                'title' => $title,
                'body' => $body,
                'click_action' => $notification['click_action'],
            ]),
            'priority' => $options['priority'] ?? 'high',
            'content_available' => true,
        ];
    }

    public function forTopic(string $topic, string $title, string $body, array $data = [], array $options = []): array
    {
        return [
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => $options['icon'] ?? 'ic_notification',
                'sound' => $options['sound'] ?? 'default',
            ],
            'data' => array_merge($data, [
                'title' => $title,
                'body' => $body,
            ]),
            'priority' => $options['priority'] ?? 'high',
        ];
    }
}
