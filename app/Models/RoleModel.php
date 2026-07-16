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
    protected $afterFind = ['castBooleans'];

    /**
     * O driver Postgres retorna 'active' como string 't'/'f', que
     * filter_var($v, FILTER_VALIDATE_BOOLEAN) NAO reconhece (so aceita
     * '1'/'true'/'0'/'false'), fazendo TODO nivel de acesso aparecer
     * como 'Inativo' independente do valor real salvo. Mesmo padrao
     * de fix ja usado em GeofenceModel::castBooleans().
     */
    protected function castBooleans(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (is_object($row)) {
                $row->active = in_array($row->active, [true, 't', '1', 1, 'true'], true);
            }
        }

        return $data;
    }

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
