<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkUnitModel extends Model
{
    protected $table = 'work_units';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'name', 'description', 'active', 'created_at', 'updated_at'
    ];
    protected $useTimestamps = true;

    /**
     * Get active work units
     */
    public function getActive()
    {
        return $this->where('active', true)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Check if table exists
     */
    public function tableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM ' . $this->table . ' LIMIT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
