<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Histórico de possíveis fraudes detectadas na segunda camada de verificação
 * facial (código/CPF/QR/biometria digital) — ver TimesheetPunchRegistrationService.
 */
class FacialFraudAlertModel extends Model
{
    protected $table            = 'facial_fraud_alerts';
    protected $primaryKey       = 'id';
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'employee_id',
        'time_punch_id',
        'method',
        'reason',
        'similarity_score',
        'threshold_used',
        'ip_address',
        'user_agent',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_at',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_REVIEWED  = 'reviewed';
    public const STATUS_DISMISSED = 'dismissed';

    public const REASON_MISMATCH      = 'mismatch';
    public const REASON_NO_PHOTO      = 'no_photo';
    public const REASON_SERVICE_ERROR = 'service_error';

    public function record(array $data): bool
    {
        $data['status'] = self::STATUS_PENDING;
        $data['created_at'] = date('Y-m-d H:i:s');

        return (bool) $this->insert($data);
    }

    /** @return list<object> */
    public function listWithEmployee(?string $status = null, ?int $employeeId = null): array
    {
        $builder = $this->select('facial_fraud_alerts.*, employees.name AS employee_name, employees.unique_code AS employee_code')
            ->join('employees', 'employees.id = facial_fraud_alerts.employee_id')
            ->orderBy('facial_fraud_alerts.created_at', 'DESC');

        if ($status !== null && $status !== '') {
            $builder->where('facial_fraud_alerts.status', $status);
        }

        if ($employeeId !== null) {
            $builder->where('facial_fraud_alerts.employee_id', $employeeId);
        }

        return $builder->findAll();
    }

    public function countByStatus(string $status): int
    {
        return $this->where('status', $status)->countAllResults();
    }

    public function markReviewed(int $id, int $reviewerId, string $status, string $notes = ''): bool
    {
        if (! in_array($status, [self::STATUS_REVIEWED, self::STATUS_DISMISSED], true)) {
            return false;
        }

        return $this->update($id, [
            'status'       => $status,
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $notes !== '' ? $notes : null,
        ]);
    }

    /** Quantidade de alertas pendentes/analisados por funcionário — para identificar padrões. */
    public function countByEmployee(int $employeeId): int
    {
        return $this->where('employee_id', $employeeId)->countAllResults();
    }
}
