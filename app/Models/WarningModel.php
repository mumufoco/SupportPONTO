<?php

namespace App\Models;

use CodeIgniter\Model;

class WarningModel extends Model
{
    protected $table            = 'warnings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'warning_type',
        'occurrence_date',
        'reason',
        'evidence_files',
        'issued_by',
        'pdf_path',
        'employee_signature',
        'employee_signed_at',
        'status',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'employee_id'     => 'required|integer',
        'warning_type'    => 'required|in_list[verbal,escrita,suspensao]',
        'occurrence_date' => 'required|valid_date',
        'reason'          => 'required|min_length[50]',
        'issued_by'       => 'required|integer',
    ];

    protected $validationMessages = [
        'reason' => [
            'min_length' => 'O motivo deve ter no mínimo 50 caracteres.',
        ],
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['encodeEvidence'];
    protected $beforeUpdate   = ['encodeEvidence'];
    protected $afterFind      = ['decodeEvidence'];

    /**
     * Encode evidence files array to JSON
     */
    protected function encodeEvidence(array $data): array
    {
        if (isset($data['data']['evidence_files']) && is_array($data['data']['evidence_files'])) {
            $data['data']['evidence_files'] = json_encode($data['data']['evidence_files']);
        }

        return $data;
    }

    /**
     * Decode evidence files JSON to array
     */
    protected function decodeEvidence(array $data): array
    {
        if (isset($data['data'])) {
            if (is_array($data['data'])) {
                foreach ($data['data'] as &$row) {
                    if (isset($row->evidence_files) && is_string($row->evidence_files)) {
                        $row->evidence_files = json_decode($row->evidence_files, true);
                    }
                }
            } elseif (isset($data['data']->evidence_files) && is_string($data['data']->evidence_files)) {
                $data['data']->evidence_files = json_decode($data['data']->evidence_files, true);
            }
        }

        return $data;
    }

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

    /**
     * Get table columns for inspection
     */
    public function getTableColumns(): array
    {
        if (!$this->tableExists()) {
            log_message('warning', "Table {$this->table} does not exist. Returning empty columns list.");
            return [];
        }

        try {
            return $this->db->getFieldNames($this->table);
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'Database error getting table columns: ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            log_message('error', 'Unexpected error getting table columns: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Cached table columns for performance
     */
    protected static $cachedColumns = null;

    /**
     * Get safe order by column (cached) - public for controller access
     */
    public function getSafeOrderByColumn(): string
    {
        // Use cached columns if available
        if (self::$cachedColumns === null) {
            self::$cachedColumns = $this->getTableColumns();
        }

        $columns = self::$cachedColumns;
        
        // Prefer occurrence_date, fallback to date, then created_at
        if (in_array('occurrence_date', $columns)) {
            return 'occurrence_date';
        } elseif (in_array('date', $columns)) {
            return 'date';
        }
        
        return 'created_at'; // default fallback
    }

    /**
     * Get warnings by employee with defensive checks
     */
    public function getByEmployee(int $employeeId): array
    {
        if (!$this->tableExists()) {
            log_message('warning', "Table {$this->table} does not exist. Returning empty result.");
            return [];
        }

        try {
            $orderByColumn = $this->getSafeOrderByColumn();

            return $this->where('employee_id', $employeeId)
                ->orderBy($orderByColumn, 'DESC')
                ->findAll();
        } catch (\CodeIgniter\Database\Exceptions\DatabaseException $e) {
            log_message('error', 'Database error in getByEmployee: ' . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            log_message('error', 'Unexpected error in getByEmployee: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending signature warnings
     */
    public function getPendingSignature(?int $employeeId = null): array
    {
        $builder = $this->where('status', 'pendente-assinatura');

        if ($employeeId) {
            $builder->where('employee_id', $employeeId);
        }

        return $builder->orderBy('occurrence_date', 'DESC')->findAll();
    }

    /**
     * Sign warning (employee)
     */
    public function sign(int $warningId, string $signature): bool
    {
        return $this->update($warningId, [
            'employee_signature' => $signature,
            'employee_signed_at' => date('Y-m-d H:i:s'),
            'status'             => 'assinado',
        ]);
    }

    /**
     * Marca a advertência como recusada. As testemunhas (2, ver WarningWitnessModel)
     * são gravadas separadamente por WarningWorkflowService::refuseWithWitness().
     */
    public function markRefused(int $warningId): bool
    {
        return $this->update($warningId, [
            'status' => 'recusado',
        ]);
    }

    /**
     * Get warning count by type for employee
     */
    public function getCountByType(int $employeeId, string $type): int
    {
        return $this->where('employee_id', $employeeId)
            ->where('warning_type', $type)
            ->countAllResults();
    }

    /**
     * Get total warnings for employee
     */
    public function getTotalWarnings(int $employeeId): int
    {
        return $this->where('employee_id', $employeeId)->countAllResults();
    }

    /**
     * Check if employee is at warning limit (3 warnings)
     */
    public function isAtLimit(int $employeeId): bool
    {
        return $this->getTotalWarnings($employeeId) >= 3;
    }

    /**
     * Get all warning type counts and overall total for one employee in a single GROUP BY query.
     *
     * Eliminates 4 separate countAllResults() calls in dashboardData()
     * (getCountByType×3 + getTotalWarnings).
     *
     * @return array{total: int, verbal: int, escrita: int, suspensao: int}
     */
    public function getStatsByEmployee(int $employeeId): array
    {
        $rows = $this->select('warning_type, COUNT(*) AS cnt', false)
            ->where('employee_id', $employeeId)
            ->groupBy('warning_type')
            ->findAll();

        $stats = ['total' => 0, 'verbal' => 0, 'escrita' => 0, 'suspensao' => 0];
        foreach ($rows as $row) {
            $type = (string) ($row->warning_type ?? '');
            $cnt  = (int) ($row->cnt ?? 0);
            if (array_key_exists($type, $stats)) {
                $stats[$type] = $cnt;
            }
            $stats['total'] += $cnt;
        }

        return $stats;
    }

    /**
     * Get warnings by date range
     */
    public function getByDateRange(string $startDate, string $endDate): array
    {
        return $this->where('occurrence_date >=', $startDate)
            ->where('occurrence_date <=', $endDate)
            ->orderBy('occurrence_date', 'DESC')
            ->findAll();
    }

    /**
     * Get warnings timeline for employee
     */
    public function getTimeline(int $employeeId): array
    {
        $warnings = $this->getByEmployee($employeeId);
        $timeline = [];

        foreach ($warnings as $warning) {
            $timeline[] = [
                'id'              => $warning->id,
                'type'            => $warning->warning_type,
                'date'            => $warning->occurrence_date,
                'status'          => $warning->status,
                'signed_at'       => $warning->employee_signed_at,
                'reason_preview'  => mb_strimwidth((string) ($warning->reason ?? ''), 0, 100, '…'),
            ];
        }

        return $timeline;
    }
}
