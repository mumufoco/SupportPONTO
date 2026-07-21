<?php

namespace App\Models;

use CodeIgniter\Model;

class ContractTypeModel extends Model
{
    protected $table = 'contract_types';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'name', 'description', 'active', 'created_at', 'updated_at'
    ];
    protected $useTimestamps = true;

    /**
     * Get active contract types
     */
    public function getActive()
    {
        return $this->where('active', true)->orderBy('name', 'ASC')->findAll();
    }
}
