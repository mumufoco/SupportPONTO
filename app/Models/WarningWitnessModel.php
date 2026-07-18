<?php

namespace App\Models;

use CodeIgniter\Model;

class WarningWitnessModel extends Model
{
    protected $table            = 'warning_witnesses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'warning_id',
        'witness_name',
        'witness_cpf',
        'witness_signature',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    protected $validationRules = [
        'warning_id'        => 'required|integer',
        'witness_name'      => 'required|min_length[3]|max_length[255]',
        'witness_cpf'       => 'required|exact_length[14]',
        'witness_signature' => 'required',
    ];

    /**
     * @return list<object>
     */
    public function forWarning(int $warningId): array
    {
        return $this->where('warning_id', $warningId)->orderBy('id', 'ASC')->findAll();
    }
}
