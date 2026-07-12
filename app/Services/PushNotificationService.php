<?php

namespace App\Services;

use App\Models\PushSubscriptionModel;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Push Notification Service
 *
 * Handles browser push notifications using Web Push Protocol
 */
class PushNotificationService
{
    protected PushSubscriptionModel $subscriptionModel;
    protected array $auth;

    public function __construct()
    {
        $this->subscriptionModel = new PushSubscriptionModel();

        // VAPID keys configuration
        // Generate keys using: vendor/bin/web-push generate-vapid-keys
        $this->auth = [
            'VAPID' => [
                'subject'    => env('VAPID_SUBJECT', 'mailto:admin@pontoeletronico.com.br'),
                'publicKey'  => env('VAPID_PUBLIC_KEY', ''),
                'privateKey' => env('VAPID_PRIVATE_KEY', ''),
            ],
        ];
    }

    /**
     * Send push notification to employee
     *
     * @param int    $employeeId
     * @param string $title
     * @param string $body
     * @param array  $data Additional data
     * @return array Results
     */
    public function sendToEmployee(int $employeeId, string $title, string $body, array $data = []): array
    {
        $subscriptions = $this->subscriptionModel->getEmployeeSubscriptions($employeeId);

        if (empty($subscriptions)) {
            return [
                'success' => false,
                'message' => 'No active subscriptions found for employee.',
                'sent'    => 0,
            ];
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => '/assets/img/icon-192.png',
            'badge' => '/assets/img/badge-72.png',
            'data'  => $data,
        ]);

        return $this->sendToSubscriptions($subscriptions, $payload);
    }

    /**
     * Send chat message notification
     *
     * @param int    $employeeId Recipient employee ID
     * @param string $senderName
     * @param string $message
     * @param int    $roomId
     * @return array
     */
    public function sendChatMessage(int $employeeId, string $senderName, string $message, int $roomId): array
    {
        $title = $senderName;
        $body = mb_strlen($message) > 100 ? mb_substr($message, 0, 100) . '...' : $message;

        $data = [
            'type'    => 'chat_message',
            'room_id' => $roomId,
            'url'     => '/chat/room/' . $roomId,
        ];

        return $this->sendToEmployee($employeeId, $title, $body, $data);
    }

    /**
     * Send push notification to multiple subscriptions
     *
     * @param array  $subscriptions
     * @param string $payload JSON payload
     * @return array
     */
    protected function sendToSubscriptions(array $subscriptions, string $payload): array
    {
        $webPush = new WebPush($this->auth);
        $webPush->setAutomaticPadding(true);

        $sent = 0;
        $failed = 0;
        $expired = [];

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint'        => $sub->endpoint,
                'publicKey'       => $sub->public_key,
                'authToken'       => $sub->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]);

            try {
                $webPush->queueNotification($subscription, $payload);
            } catch (\Exception $e) {
                log_message('error', 'Push notification queue error: ' . $e->getMessage());
                $failed++;
            }
        }

        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;

                // Handle expired subscriptions
                if ($report->isSubscriptionExpired()) {
                    $expired[] = $endpoint;
                    $this->handleExpiredSubscription($endpoint);
                }

                log_message('error', 'Push notification failed for endpoint ' . $endpoint . ': ' . $report->getReason());
            }
        }

        return [
            'success' => $sent > 0,
            'sent'    => $sent,
            'failed'  => $failed,
            'expired' => count($expired),
        ];
    }

    /**
     * Handle expired subscription
     *
     * @param string $endpoint
     * @return void
     */
    protected function handleExpiredSubscription(string $endpoint): void
    {
        $subscription = $this->subscriptionModel->getByEndpoint($endpoint);

        if ($subscription) {
            $this->subscriptionModel->update($subscription->id, ['active' => false]);
        }
    }

    /**
     * Subscribe employee to push notifications
     *
     * @param int   $employeeId
     * @param array $subscription Subscription object from browser
     * @return array
     */
    public function subscribe(int $employeeId, array $subscription): array
    {
        if (empty($subscription['endpoint'])) {
            return [
                'success' => false,
                'message' => 'Endpoint é obrigatório.',
            ];
        }

        $keys = $subscription['keys'] ?? [];

        $result = $this->subscriptionModel->subscribe(
            $employeeId,
            $subscription['endpoint'],
            $keys['p256dh'] ?? '',
            $keys['auth'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        );

        if ($result) {
            return [
                'success' => true,
                'message' => 'Inscrito com sucesso em notificações push.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro ao inscrever em notificações push.',
        ];
    }

    /**
     * Unsubscribe employee from push notifications
     *
     * @param int    $employeeId
     * @param string $endpoint
     * @return array
     */
    public function unsubscribe(int $employeeId, string $endpoint): array
    {
        $result = $this->subscriptionModel->unsubscribe($employeeId, $endpoint);

        if ($result) {
            return [
                'success' => true,
                'message' => 'Desinscrito com sucesso de notificações push.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Erro ao desinscrever de notificações push.',
        ];
    }

    /**
     * Get VAPID public key for client
     *
     * @return string
     */
    public function getPublicKey(): string
    {
        return $this->auth['VAPID']['publicKey'];
    }

    /**
     * Test notification
     *
     * @param int $employeeId
     * @return array
     */
    public function sendTestNotification(int $employeeId): array
    {
        return $this->sendToEmployee(
            $employeeId,
            'Notificação de Teste',
            'As notificações push estão funcionando corretamente!',
            ['type' => 'test']
        );
    }
}
