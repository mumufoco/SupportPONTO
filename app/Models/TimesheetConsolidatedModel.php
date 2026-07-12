<?php

namespace App\Models;

use CodeIgniter\Model;

class TimesheetConsolidatedModel extends Model
{
    protected $table            = 'timesheet_consolidated';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'date',
        'total_worked',
        'expected',
        'extra',
        'owed',
        'interval_violation',
        'justified',
        'incomplete',
        'justification_id',
        'punches_count',
        'first_punch',
        'last_punch',
        'total_interval',
        'notes',
        'needs_recalculation',
        'approved_by',
        'approved_at',
        'processed_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules      = [
        'employee_id'  => 'required|integer',
        'date'         => 'required|valid_date',
        'total_worked' => 'required|decimal',
        'expected'     => 'required|decimal',
    ];

    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Check if the table exists in the database
     */
    public function tableExists(): bool
    {
        try {
            return $this->db->tableExists($this->table);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'Database error checking table existence: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            log_message('error', 'Unexpected error checking table existence: ' . $e->getMessage());
            return false;
        }
    }

    protected function employeeBuilder(int $employeeId)
    {
        return $this->builder()
            ->where('employee_id', $employeeId);
    }

    protected function employeeRangeBuilder(int $employeeId, string $startDate, string $endDate)
    {
        return $this->employeeBuilder($employeeId)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate);
    }

    protected function runSafe(callable $callback, mixed $default, string $context)
    {
        if (!$this->tableExists()) {
            log_message('warning', "Table {$this->table} does not exist. Context: {$context}.");
            return $default;
        }

        try {
            return $callback();
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', sprintf('Database error in %s: %s', $context, $e->getMessage()));
            return $default;
        } catch (\Throwable $e) {
            log_message('error', sprintf('Unexpected error in %s: %s', $context, $e->getMessage()));
            return $default;
        }
    }


    /**
     * Safe version of findAll that handles missing table gracefully
     * This does NOT override parent::findAll() to avoid signature incompatibility
     */
    public function safeFindAll(?int $limit = null, int $offset = 0): array
    {
        return $this->runSafe(
            static fn() => parent::findAll($limit, $offset),
            [],
            'safeFindAll'
        );
    }

    /**
     * Get consolidated data for employee in date range
     */
    public function getByEmployeeAndRange(int $employeeId, string $startDate, string $endDate): array
    {
        return $this->runSafe(
            fn() => $this->employeeRangeBuilder($employeeId, $startDate, $endDate)
                ->orderBy('date', 'ASC')
                ->get()
                ->getResult(),
            [],
            'getByEmployeeAndRange'
        );
    }

    /**
     * Get current balance for employee
     */
    public function getCurrentBalance(int $employeeId): array
    {
        $defaultReturn = [
            'extra' => 0.0,
            'owed' => 0.0,
            'balance' => 0.0,
        ];

        return $this->runSafe(
            function () use ($employeeId, $defaultReturn) {
                $data = $this->employeeBuilder($employeeId)
                    ->select('COALESCE(SUM(extra), 0) AS total_extra, COALESCE(SUM(owed), 0) AS total_owed')
                    ->get()
                    ->getRow();

                if (!$data) {
                    return $defaultReturn;
                }

                $totalExtra = (float) ($data->total_extra ?? 0);
                $totalOwed = (float) ($data->total_owed ?? 0);

                return [
                    'extra' => $totalExtra,
                    'owed' => $totalOwed,
                    'balance' => $totalExtra - $totalOwed,
                ];
            },
            $defaultReturn,
            'getCurrentBalance'
        );
    }

    /**
     * Get incomplete days for employee
     */
    public function getIncompleteDays(int $employeeId, ?string $startDate = null): array
    {
        return $this->runSafe(
            function () use ($employeeId, $startDate) {
                $builder = $this->employeeBuilder($employeeId)
                    ->where('incomplete', true);

                if ($startDate) {
                    $builder->where('date >=', $startDate);
                }

                return $builder->orderBy('date', 'DESC')->get()->getResult();
            },
            [],
            'getIncompleteDays'
        );
    }

    /**
     * Get balance evolution for chart (last N days)
     */
    public function getBalanceEvolution(int $employeeId, int $days = 30): array
    {
        return $this->runSafe(
            function () use ($employeeId, $days) {
                $startDate = date('Y-m-d', strtotime("-{$days} days"));

                $records = $this->employeeBuilder($employeeId)
                    ->select('date, extra, owed')
                    ->where('date >=', $startDate)
                    ->orderBy('date', 'ASC')
                    ->get()
                    ->getResult();

                $evolution = [];
                $cumulativeExtra = 0.0;
                $cumulativeOwed = 0.0;

                foreach ($records as $record) {
                    $cumulativeExtra += (float) ($record->extra ?? 0);
                    $cumulativeOwed += (float) ($record->owed ?? 0);

                    $evolution[] = [
                        'date' => $record->date,
                        'balance' => $cumulativeExtra - $cumulativeOwed,
                        'extra' => $cumulativeExtra,
                        'owed' => $cumulativeOwed,
                    ];
                }

                return $evolution;
            },
            [],
            'getBalanceEvolution'
        );
    }

    /**
     * Check if date already processed
     */
    public function isProcessed(int $employeeId, string $date): bool
    {
        return (bool) $this->runSafe(
            fn() => $this->employeeBuilder($employeeId)
                ->where('date', $date)
                ->countAllResults() > 0,
            false,
            'isProcessed'
        );
    }

    /**
     * Get statistics for employee
     */
    public function getStatistics(int $employeeId, string $startDate, string $endDate): array
    {
        $defaultReturn = [
            'total_days' => 0,
            'incomplete_days' => 0,
            'justified_days' => 0,
            'total_worked' => 0.0,
            'total_expected' => 0.0,
            'total_extra' => 0.0,
            'total_owed' => 0.0,
            'total_violations' => 0.0,
            'avg_worked' => 0.0,
        ];

        return $this->runSafe(
            function () use ($employeeId, $startDate, $endDate, $defaultReturn) {
                $data = $this->employeeRangeBuilder($employeeId, $startDate, $endDate)
                    ->select("
                        COUNT(*) AS total_days,
                        SUM(CASE WHEN incomplete IS TRUE THEN 1 ELSE 0 END) AS incomplete_days,
                        SUM(CASE WHEN justified IS TRUE THEN 1 ELSE 0 END) AS justified_days,
                        COALESCE(SUM(total_worked), 0) AS total_worked,
                        COALESCE(SUM(expected), 0) AS total_expected,
                        COALESCE(SUM(extra), 0) AS total_extra,
                        COALESCE(SUM(owed), 0) AS total_owed,
                        COALESCE(SUM(interval_violation), 0) AS total_violations,
                        COALESCE(AVG(total_worked), 0) AS avg_worked
                    ")
                    ->get()
                    ->getRow();

                if (!$data) {
                    return $defaultReturn;
                }

                return [
                    'total_days' => (int) ($data->total_days ?? 0),
                    'incomplete_days' => (int) ($data->incomplete_days ?? 0),
                    'justified_days' => (int) ($data->justified_days ?? 0),
                    'total_worked' => (float) ($data->total_worked ?? 0),
                    'total_expected' => (float) ($data->total_expected ?? 0),
                    'total_extra' => (float) ($data->total_extra ?? 0),
                    'total_owed' => (float) ($data->total_owed ?? 0),
                    'total_violations' => (float) ($data->total_violations ?? 0),
                    'avg_worked' => (float) ($data->avg_worked ?? 0),
                ];
            },
            $defaultReturn,
            'getStatistics'
        );
    }

    /**
     * Sum two time values in HH:MM format
     */
    public function sumTime(string $time1, string $time2): string
    {
        try {
            $parts1 = explode(':', $time1);
            $parts2 = explode(':', $time2);
            
            $hours1 = (int) ($parts1[0] ?? 0);
            $minutes1 = (int) ($parts1[1] ?? 0);
            $hours2 = (int) ($parts2[0] ?? 0);
            $minutes2 = (int) ($parts2[1] ?? 0);
            
            $totalMinutes = ($hours1 * 60 + $minutes1) + ($hours2 * 60 + $minutes2);
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            
            return sprintf('%02d:%02d', $hours, $minutes);
        } catch (\Exception $e) {
            log_message('error', 'Error in sumTime: ' . $e->getMessage());
            return '00:00';
        }
    }

    /**
     * Negate time value (useful for calculations)
     */
    public function negateTime(string $time): string
    {
        try {
            $parts = explode(':', $time);
            $hours = (int) ($parts[0] ?? 0);
            $minutes = (int) ($parts[1] ?? 0);
            
            $totalMinutes = -($hours * 60 + $minutes);
            $isNegative = $totalMinutes < 0;
            $totalMinutes = abs($totalMinutes);
            
            $hours = floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;
            
            return ($isNegative ? '-' : '') . sprintf('%02d:%02d', $hours, $minutes);
        } catch (\Exception $e) {
            log_message('error', 'Error in negateTime: ' . $e->getMessage());
            return '00:00';
        }
    }

    /**
     * Convert decimal hours to HH:MM format
     */
    public function decimalToTime(float $decimal): string
    {
        try {
            $isNegative = $decimal < 0;
            $decimal = abs($decimal);
            
            $hours = floor($decimal);
            $minutes = round(($decimal - $hours) * 60);
            
            return ($isNegative ? '-' : '') . sprintf('%02d:%02d', $hours, $minutes);
        } catch (\Exception $e) {
            log_message('error', 'Error in decimalToTime: ' . $e->getMessage());
            return '00:00';
        }
    }

    /**
     * Convert HH:MM format to decimal hours
     */
    public function timeToDecimal(string $time): float
    {
        try {
            $isNegative = (strlen($time) > 0 && $time[0] === '-');
            $time = ltrim($time, '-');
            
            $parts = explode(':', $time);
            $hours = (int) ($parts[0] ?? 0);
            $minutes = (int) ($parts[1] ?? 0);
            
            $decimal = $hours + ($minutes / 60);
            return $isNegative ? -$decimal : $decimal;
        } catch (\Exception $e) {
            log_message('error', 'Error in timeToDecimal: ' . $e->getMessage());
            return 0.0;
        }
    }
}
