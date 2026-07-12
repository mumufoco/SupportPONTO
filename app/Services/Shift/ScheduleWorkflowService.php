<?php

namespace App\Services\Shift;

use App\Models\ScheduleModel;

class ScheduleWorkflowService
{
    public function __construct(private readonly ScheduleModel $scheduleModel = new ScheduleModel())
    {
    }

    public static function createDefault(): self
    {
        return new self();
    }

    public function create(array $payload, int $actorId): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $shiftId = (int) ($payload['shift_id'] ?? 0);
        $date = (string) ($payload['date'] ?? '');
        $isRecurring = ($payload['is_recurring'] ?? '0') === '1';

        if ($isRecurring) {
            $validationError = $this->validateRecurringDates($date, (string) ($payload['recurrence_end_date'] ?? ''));
            if ($validationError !== null) {
                return ['success' => false, 'error' => $validationError];
            }
        }

        if ($this->scheduleModel->isEmployeeScheduled($employeeId, $date)) {
            return ['success' => false, 'error' => 'Este funcionário já possui um turno agendado para esta data.'];
        }

        $data = [
            'employee_id' => $employeeId,
            'shift_id' => $shiftId,
            'date' => $date,
            'week_day' => (int) date('w', strtotime($date)),
            'is_recurring' => $isRecurring ? 1 : 0,
            'recurrence_end_date' => $isRecurring ? ($payload['recurrence_end_date'] ?? null) : null,
            'status' => 'scheduled',
            'notes' => $payload['notes'] ?? null,
            'created_by' => $actorId,
        ];

        if ($isRecurring) {
            $success = $this->scheduleModel->createRecurringSchedule($data);

            return $success
                ? [
                    'success' => true,
                    'message' => 'Escala recorrente criada com sucesso!',
                    'audit' => [
                        'action' => 'SCHEDULE_RECURRING_CREATED',
                        'entity_id' => null,
                        'old_values' => null,
                        'new_values' => $data,
                        'description' => "Escala recorrente criada para funcionário ID {$employeeId} a partir de {$date}",
                    ],
                ]
                : ['success' => false, 'error' => 'Erro ao criar escala.'];
        }

        $scheduleId = $this->scheduleModel->insert($data);
        if (!$scheduleId) {
            return ['success' => false, 'error' => 'Erro ao criar escala.'];
        }

