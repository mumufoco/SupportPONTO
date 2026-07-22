<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/**
 * Work Shift Model
 *
 * Manages work shifts (morning, afternoon, night, custom)
 */
class WorkShiftModel extends Model
{
    protected $table = 'work_shifts';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = true;
    protected $protectFields = true;

    protected $allowedFields = [
        'name',
        'description',
        'start_time',
        'end_time',
        'color',
        'type',
        'break_duration',
        'lunch_start_time',
        'lunch_end_time',
        'active',
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
        'name' => 'required|max_length[100]',
        'start_time' => 'required|valid_time',
        'end_time' => 'required|valid_time',
        'type' => 'required|in_list[morning,afternoon,night,custom]',
        'color' => 'permit_empty|max_length[7]',
        'break_duration' => 'permit_empty|integer|greater_than_equal_to[0]',
        'lunch_start_time' => 'permit_empty|valid_time',
        'lunch_end_time' => 'permit_empty|valid_time'
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'O nome do turno é obrigatório',
            'max_length' => 'O nome não pode ter mais de 100 caracteres'
        ],
        'start_time' => [
            'required' => 'O horário de início é obrigatório',
            'valid_time' => 'Horário de início inválido'
        ],
        'end_time' => [
            'required' => 'O horário de término é obrigatório',
            'valid_time' => 'Horário de término inválido'
        ],
        'type' => [
            'required' => 'O tipo de turno é obrigatório',
            'in_list' => 'Tipo de turno inválido'
        ]
    ];

    // Callbacks
    protected $beforeInsert = ['setCreatedBy'];
    protected $beforeUpdate = [];
    protected $afterInsert = ['clearCacheAfterChange'];
    protected $afterUpdate = ['clearCacheAfterChange'];
    protected $afterDelete = ['clearCacheAfterChange'];
    protected $afterFind = ['castBooleans'];

    /**
     * Normaliza 'active' para bool de verdade — o Postgres retorna colunas
     * boolean como string 't'/'f', e sem isso !empty()/checagens truthy
     * tratam 'f' como valor verdadeiro (mesmo bug já corrigido em
     * TimePunchModel/RoleModel/GeofenceModel).
     */
    protected function castBooleans(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (is_object($row) && property_exists($row, 'active')) {
                $row->active = in_array($row->active, [true, 't', '1', 1, 'true'], true);
            }
        }

        return $data;
    }

    /**
     * Set created_by field
     */
    protected function setCreatedBy(array $data): array
    {
        if (!isset($data['data']['created_by'])) {
            $session = session();
            $userId = $session->get('user_id');

            if ($userId) {
                $data['data']['created_by'] = $userId;
            }
        }

        return $data;
    }

    /**
     * Get all active shifts (with cache)
     *
     * Results are cached for 1 hour to reduce database queries.
     * Cache is automatically cleared when shifts are modified.
     *
     * @return array Active shifts
     */
    public function getActiveShifts(): array
    {
        $cache = \Config\Services::cache();
        $cacheKey = 'work_shifts_active';

        // Try to get from cache first
        $shifts = $cache->get($cacheKey);

        if ($shifts === null) {
            // Not in cache, query database
            $shifts = $this->where('active', true)
                ->orderBy('type', 'ASC')
                ->orderBy('start_time', 'ASC')
                ->findAll();

            // Store in cache for 1 hour (3600 seconds)
            $cache->save($cacheKey, $shifts, 3600);
        }

        return $shifts;
    }

    /**
     * Clear active shifts cache
     *
     * Call this after creating, updating, or deleting shifts
     */
    protected function clearActiveShiftsCache(): void
    {
        $cache = \Config\Services::cache();
        $cache->delete('work_shifts_active');
    }

    /**
     * Clear cache after insert/update/delete
     *
     * This is called automatically by model callbacks
     */
    protected function clearCacheAfterChange(array $data): array
    {
        $this->clearActiveShiftsCache();
        return $data;
    }

    /**
     * Get shifts by type
     */
    public function getShiftsByType(string $type): array
    {
        return $this->where('type', $type)
            ->where('active', true)
            ->findAll();
    }

    /**
     * Calculate shift duration in hours
     */
    public function calculateDuration(object $shift): float
    {
        $start = strtotime($shift->start_time);
        $end = strtotime($shift->end_time);

        // Handle overnight shifts
        if ($end < $start) {
            $end += 86400; // Add 24 hours
        }

        $duration = ($end - $start) / 3600; // Convert to hours

        // Subtract break duration
        if (!empty($shift->break_duration)) {
            $duration -= ($shift->break_duration / 60);
        }

        return round($duration, 2);
    }

    /**
     * Check if time ranges overlap
     */
    public function hasTimeOverlap(string $start1, string $end1, string $start2, string $end2): bool
    {
        $s1 = strtotime($start1);
        $e1 = strtotime($end1);
        $s2 = strtotime($start2);
        $e2 = strtotime($end2);

        // Handle overnight shifts
        if ($e1 < $s1) $e1 += 86400;
        if ($e2 < $s2) $e2 += 86400;

        return ($s1 < $e2) && ($e1 > $s2);
    }

    /**
     * Get shift statistics
     *
     * Single GROUP BY query — eliminates N+1 (was 6 queries: total + active + 4 per-type counts)
     */
    public function getShiftStatistics(): array
    {
        $knownTypes = ['morning', 'afternoon', 'night', 'custom'];

        // Fetch all type/active combinations in one query; builder() applies soft-delete scope automatically
        $rows = $this->builder()
            ->select('type, active, COUNT(*) AS cnt', false)
            ->groupBy('type, active')
            ->get()
            ->getResultArray();

        $total  = 0;
        $active = 0;
        $byType = array_fill_keys($knownTypes, 0);

        foreach ($rows as $row) {
            $cnt    = (int) ($row['cnt'] ?? 0);
            $type   = (string) ($row['type'] ?? '');
            $isActive = (bool) ($row['active'] ?? false);

            $total += $cnt;
            if ($isActive) {
                $active += $cnt;
                if (array_key_exists($type, $byType)) {
                    $byType[$type] += $cnt;
                }
            }
        }

        return [
            'total'   => $total,
            'active'  => $active,
            'by_type' => $byType,
        ];
    }

    /**
     * Get shifts with employee count
     */
    public function getShiftsWithEmployeeCount(): array
    {
        return $this->select('work_shifts.*, COUNT(DISTINCT schedules.employee_id) as employee_count')
            ->join('schedules', 'schedules.shift_id = work_shifts.id', 'left')
            ->where('.active', true)
            ->groupBy('work_shifts.id')
            ->findAll();
    }

    /**
     * Find shifts that overlap with given time range
     */
    public function findOverlappingShifts(string $startTime, string $endTime, ?int $excludeId = null): array
    {
        $query = $this->where('active', true);

        if ($excludeId !== null) {
            $query->where('id !=', $excludeId);
        }

        $allShifts = $query->findAll();
        $overlapping = [];

        foreach ($allShifts as $shift) {
            if ($this->hasTimeOverlap($startTime, $endTime, $shift->start_time, $shift->end_time)) {
                $overlapping[] = $shift;
            }
        }

        return $overlapping;
    }

    /**
     * Clone a shift
     */
    public function cloneShift(int $shiftId, ?string $newName = null): ?int
    {
        $shift = $this->find($shiftId);

        if (!$shift) {
            return null;
        }

        $newShift = [
            'name' => $newName ?? ($shift->name . ' (Cópia)'),
            'description' => $shift->description,
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'color' => $shift->color,
            'type' => $shift->type,
            'break_duration' => $shift->break_duration,
            'lunch_start_time' => $shift->lunch_start_time ?? null,
            'lunch_end_time' => $shift->lunch_end_time ?? null,
            'active' => 0 // Clones start as inactive
        ];

        return $this->insert($newShift) ? $this->getInsertID() : null;
    }

    /**
     * Get default shifts for installation
     */
    public static function getDefaultShifts(): array
    {
        return [
            [
                'name' => 'Manhã',
                'description' => 'Turno da manhã padrão',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'color' => '#FFA500',
                'type' => 'morning',
                'break_duration' => 0,
                'active' => 1
            ],
            [
                'name' => 'Tarde',
                'description' => 'Turno da tarde padrão',
                'start_time' => '13:00:00',
                'end_time' => '18:00:00',
                'color' => '#4169E1',
                'type' => 'afternoon',
                'break_duration' => 0,
                'active' => 1
            ],
            [
                'name' => 'Noite',
                'description' => 'Turno da noite padrão',
                'start_time' => '22:00:00',
                'end_time' => '06:00:00',
                'color' => '#2F4F4F',
                'type' => 'night',
                'break_duration' => 60,
                'active' => 1
            ],
            [
                'name' => 'Comercial',
                'description' => 'Horário comercial completo',
                'start_time' => '08:00:00',
                'end_time' => '18:00:00',
                'color' => '#228B22',
                'type' => 'custom',
                'break_duration' => 60,
                'active' => 1
            ]
        ];
    }
}
