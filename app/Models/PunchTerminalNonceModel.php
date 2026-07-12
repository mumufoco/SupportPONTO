<?php

namespace App\Models;

use CodeIgniter\Model;

class PunchTerminalNonceModel extends Model
{
    protected $table = 'punch_terminal_nonces';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'object';
    protected $protectFields = true;
    protected $allowedFields = ['terminal_id','nonce_hash','purpose','ip_address','expires_at','consumed_at','created_at'];
    protected $useTimestamps = false;

    public function nonceExists(string $terminalId, string $nonceHash, string $purpose): bool
    {
        return $this->where('terminal_id', $terminalId)
            ->where('nonce_hash', $nonceHash)
            ->where('purpose', $purpose)
            ->countAllResults() > 0;
    }
}
