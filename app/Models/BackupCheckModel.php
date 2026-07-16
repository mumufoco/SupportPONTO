<?php

namespace App\Models;

use CodeIgniter\Model;

class BackupCheckModel extends Model
{
    protected $table = 'backup_checks';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'status', 'last_backup_at', 'backup_size_bytes', 'destination',
        'integrity_ok', 'critical_files', 'risks', 'checked_at', 'created_at',
    ];

    protected $afterFind = ['castTypes'];

    protected function castTypes(array $data): array
    {
        if (empty($data['data'])) {
            return $data;
        }

        $rows = $data['singleton'] ? [$data['data']] : $data['data'];

        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $row->integrity_ok = in_array($row->integrity_ok, [true, 't', '1', 1, 'true'], true);
            $row->critical_files = json_decode((string) ($row->critical_files ?? '[]'), true) ?: [];
            $row->risks = json_decode((string) ($row->risks ?? '[]'), true) ?: [];
        }

        return $data;
    }

    public function latest(): ?object
    {
        return $this->orderBy('checked_at', 'DESC')->first();
    }
}
