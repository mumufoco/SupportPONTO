<?php

namespace App\Services\SMS;

class SMSProviderTransport
{
    private string $provider;
    private AWSRequestSigner $awsRequestSigner;

    public function __construct(?string $provider = null, ?AWSRequestSigner $awsRequestSigner = null)
    {
        $this->provider = $provider ?? (string) env('SMS_PROVIDER', 'mock');
        $this->awsRequestSigner = $awsRequestSigner ?? new AWSRequestSigner();
    }

    public function sendVerificationCode(string $phone, string $code, string $message, int $maxAttempts = 1): array
    {
        $attempts = max(1, $maxAttempts);
        $lastError = 'Erro desconhecido';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $result = $this->sendOnce($phone, $code, $message);
            if (($result['success'] ?? false) === true) {
                $result['attempt'] = $attempt;
                return $result;
            }

            $lastError = (string) ($result['error'] ?? $lastError);
        }

        return [
            'success' => false,
            'error' => $lastError,
            'attempts' => $attempts,
        ];
    }

    private function sendOnce(string $phone, string $code, string $message): array
    {
        return match ($this->provider) {
            'twilio' => $this->sendTwilio($phone, $message),
            'aws_sns' => $this->sendAwsSns($phone, $message),
            'mock' => $this->sendMock($phone, $code, $message),
            default => $this->sendMock($phone, $code, $message),
        };
    }

    private function sendTwilio(string $phone, string $message): array
    {
        try {
            $accountSid = env('TWILIO_ACCOUNT_SID');
            $authToken = env('TWILIO_AUTH_TOKEN');
            $twilioNumber = env('TWILIO_PHONE_NUMBER');

            if (! $accountSid || ! $authToken || ! $twilioNumber) {
                return ['success' => false, 'error' => 'Twilio não configurado corretamente.'];
            }

            $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";
            $response = \Config\Services::curlrequest()->post($url, [
                'auth' => [$accountSid, $authToken],
                'form_params' => [
                    'To' => $phone,
                    'From' => $twilioNumber,
                    'Body' => $message,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string) $response->getBody(), true);

            if ($statusCode !== 201) {
                log_message('error', 'Twilio SMS failed: ' . ($body['message'] ?? 'Unknown error'));
                return [
                    'success' => false,
                    'error' => 'Falha ao enviar SMS via Twilio: ' . ($body['message'] ?? 'Unknown error'),
                ];
            }

            log_message('info', "SMS sent via Twilio to {$phone}, SID: " . ($body['sid'] ?? ''));

            return [
                'success' => true,
                'provider' => 'twilio',
                'message_sid' => $body['sid'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendAwsSns(string $phone, string $message): array
    {
        try {
            $awsKey = env('AWS_ACCESS_KEY_ID');
            $awsSecret = env('AWS_SECRET_ACCESS_KEY');
            $awsRegion = env('AWS_REGION', 'us-east-1');

            if (! $awsKey || ! $awsSecret) {
                return ['success' => false, 'error' => 'AWS SNS não configurado corretamente.'];
            }

            $endpoint = "https://sns.{$awsRegion}.amazonaws.com/";
            $params = [
                'Action' => 'Publish',
                'Message' => $message,
                'PhoneNumber' => $phone,
                'Version' => '2010-03-31',
            ];

            $signedHeaders = $this->awsRequestSigner->signSNSPost($endpoint, $params, (string) $awsKey, (string) $awsSecret, (string) $awsRegion);

            $response = \Config\Services::curlrequest()->post($endpoint, [
                'headers' => $signedHeaders,
                'form_params' => $params,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode !== 200) {
                log_message('error', 'AWS SNS SMS failed: ' . $body);
                return ['success' => false, 'error' => 'Falha ao enviar SMS via AWS SNS'];
            }

            $xml = simplexml_load_string($body);
            $messageId = ($xml !== false && isset($xml->PublishResult->MessageId)) ? (string) $xml->PublishResult->MessageId : null;

            log_message('info', "SMS sent via AWS SNS to {$phone}, MessageId: {$messageId}");

            return [
                'success' => true,
                'provider' => 'aws_sns',
                'message_id' => $messageId,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function sendMock(string $phone, string $code, string $message): array
    {
        $logFile = WRITEPATH . 'logs/sms_mock.log';
        $line = sprintf("[%s] SMS para %s: %s\n", date('Y-m-d H:i:s'), $phone, $message);
        file_put_contents($logFile, $line, FILE_APPEND);

        log_message('info', "SMS Mock: Code {$code} sent to {$phone}");

        return [
            'success' => true,
            'provider' => 'mock',
            'code' => $code,
        ];
    }
}
