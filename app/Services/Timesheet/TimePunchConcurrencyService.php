<?php

namespace App\Services\Timesheet;

use CodeIgniter\Database\BaseConnection;

/**
 * Locks critical punch-registration operations per employee.
 *
 * PostgreSQL advisory transaction locks prevent two simultaneous requests from
 * reading the same last punch/cooldown state and inserting duplicate punches.
 */
class TimePunchConcurrencyService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function lockEmployeeForPunch(int $employeeId): void
    {
        if ($employeeId <= 0) {
            throw new \InvalidArgumentException('employeeId inválido para lock de ponto.');
        }

        $driver = strtolower((string) ($this->db->DBDriver ?? ''));
        if (str_contains($driver, 'postgre') || str_contains($driver, 'postgres')) {
            $this->db->query('SELECT pg_advisory_xact_lock(?)', [$this->lockKey($employeeId)]);
            return;
        }

        // Non-PostgreSQL fallback only protects the current process/database if
        // the driver supports row locks poorly. Production remains PostgreSQL.
        $this->db->query('SELECT 1');
    }

    private function lockKey(int $employeeId): int
    {
        // Stable 31-bit key to keep compatibility with PostgreSQL integer locks.
        return (int) (crc32('supportponto:time_punch:' . $employeeId) & 0x7fffffff);
    }
}
