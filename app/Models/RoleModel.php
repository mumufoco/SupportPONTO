<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = [
        'name', 'description', 'permissions', 'active', 'created_at', 'updated_at'
    ];
    protected $useTimestamps = true;

    /**
     * Get active roles
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