        return [
            'success' => true,
            'message' => 'Escala criada com sucesso!',
            'audit' => [
                'action' => 'SCHEDULE_CREATED',
                'entity_id' => (int) $scheduleId,
                'old_values' => null,
                'new_values' => $data,
                'description' => "Escala criada para funcionário ID {$employeeId} em {$date}",
            ],
        ];
    }

    public function update(int $id, array $payload): array
    {
        $schedule = $this->scheduleModel->find($id);
        if (!$schedule || !is_object($schedule)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Escala não encontrada.'];
        }

        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $date = (string) ($payload['date'] ?? '');

        if ($this->scheduleModel->isEmployeeScheduled($employeeId, $date, $id)) {
            return ['success' => false, 'error' => 'Este funcionário já possui outro turno agendado para esta data.'];
        }

        $oldValues = [
            'employee_id' => $schedule->employee_id,
            'shift_id' => $schedule->shift_id,
            'date' => $schedule->date,
            'status' => $schedule->status,
        ];

        $data = [
            'employee_id' => $employeeId,
            'shift_id' => (int) ($payload['shift_id'] ?? 0),
            'date' => $date,
            'week_day' => (int) date('w', strtotime($date)),
            'status' => $payload['status'] ?? 'scheduled',
            'notes' => $payload['notes'] ?? null,
        ];

        if (!$this->scheduleModel->update($id, $data)) {
            return ['success' => false, 'error' => 'Erro ao atualizar escala.'];
        }

        return [
            'success' => true,
            'message' => 'Escala atualizada com sucesso!',
            'audit' => [
                'action' => 'SCHEDULE_UPDATED',
                'entity_id' => $id,
                'old_values' => $oldValues,
                'new_values' => $data,
                'description' => "Escala atualizada para funcionário ID {$employeeId}",
            ],
        ];
    }

    public function delete(int $id): array
    {
        $schedule = $this->scheduleModel->find($id);
        if (!$schedule || !is_object($schedule)) {
            return ['success' => false, 'not_found' => true, 'error' => 'Escala não encontrada.'];
        }

        if (!$this->scheduleModel->delete($id)) {
            return ['success' => false, 'error' => 'Erro ao excluir escala.'];
        }

        return [
            'success' => true,
            'warning' => $schedule->date < date('Y-m-d') ? 'Não é recomendado excluir escalas passadas.' : null,
            'message' => 'Escala excluída com sucesso!',
            'audit' => [
                'action' => 'SCHEDULE_DELETED',
                'entity_id' => $id,
                'old_values' => [
                    'employee_id' => $schedule->employee_id,
                    'date' => $schedule->date,
                    'shift_id' => $schedule->shift_id,
                ],
                'new_values' => null,
                'description' => "Escala excluída para funcionário ID {$schedule->employee_id} em {$schedule->date}",
            ],
        ];
    }

    public function bulkAssign(array $payload): array
    {
        $employeeIds = $payload['employee_ids'] ?? [];
        if (is_string($employeeIds)) {
            $employeeIds = explode(',', $employeeIds);
        }

        $employeeIds = array_values(array_filter(array_map('intval', is_array($employeeIds) ? $employeeIds : [])));
        $shiftId = (int) ($payload['shift_id'] ?? 0);
        $startDate = (string) ($payload['start_date'] ?? '');
        $endDate = (string) ($payload['end_date'] ?? '');
        $weekDays = $this->normalizeWeekDays($payload['week_days'] ?? [1, 2, 3, 4, 5]);

        if (strtotime($endDate) < strtotime($startDate)) {
            return ['success' => false, 'error' => 'A data final deve ser igual ou posterior à data inicial.'];
        }

        $created = 0;
        $currentDate = $startDate;

        while (strtotime($currentDate) <= strtotime($endDate)) {
            $weekday = (int) date('w', strtotime($currentDate));
            if (in_array($weekday, $weekDays, true)) {
                foreach ($employeeIds as $employeeId) {
                    if ($this->scheduleModel->isEmployeeScheduled($employeeId, $currentDate)) {
                        continue;
                    }

                    $inserted = $this->scheduleModel->insert([
                        'employee_id' => $employeeId,
                        'shift_id' => $shiftId,
                        'date' => $currentDate,
                        'week_day' => $weekday,
                        'is_recurring' => 0,
                        'status' => 'scheduled',
                        'notes' => $payload['notes'] ?? null,
                    ]);

                    if ($inserted) {
                        $created++;
                    }
                }
            }

            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }

        return [
            'success' => true,
            'assigned_count' => $created,
            'message' => "{$created} escalas criadas com sucesso!",
            'audit' => [
                'action' => 'SCHEDULE_BULK_ASSIGNED',
                'entity_id' => null,
                'old_values' => null,
                'new_values' => [
                    'employee_count' => count($employeeIds),
                    'shift_id' => $shiftId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'assigned_count' => $created,
                ],
                'description' => "Atribuição em massa: {$created} escalas criadas para " . count($employeeIds) . ' funcionários',
            ],
        ];
    }

    private function validateRecurringDates(string $startDate, string $recurrenceEndDate): ?string
    {
        if ($recurrenceEndDate === '') {
            return 'A data final da recorrência é obrigatória para escalas recorrentes.';
        }

        if (strtotime($recurrenceEndDate) <= strtotime($startDate)) {
            return 'A data final da recorrência deve ser posterior à data inicial.';
        }

        if (strtotime($recurrenceEndDate) < strtotime($startDate . ' +7 days')) {
            return 'A data final da recorrência deve ser no mínimo 7 dias após a data inicial.';
        }

        return null;
    }

    private function normalizeWeekDays(array|string $weekDays): array
    {
        $values = is_array($weekDays) ? $weekDays : [$weekDays];

        $normalized = array_values(array_unique(array_map(static fn ($day) => (int) $day, $values)));
        return array_values(array_filter($normalized, static fn ($day) => $day >= 0 && $day <= 6));
    }
}
