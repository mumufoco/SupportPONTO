<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class HolidayModel extends Model
{
    protected $table = 'holidays';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $protectFields = true;

    protected $allowedFields = [
        'name',
        'description',
        'date',
        'type',
        'recurring',
        'blocks_punch',
        'active',
        'created_by',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[255]',
        'date' => 'required|valid_date',
        'type' => 'required|in_list[national,state,municipal,company,non_working]',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'O nome do feriado é obrigatório',
            'min_length' => 'O nome deve ter pelo menos 2 caracteres',
            'max_length' => 'O nome não pode ter mais de 255 caracteres',
        ],
        'date' => [
            'required'   => 'A data do feriado é obrigatória',
            'valid_date' => 'Data inválida',
        ],
        'type' => [
            'required' => 'O tipo é obrigatório',
            'in_list'  => 'Tipo inválido',
        ],
    ];

    public static function typeLabel(string $type): string
    {
        return match($type) {
            'national'    => 'Nacional',
            'state'       => 'Estadual',
            'municipal'   => 'Municipal',
            'company'     => 'Empresa',
            'non_working' => 'Dia Não Trabalhado',
            default       => ucfirst($type),
        };
    }

    /**
     * Returns a holiday that blocks punch for the given date, or null if none.
     */
    public function getBlockingHolidayForDate(string $date): ?object
    {
        $holiday = $this->getHolidayInfo($date);
        if ($holiday && !empty($holiday->blocks_punch)) {
            return $holiday;
        }
        return null;
    }

    public function getActive(): array
    {
        return $this->where('active', 1)
            ->orderBy('date', 'ASC')
            ->findAll();
    }

    public function getByYear(int $year): array
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $fixedYear = $this->where('active', 1)
            ->where('date >=', $startDate)
            ->where('date <=', $endDate)
            ->orderBy('date', 'ASC')
            ->findAll();

        $recurring = $this->getRecurring();

        return array_values(array_merge($fixedYear, array_filter($recurring, static function ($holiday) use ($year) {
            return (int) date('Y', strtotime((string) $holiday->date)) !== $year;
        })));
    }

    public function getRecurring(): array
    {
        return $this->where('active', 1)
            ->where('recurring', 1)
            ->orderBy('date', 'ASC')
            ->findAll();
    }

    public function isHoliday(string $date): bool
    {
        return $this->getHolidayInfo($date) !== null;
    }

    public function getHolidayInfo(string $date): ?object
    {
        $holiday = $this->where('active', 1)
            ->where('date', $date)
            ->first();

        if ($holiday) {
            return $holiday;
        }

        $targetMonthDay = date('m-d', strtotime($date));

        foreach ($this->getRecurring() as $recurring) {
            if (date('m-d', strtotime((string) $recurring->date)) === $targetMonthDay) {
                return $recurring;
            }
        }

        return null;
    }

    public static function getDefaultHolidays(): array
    {
        return [
            ['name' => 'Confraternização Universal', 'date' => '2025-01-01', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Tiradentes', 'date' => '2025-04-21', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Dia do Trabalhador', 'date' => '2025-05-01', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Independência do Brasil', 'date' => '2025-09-07', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Nossa Senhora Aparecida', 'date' => '2025-10-12', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Finados', 'date' => '2025-11-02', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Proclamação da República', 'date' => '2025-11-15', 'type' => 'national', 'recurring' => 1, 'active' => 1],
            ['name' => 'Natal', 'date' => '2025-12-25', 'type' => 'national', 'recurring' => 1, 'active' => 1],
        ];
    }
}
