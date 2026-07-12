<?php

namespace App\Services\Timesheet;

use App\Services\Audit\CanonicalAuditLogger;
use App\Models\TimePunchModel;
use App\Enums\Role;

class TimesheetWorkflowService
{
    protected TimePunchModel $timePunchModel;
    protected CanonicalAuditLogger $auditLogger;

    public function __construct()
    {
        $this->timePunchModel = new TimePunchModel();
        $this->auditLogger = new CanonicalAuditLogger();
    }

    public function getPunchDetails(int $id, array $employee): array
    {
        $punch = $this->timePunchModel->find($id);
        if (! $punch) {
            return ['success' => false, 'status' => 404, 'message' => 'Marcação não encontrada.'];
        }

        $employeeId = $employee['id'] ?? null;
        $employeeRole = $employee['role'] ?? null;

        if ($employeeId === null || ($punch->employee_id != $employeeId && ! $this->canManagePunches($employeeRole))) {
            return ['success' => false, 'status' => 403, 'message' => 'Sem permissão.'];
        }

        return [
            'success' => true,
            'status' => 200,
            'message' => 'OK',
            'data' => [
                'date' => date('d/m/Y', strtotime((string) $punch->punch_time)),
                'punch_time' => date('H:i:s', strtotime($punch->punch_time)),
                'punch_type' => ucfirst(str_replace('_', ' ', $punch->punch_type)),
                'method' => ucfirst($punch->method ?? 'manual'),
                'location_lat' => $punch->location_lat,
                'location_lng' => $punch->location_lng,
                'photo_path' => $punch->photo_path,
                'notes' => $punch->notes,
            ],
        ];
    }

    public function approvePunch(int $id, array $employee): array
    {
        $punch = $this->timePunchModel->find($id);
        if (! $punch || $punch->status !== 'pending') {
            return ['success' => false, 'status' => 400, 'message' => 'Marcação inválida ou não pendente.'];
        }

        $employeeId = $employee['id'] ?? null;
        $employeeRole = $employee['role'] ?? null;

        if ($employeeId === null || ! $this->canManagePunches($employeeRole)) {
            return ['success' => false, 'status' => 403, 'message' => 'Sem permissão para aprovar.'];
        }

        $this->timePunchModel->update($id, ['status' => 'approved', 'approved_by' => $employeeId, 'approved_at' => date('Y-m-d H:i:s')]);
        $this->auditLogger->logEntityEvent($employeeId, 'PUNCH_APPROVED', 'time_punches', $id, ['status' => 'pending'], ['status' => 'approved'], 'Marcação aprovada', 'info');

        return ['success' => true, 'status' => 200, 'message' => 'Marcação aprovada com sucesso.'];
    }

    public function rejectPunch(int $id, string $reason, array $employee): array
    {
        if (trim($reason) === '') {
            return ['success' => false, 'status' => 400, 'message' => 'Motivo da rejeição é obrigatório.'];
        }

        $punch = $this->timePunchModel->find($id);
        if (! $punch || $punch->status !== 'pending') {
            return ['success' => false, 'status' => 400, 'message' => 'Marcação inválida ou não pendente.'];
        }

        $employeeId = $employee['id'] ?? null;
        $employeeRole = $employee['role'] ?? null;

        if ($employeeId === null || ! $this->canManagePunches($employeeRole)) {
            return ['success' => false, 'status' => 403, 'message' => 'Sem permissão para rejeitar.'];
        }

        $reason = trim($reason);
        $this->timePunchModel->update($id, ['status' => 'rejected', 'rejected_by' => $employeeId, 'rejected_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $reason, 'notes' => $reason]);
        $this->auditLogger->logEntityEvent($employeeId, 'PUNCH_REJECTED', 'time_punches', $id, ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason], 'Marcação rejeitada', 'warning');

        return ['success' => true, 'status' => 200, 'message' => 'Marcação rejeitada.'];
    }

    private function canManagePunches(?string $role): bool
    {
        try {
            return Role::normalize((string) ($role ?? Role::Funcionario->value))->canManageEmployees();
        } catch (\Throwable) {
            return false;
        }
    }
}
