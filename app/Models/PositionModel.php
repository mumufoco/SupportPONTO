<?php

namespace App\Models;

use CodeIgniter\Model;

class PositionModel extends Model
{
    protected $table = 'positions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = ['name', 'description', 'department_id', 'cbo_occupation_id', 'active'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'name' => 'required|min_length[2]|max_length[255]',
        'description' => 'permit_empty|max_length[1000]',
        'department_id' => 'required|integer|is_not_unique[departments.id]',
        'cbo_occupation_id' => 'permit_empty|integer|is_not_unique[cbo_occupations.id]',
        'active' => 'permit_empty|in_list[0,1,true,false]',
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'O nome do cargo é obrigatório.',
        ],
        'department_id' => [
            'required' => 'O departamento é obrigatório.',
            'is_not_unique' => 'Departamento inválido.',
        ],
        'cbo_occupation_id' => [
            'is_not_unique' => 'Código CBO inválido.',
        ],
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Get active positions
     */
    public function getActive()
    {
        return $this->select('positions.*, departments.name as department_name')
            ->join('departments', 'departments.id = positions.department_id')
            ->where('positions.active', true)
            ->orderBy('departments.name', 'ASC')
            ->orderBy('positions.name', 'ASC')
            ->findAll();
    }

    /**
     * Get positions by department
     */
    public function getByDepartment($departmentId)
    {
        return $this->where('department_id', $departmentId)
            ->where('active', true)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
