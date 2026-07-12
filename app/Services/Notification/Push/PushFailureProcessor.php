<?php

namespace App\Services\Notification\Push;

class PushFailureProcessor
{
    private const INVALID_ERRORS = ['InvalidRegistration', 'NotRegistered', 'MismatchSenderId'];

    public function __construct(private readonly PushTokenRepository $tokens)
    {
    }

    public function processMulticastResults(array $deviceTokens, array $results): void
    {
        foreach ($results as $index => $result) {
            $token = $deviceTokens[$index] ?? null;
            if (! $token || ! isset($result['error'])) {
                continue;
            }

            $error = (string) $result['error'];
            if (in_array($error, self::INVALID_ERRORS, true)) {
                $this->tokens->markInvalid($token);
                log_message('info', "Marked FCM token as invalid: {$error}");
            }
        }
    }
}
