<?php

namespace App\Services\Notification\Push;

class FcmHttpClient
{
    private string $endpoint = 'https://fcm.googleapis.com/fcm/send';

    public function __construct(private readonly string $serverKey)
    {
    }

    public function send(array $message): array
    {
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            log_message('error', "FCM request failed: {$error}");
            return [
                'success' => false,
                'error' => $error,
            ];
        }

        $response = json_decode((string) $result, true);

        if ($httpCode !== 200) {
            log_message('error', "FCM returned HTTP {$httpCode}: {$result}");
            return [
                'success' => false,
                'error' => "HTTP {$httpCode}",
                'response' => $response,
            ];
        }

        log_message('info', 'FCM notification sent successfully');

        return array_merge([
            'success' => true,
            'http_code' => $httpCode,
        ], is_array($response) ? $response : []);
    }
}
