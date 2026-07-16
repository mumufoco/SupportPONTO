<?php

namespace App\Models;

use CodeIgniter\Model;

class RestoreTestRecordModel extends Model
{
    protected $table = 'restore_test_records';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;
    protected $allowedFields = ['tested_by', 'tested_at', 'status', 'notes', 'created_at'];

    public function latest(): ?object
    {
        return $this->orderBy('tested_at', 'DESC')->first();
    }
}
