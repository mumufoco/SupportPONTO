<?php

namespace App\Services\SMS;

class SMSCodeRepository
{
    public function storeCode(int $employeeId, string $code, int $expirySeconds): void
    {
        $cache = \Config\Services::cache();
        $cache->save($this->codeKey($employeeId), [
            'code' => $code,
            'expiry' => time() + $expirySeconds,
            'attempts' => 0,
        ], $expirySeconds);
    }

    public function getCode(int $employeeId): ?array
    {
        $cache = \Config\Services::cache();
        $data = $cache->get($this->codeKey($employeeId));
        return is_array($data) ? $data : null;
    }

    public function deleteCode(int $employeeId): void
    {
        \Config\Services::cache()->delete($this->codeKey($employeeId));
    }

    public function getRateLimitInfo(int $employeeId, int $maxAttempts = 3, int $windowSeconds = 3600): array
    {
        $data = \Config\Services::cache()->get($this->rateKey($employeeId));
        if (! is_array($data)) {
            return [
                'attempts' => 0,
                'can_send' => true,
                'wait_seconds' => 0,
            ];
        }

        $canSend = ((int) $data['attempts']) < $maxAttempts;
        $waitSeconds = $canSend ? 0 : ((int) $data['first_attempt'] + $windowSeconds - time());

        return [
            'attempts' => (int) $data['attempts'],
            'can_send' => $canSend,
            'wait_seconds' => max(0, $waitSeconds),
        ];
    }

    public function incrementRateLimit(int $employeeId, int $windowSeconds = 3600): void
    {
        $cache = \Config\Services::cache();
        $key = $this->rateKey($employeeId);
        $data = $cache->get($key);

        if (! is_array($data)) {
            $data = [
                'attempts' => 1,
                'first_attempt' => time(),
            ];
        } else {
            $data['attempts'] = (int) ($data['attempts'] ?? 0) + 1;
        }

        $cache->save($key, $data, $windowSeconds);
    }

    private function codeKey(int $employeeId): string
    {
        return 'sms_code_' . $employeeId;
    }

    private function rateKey(int $employeeId): string
    {
        return 'sms_rate_limit_' . $employeeId;
    }
}
