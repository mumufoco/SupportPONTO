<?php

namespace App\Services\Timesheet;

use CodeIgniter\Database\BaseConnection;

/**
 * Creates and verifies a stronger integrity envelope for time punches.
 *
 * The public hash remains available for compatibility, while chain_hash is an
 * HMAC protected by TIME_PUNCH_INTEGRITY_KEY / APP_KEY and linked to the
 * previous punch hash for the same employee.
 */
class TimePunchIntegrityChainService
{
    public const HASH_ALGORITHM = 'HMAC-SHA256-CHAIN';
    public const HASH_VERSION   = 2;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function enrichPayload(array $payload): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        if ($employeeId <= 0 || empty($payload['punch_time']) || empty($payload['nsr'])) {
            return $payload;
        }

        $previousHash = $this->latestChainHashForEmployee($employeeId);
        $payload['previous_hash'] = $previousHash;
        $payload['hash_algorithm'] = self::HASH_ALGORITHM;
        $payload['hash_version'] = self::HASH_VERSION;
        $payload['integrity_key_id'] = $this->keyId();
        $payload['integrity_signed_at'] = date('Y-m-d H:i:s');
        $payload['hash'] = $this->publicHash($payload);
        $payload['chain_hash'] = $this->chainHash($payload, $previousHash);

        return $payload;
    }

    public function verify(object|array $punch): bool
    {
        $data = is_array($punch) ? $punch : (array) $punch;
        if (empty($data['hash']) || empty($data['chain_hash'])) {
            return false;
        }

        $expectedPublic = $this->publicHash($data);
        $expectedChain = $this->chainHash($data, $data['previous_hash'] ?? null);

        return hash_equals((string) $data['hash'], $expectedPublic)
            && hash_equals((string) $data['chain_hash'], $expectedChain);
    }

    public function publicHash(array $payload): string
    {
        return hash('sha256', implode('|', [
            (int) ($payload['employee_id'] ?? 0),
            (string) ($payload['punch_type'] ?? ''),
            (string) ($payload['punch_time'] ?? ''),
            (string) ($payload['nsr'] ?? ''),
            (string) ($payload['method'] ?? ''),
        ]));
    }

    public function chainHash(array $payload, ?string $previousHash): string
    {
        $message = implode('|', [
            'supportponto-time-punch-v2',
            (string) ($previousHash ?: 'GENESIS'),
            (int) ($payload['employee_id'] ?? 0),
            (string) ($payload['punch_type'] ?? ''),
            (string) ($payload['punch_time'] ?? ''),
            (string) ($payload['nsr'] ?? ''),
            (string) ($payload['method'] ?? ''),
            (string) ($payload['ip_address'] ?? ''),
            (string) ($payload['user_agent'] ?? ''),
            (string) ($payload['created_at'] ?? ''),
        ]);

        return hash_hmac('sha256', $message, $this->secret());
    }

    public function latestChainHashForEmployee(int $employeeId): ?string
    {
        if (! $this->db->tableExists('time_punches') || ! $this->db->fieldExists('chain_hash', 'time_punches')) {
            return null;
        }

        $row = $this->db->table('time_punches')
            ->select('chain_hash')
            ->where('employee_id', $employeeId)
            ->where('chain_hash IS NOT NULL')
            ->orderBy('nsr', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row['chain_hash'] ?? null;
    }

    private function secret(): string
    {
        $secret = (string) (env('TIME_PUNCH_INTEGRITY_KEY') ?: env('APP_KEY') ?: '');
        if ($secret === '') {
            throw new \RuntimeException('TIME_PUNCH_INTEGRITY_KEY/APP_KEY ausente. Registro de ponto bloqueado por integridade.');
        }

        return $secret;
    }

    private function keyId(): string
    {
        return substr(hash('sha256', $this->secret()), 0, 16);
    }
}
