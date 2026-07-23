<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Schedule Model
 *
 * Manages employee shift assignments
 */
class ScheduleModel extends Model
{
    protected $table = 'schedules';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'employee_id',
        'shift_id',
        'date',
        'week_day',
        'is_recurring',
        'recurrence_end_date',
        'status',
        'notes',
        'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'employee_id' => 'required|integer',
        'shift_id' => 'required|integer',
        'date' => 'permit_empty|valid_date',
        'week_day' => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[6]',
        'status' => 'permit_empty|in_list[scheduled,completed,cancelled,absent]'
    ];

    protected $validationMessages = [
        'employee_id' => [
            'required' => 'O funcionário é obrigatório',
            'integer' => 'ID de funcionário inválido'
        ],
        'shift_id' => [
            'required' => 'O turno é obrigatório',
            'integer' => 'ID de turno inválido'
        ]
    ];

    /**
     * Get schedule for a specific date
     */
    public function getScheduleByDate(string $date): array
    {
        return $this->select('schedules.*, employees.name as employee_name, work_shifts.name as shift_name, work_shifts.start_time, work_shifts.end_time, work_shifts.color')
            ->join('employees', 'employees.id = schedules.employee_id')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id')
            ->where('schedules.date', $date)
            ->where('schedules.status !=', 'cancelled')
            ->orderBy('work_shifts.start_time', 'ASC')
            ->findAll();
    }

    /**
     * Get schedule for a date range
     */
    public function getScheduleByDateRange(string $startDate, string $endDate): array
    {
        return $this->select('schedules.*, employees.name as employee_name, employees.department, work_shifts.name as shift_name, work_shifts.start_time, work_shifts.end_time, work_shifts.color')
            ->join('employees', 'employees.id = schedules.employee_id')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id')
            ->where('schedules.date >=', $startDate)
            ->where('schedules.date <=', $endDate)
            ->where('schedules.status !=', 'cancelled')
            ->orderBy('schedules.date', 'ASC')
            ->orderBy('work_shifts.start_time', 'ASC')
            ->findAll();
    }

    /**
     * Get employee schedule for a month
     */
    public function getEmployeeSchedule(int $employeeId, string $month): array
    {
        return $this->select('schedules.*, work_shifts.name as shift_name, work_shifts.start_time, work_shifts.end_time, work_shifts.color')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id')
            ->where('schedules.employee_id', $employeeId)
            ->like('schedules.date', $month, 'after')
            ->where('schedules.status !=', 'cancelled')
            ->orderBy('schedules.date', 'ASC')
            ->findAll();
    }

    /**
     * Check if employee is already scheduled for a date
     */
    public function isEmployeeScheduled(int $employeeId, string $date, ?int $excludeScheduleId = null): bool
    {
        $query = $this->where('employee_id', $employeeId)
            ->where('date', $date)
            ->where('status !=', 'cancelled');

        if ($excludeScheduleId !== null) {
            $query->where('id !=', $excludeScheduleId);
        }

        return $query->countAllResults() > 0;
    }

    /**
     * Get employees assigned to a specific shift
     */
    public function getEmployeesByShift(int $shiftId): array
    {
        return $this->select('employees.*,
                             COUNT(schedules.id) as total_schedules,
                             MIN(CASE WHEN schedules.date >= CURRENT_DATE THEN schedules.date END) as next_schedule')
            ->join('employees', 'employees.id = schedules.employee_id')
            ->where('schedules.shift_id', $shiftId)
            ->where('schedules.status !=', 'cancelled')
            ->groupBy('employees.id')
            ->orderBy('employees.name', 'ASC')
            ->findAll();
    }

    /**
     * Get recurring schedules
     */
    public function getRecurringSchedules(): array
    {
        return $this->select('schedules.*, employees.name as employee_name, work_shifts.name as shift_name')
            ->join('employees', 'employees.id = schedules.employee_id')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id')
            ->where('schedules.is_recurring', 1)
            ->where('schedules.status !=', 'cancelled')
            ->findAll();
    }

    /**
     * Create recurring schedule
     *
     * @param array $data Schedule data
     * @return bool Success status
     */
    public function createRecurringSchedule(array $data): bool
    {
        if (!isset($data['week_day']) || !isset($data['recurrence_end_date'])) {
            return false;
        }

        $this->db->transStart();

        // Create schedules for each week until end date
        $currentDate = new \DateTime($data['date'] ?? 'now');
        $endDate = new \DateTime($data['recurrence_end_date']);
        $weekDay = (int)$data['week_day'];

        // Safety limit: maximum 52 weeks (1 year) to prevent excessive records
        $maxRecurrences = 52;
        $recurrenceCount = 0;

        // Adjust to the correct weekday
        while ((int)$currentDate->format('w') !== $weekDay) {
            $currentDate->modify('+1 day');
        }

        $schedules = [];
        while ($currentDate <= $endDate && $recurrenceCount < $maxRecurrences) {
            if (!$this->isEmployeeScheduled((int)$data['employee_id'], $currentDate->format('Y-m-d'))) {
                $schedules[] = [
                    'employee_id' => $data['employee_id'],
                    'shift_id' => $data['shift_id'],
                    'date' => $currentDate->format('Y-m-d'),
                    'week_day' => $weekDay,
                    'is_recurring' => true,
                    'recurrence_end_date' => $data['recurrence_end_date'],
                    'status' => 'scheduled',
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $data['created_by'] ?? null
                ];
            }

            $currentDate->modify('+7 days');
            $recurrenceCount++;
        }

        // Log warning if limit was reached
        if ($recurrenceCount >= $maxRecurrences) {
            log_message('warning', "Recurring schedule limit ({$maxRecurrences}) reached for employee {$data['employee_id']}. Some schedules may not have been created.");
        }

        if (!empty($schedules)) {
            $this->insertBatch($schedules);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Get schedule statistics
     *
     * $this->db->table() gera um builder novo a cada chamada -- encadear
     * where()/like() direto no Model (com countAllResults(false), que pula o
     * reset) faz cada linha herdar as condicoes das anteriores, ja que todas
     * rodam sobre o mesmo builder cacheado da instancia. Antes disso, exceto
     * a primeira linha ('total_schedules'), todas as demais ficavam com
     * "status = X AND status = Y" (contraditorio, sempre 0). Mesmo padrao ja
     * usado em outros pontos do sistema (ex.: FacialFraudAlertModel).
     */
    public function getScheduleStatistics(string $month): array
    {
        $scoped = function () use ($month) {
            return $this->db->table($this->table)->like('date', $month, 'after');
        };

        return [
            'total_schedules' => $scoped()->countAllResults(),
            'completed' => $scoped()->where('status', 'completed')->countAllResults(),
            'scheduled' => $scoped()->where('status', 'scheduled')->countAllResults(),
            'absent' => $scoped()->where('status', 'absent')->countAllResults(),
            'cancelled' => $scoped()->where('status', 'cancelled')->countAllResults(),
        ];
    }

    /**
     * Get employees without schedule for a date
     */
    public function getEmployeesWithoutSchedule(string $date, ?string $department = null): array
    {
        $builder = $this->db->table('employees');
        $builder->select('employees.*');
        $builder->where('employees.active', true);

        if ($department) {
            $builder->where('employees.department', $department);
        }

        $builder->whereNotIn('employees.id', function($subquery) use ($date) {
            return $subquery->select('employee_id')
                ->from('schedules')
                ->where('date', $date)
                ->where('status !=', 'cancelled');
        });

        return $builder->get()->getResult();
    }

    /**
     * Bulk assign employees to shift
     */
    public function bulkAssign(array $employeeIds, int $shiftId, string $date, ?string $notes = null): bool
    {
        $this->db->transStart();

        $schedules = [];
        foreach ($employeeIds as $employeeId) {
            if (!$this->isEmployeeScheduled((int)$employeeId, $date)) {
                $schedules[] = [
                    'employee_id' => $employeeId,
                    'shift_id' => $shiftId,
                    'date' => $date,
                    'week_day' => (int)date('w', strtotime($date)),
                    'is_recurring' => false,
                    'status' => 'scheduled',
                    'notes' => $notes
                ];
            }
        }

        if (!empty($schedules)) {
            $this->insertBatch($schedules);
        }

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Update schedule status
     */
    public function updateScheduleStatus(int $scheduleId, string $status): bool
    {
        return $this->update($scheduleId, ['status' => $status]);
    }

    /**
     * Cancel recurring schedule
     */
    public function cancelRecurringSchedule(int $employeeId, int $shiftId, int $weekDay, string $fromDate): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('shift_id', $shiftId)
            ->where('week_day', $weekDay)
            ->where('is_recurring', 1)
            ->where('date >=', $fromDate)
            ->where('status', 'scheduled')
            ->set(['status' => 'cancelled'])
            ->update();
    }

    /**
     * Get shift coverage for a date
     */
    public function getShiftCoverage(string $date): array
    {
        return $this->select('work_shifts.id, work_shifts.name, work_shifts.start_time, work_shifts.end_time, COUNT(schedules.id) as assigned_count')
            ->join('work_shifts', 'work_shifts.id = schedules.shift_id', 'right')
            ->where('schedules.date', $date)
            ->where('schedules.status', 'scheduled')
            ->orWhere('schedules.date IS NULL', null, false)
            ->groupBy('work_shifts.id')
            ->findAll();
    }
}
