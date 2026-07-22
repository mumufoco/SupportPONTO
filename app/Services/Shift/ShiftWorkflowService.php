<?php

namespace App\Services\Shift;

use App\Models\ScheduleModel;
use App\Models\WorkShiftModel;

class ShiftWorkflowService
{
    public function __construct(
        private readonly WorkShiftModel $shiftModel = new WorkShiftModel(),
        private readonly ScheduleModel $scheduleModel = new ScheduleModel(),
    ) {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function create(array $payload, int $actorId): array
    {
        $startTime = (string) ($payload['start_time'] ?? '');
        $endTime = (string) ($payload['end_time'] ?? '');
        $lunchStart = $this->normalizeTime($payload['lunch_start_time'] ?? null);
        $lunchEnd = $this->normalizeTime($payload['lunch_end_time'] ?? null);

        $data = [
            'name' => $payload['name'] ?? null,
            'description' => $payload['description'] ?? null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'type' => $payload['type'] ?? null,
            'break_duration' => $this->resolveBreakDuration($payload, $lunchStart, $lunchEnd),
            'lunch_start_time' => $lunchStart,
            'lunch_end_time' => $lunchEnd,
            'color' => !empty($payload['color']) ? $payload['color'] : $this->defaultColor((string) ($payload['type'] ?? '')),
            'active' => true,
            'created_by' => $actorId,
        ];

        $shiftId = $this->shiftModel->insert($data);
        if (!$shiftId) {
            return ['success' => false, 'error' => 'Erro ao criar turno.'];
        }

        return [
            'success' => true,
            'shift_id' => (int) $shiftId,
            'warning' => $this->overlapWarning($startTime, $endTime, (int) $shiftId),
            'audit' => [
                'action' => 'SHIFT_CREATED',
                'entity_id' => (int) $shiftId,
                'old_values' => null,
                'new_values' => $data,
                'description' => "Turno criado: {$data['name']} ({$data['start_time']} - {$data['end_time']})",
            ],
        ];
    }

    public function update(int $id, array $payload): array
    {
        $shift = $this->shiftModel->find($id);
        if (!$shift || !is_object($shift)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Turno não encontrado.'];
        }

        $startTime = (string) ($payload['start_time'] ?? '');
        $endTime = (string) ($payload['end_time'] ?? '');
        $lunchStart = $this->normalizeTime($payload['lunch_start_time'] ?? null);
        $lunchEnd = $this->normalizeTime($payload['lunch_end_time'] ?? null);

        $oldValues = [
            'name' => $shift->name,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'type' => $shift->type,
            'break_duration' => $shift->break_duration,
            'lunch_start_time' => $shift->lunch_start_time ?? null,
            'lunch_end_time' => $shift->lunch_end_time ?? null,
            'active' => $shift->active,
        ];

        $data = [
            'name' => $payload['name'] ?? null,
            'description' => $payload['description'] ?? null,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'type' => $payload['type'] ?? null,
            'break_duration' => $this->resolveBreakDuration($payload, $lunchStart, $lunchEnd),
            'lunch_start_time' => $lunchStart,
            'lunch_end_time' => $lunchEnd,
            'color' => !empty($payload['color']) ? $payload['color'] : $shift->color,
            'active' => !empty($payload['active']),
        ];

        if (!$this->shiftModel->update($id, $data)) {
            return ['success' => false, 'error' => 'Erro ao atualizar turno.'];
        }

        return [
            'success' => true,
            'warning' => $this->overlapWarning($startTime, $endTime, $id),
            'audit' => [
                'action' => 'SHIFT_UPDATED',
                'entity_id' => $id,
                'old_values' => $oldValues,
                'new_values' => $data,
                'description' => "Turno atualizado: {$data['name']}",
            ],
        ];
    }

    public function delete(int $id): array
    {
        $shift = $this->shiftModel->find($id);
        if (!$shift || !is_object($shift)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Turno não encontrado.'];
        }

        $activeSchedules = $this->scheduleModel
            ->where('shift_id', $id)
            ->where('date >=', date('Y-m-d'))
            ->countAllResults();

        if ($activeSchedules > 0) {
            return [
                'success' => false,
                'error' => "Não é possível excluir este turno pois existem {$activeSchedules} escalas futuras associadas a ele.",
            ];
        }

        if (!$this->shiftModel->delete($id)) {
            return ['success' => false, 'error' => 'Erro ao excluir turno.'];
        }

        return [
            'success' => true,
            'audit' => [
                'action' => 'SHIFT_DELETED',
                'entity_id' => $id,
                'old_values' => ['name' => $shift->name, 'active' => $shift->active],
                'new_values' => null,
                'description' => "Turno excluído: {$shift->name}",
            ],
        ];
    }

    public function clone(int $id): array
    {
        $shift = $this->shiftModel->find($id);
        if (!$shift || !is_object($shift)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Turno não encontrado.'];
        }

        $newName = "Cópia de {$shift->name}";
        $newShiftId = $this->shiftModel->cloneShift($id, $newName);
        if (!$newShiftId) {
            return ['success' => false, 'error' => 'Erro ao clonar turno.'];
        }

        return [
            'success' => true,
            'shift_id' => (int) $newShiftId,
            'audit' => [
                'action' => 'SHIFT_CLONED',
                'entity_id' => (int) $newShiftId,
                'old_values' => null,
                'new_values' => ['source_id' => $id, 'name' => $newName],
                'description' => "Turno clonado: {$shift->name} → {$newName}",
            ],
        ];
    }

    public function toggleActive(int $id): array
    {
        $shift = $this->shiftModel->find($id);
        if (!$shift || !is_object($shift)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Turno não encontrado.'];
        }

        $newStatus = !$shift->active;
        if (!$this->shiftModel->skipValidation(true)->update($id, ['active' => $newStatus])) {
            return ['success' => false, 'error' => 'Erro ao atualizar status do turno.'];
        }

        return [
            'success' => true,
            'data' => ['active' => $newStatus],
            'message' => $newStatus ? 'Turno ativado com sucesso!' : 'Turno desativado com sucesso!',
            'audit' => [
                'action' => 'SHIFT_STATUS_CHANGED',
                'entity_id' => $id,
                'old_values' => ['active' => $shift->active],
                'new_values' => ['active' => $newStatus],
                'description' => "Status do turno {$shift->name} alterado para " . ($newStatus ? 'ativo' : 'inativo'),
            ],
        ];
    }

    private function overlapWarning(string $startTime, string $endTime, ?int $excludeId = null): ?string
    {
        $overlappingShifts = $this->shiftModel->findOverlappingShifts($startTime, $endTime, $excludeId);

        return !empty($overlappingShifts)
            ? 'Atenção: Este turno se sobrepõe a outros turnos existentes.'
            : null;
    }

    private function normalizeTime(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    /**
     * O intervalo é a fonte de verdade quando os dois horários (saída/retorno)
     * estão preenchidos — break_duration é recalculado a partir deles em vez
     * de confiar apenas no valor calculado no navegador (JS pode estar
     * desabilitado ou o campo pode ter sido enviado divergente).
     */
    private function resolveBreakDuration(array $payload, ?string $lunchStart, ?string $lunchEnd): int
    {
        if ($lunchStart !== null && $lunchEnd !== null) {
            $start = strtotime($lunchStart);
            $end = strtotime($lunchEnd);

            if ($start !== false && $end !== false) {
                if ($end < $start) {
                    $end += 86400;
                }

                return min(480, (int) round(($end - $start) / 60));
            }
        }

        return !empty($payload['break_duration']) ? (int) $payload['break_duration'] : 0;
    }

    private function defaultColor(string $type): string
    {
        return [
            'morning' => '#FFA500',
            'afternoon' => '#4169E1',
            'night' => '#2F4F4F',
            'custom' => '#228B22',
        ][$type] ?? '#6C757D';
    }
}
