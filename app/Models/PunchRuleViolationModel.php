<?php

namespace App\Models;

use App\Enums\PunchViolationType;
use CodeIgniter\Model;

class PunchRuleViolationModel extends Model
{
    protected $table      = 'punch_rule_violations';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    protected $allowedFields = [
        'employee_id', 'violation_type', 'reference_date', 'details',
        'status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'employee_id'    => 'required|integer',
        'violation_type' => 'required',
        'reference_date' => 'required|valid_date',
        'status'         => 'required|in_list[pendente,tratada]',
    ];

    /**
     * Cria a irregularidade se ainda não existir para o mesmo colaborador/tipo/data.
     * Se já existir e ainda estiver 'pendente', apenas atualiza os valores calculados.
     * Se já tiver sido 'tratada' pelo gestor, não mexe — não reabre algo já revisado.
     */
    public function upsertPending(int $employeeId, PunchViolationType $type, string $date, array $details): void
    {
        $existing = $this->where('employee_id', $employeeId)
            ->where('violation_type', $type->value)
            ->where('reference_date', $date)
            ->first();

        if (! $existing) {
            $this->insert([
                'employee_id'    => $employeeId,
                'violation_type' => $type->value,
                'reference_date' => $date,
                'details'        => json_encode($details, JSON_UNESCAPED_UNICODE),
                'status'         => 'pendente',
            ]);
            return;
        }

        if ($existing->status === 'pendente') {
            $this->update($existing->id, [
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /** Remove a irregularidade se ela ainda estiver 'pendente' (condição deixou de existir) */
    public function clearIfPending(int $employeeId, PunchViolationType $type, string $date): void
    {
        $this->where('employee_id', $employeeId)
            ->where('violation_type', $type->value)
            ->where('reference_date', $date)
            ->where('status', 'pendente')
            ->delete();
    }

    /** Irregularidades pendentes de revisão, opcionalmente por departamento */
    public function listPendingForManager(?string $department = null): array
    {
        $builder = $this->select('punch_rule_violations.*, employees.name AS employee_name, employees.department')
            ->join('employees', 'employees.id = punch_rule_violations.employee_id', 'left')
            ->where('punch_rule_violations.status', 'pendente')
            ->orderBy('punch_rule_violations.reference_date', 'DESC');

        if ($department !== null && $department !== '') {
            $builder->where('employees.department', $department);
        }

        return $builder->findAll();
    }

    public function markResolved(int $id, int $reviewerId, string $notes): bool
    {
        return $this->update($id, [
            'status'       => 'tratada',
            'reviewed_by'  => $reviewerId,
            'reviewed_at'  => date('Y-m-d H:i:s'),
            'review_notes' => $notes,
        ]);
    }

    /** Decodifica details de JSON para array */
    public function decodeDetails(object $record): array
    {
        $raw = $record->details ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($raw) ? $raw : [];
    }
}
