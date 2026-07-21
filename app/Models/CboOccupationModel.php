<?php

namespace App\Models;

use CodeIgniter\Model;

class CboOccupationModel extends Model
{
    protected $table = 'cbo_occupations';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'code', 'title', 'active', 'created_at', 'updated_at',
    ];
    protected $useTimestamps = true;

    public function getActive()
    {
        return $this->where('active', true)->orderBy('title', 'ASC')->findAll();
    }
}
