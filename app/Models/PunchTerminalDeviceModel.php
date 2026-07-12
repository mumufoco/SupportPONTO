<?php

namespace App\Models;

use CodeIgniter\Model;

class PunchTerminalDeviceModel extends Model
{
    protected $table = 'punch_terminal_devices';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $protectFields = true;
    protected $allowedFields = [
        'terminal_id','name','secret_hash','allowed_ip','device_fingerprint','active',
        'last_seen_at','last_ip','last_user_agent','created_by','revoked_at','revoked_by','notes'
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    public function findActiveByTerminalId(string $terminalId): ?object
    {
        return $this->where('terminal_id', $terminalId)->where('active', true)->first();
    }
}
