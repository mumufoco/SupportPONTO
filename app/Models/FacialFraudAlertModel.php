<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
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

    /**
     * @return list<object>
     */
    public function listWithEmployee(?string $status = null, ?int $employeeId = null, ?int $department = null, int $limit = 0, int $offset = 0): array
    {
        $builder = $this->scopedBuilder($status, $employeeId, $department)
            ->select('facial_fraud_alerts.*, employees.name AS employee_name, employees.unique_code AS employee_code')
            ->orderBy('facial_fraud_alerts.created_at', 'DESC');

        if ($limit > 0) {
            $builder->limit($limit, $offset);
        }

        return $builder->get()->getResult();
    }

    public function countScoped(?string $status = null, ?int $employeeId = null, ?int $department = null): int
    {
        $row = $this->scopedBuilder($status, $employeeId, $department)
            ->select('COUNT(DISTINCT facial_fraud_alerts.id) AS aggregate_count', false)
            ->get()
            ->getRow();

        return (int) ($row->aggregate_count ?? 0);
    }

    /**
     * Builder novo a cada chamada (via $this->db->table(), nao $this->where()/$this->join())
     * - o builder interno de um Model do CI4 acumula condicoes entre chamadas sucessivas na
     * mesma instancia, e este model e usado varias vezes seguidas na mesma requisicao
     * (contagem por status + listagem), o que faria a 2a chamada herdar filtros da 1a.
     * Mesmo cuidado ja aplicado em WarningQueryService/AuditQueryService nesta sessao.
     */
    private function scopedBuilder(?string $status = null, ?int $employeeId = null, ?int $department = null): BaseBuilder
    {
        // LEFT JOIN (nao INNER): um alerta nunca deve sumir da listagem so porque o
        // colaborador referenciado nao existe mais (fica "Colaborador removido" na tela
        // em vez de o registro de auditoria desaparecer silenciosamente).
        $builder = $this->db->table('facial_fraud_alerts')
            ->join('employees', 'employees.id = facial_fraud_alerts.employee_id', 'left');

        if ($status !== null && $status !== '') {
            $builder->where('facial_fraud_alerts.status', $status);
        }

        if ($employeeId !== null) {
            $builder->where('facial_fraud_alerts.employee_id', $employeeId);
        }

        // Gestor so pode ver/revisar alertas da propria equipe - mesma restricao ja
        // aplicada em outras telas (auditoria, relatorios) para o papel gestor.
        if ($department !== null) {
            $builder->where('employees.department_id', $department);
        }

        return $builder;
    }

    public function countByStatus(string $status, ?int $department = null): int
    {
        return $this->countScoped($status, null, $department);
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

    /** Quantidade de alertas pendentes/analisados por colaborador — para identificar padrões. */
    public function countByEmployee(int $employeeId): int
    {
        return $this->where('employee_id', $employeeId)->countAllResults();
    }
}
