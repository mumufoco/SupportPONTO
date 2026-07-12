<?php
namespace App\Models;
use CodeIgniter\Model;

class EmployeeInviteModel extends Model
{
    protected $table         = 'employee_invites';
    protected $primaryKey    = 'id';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'token','email','name','department','position','role',
        'message','expires_at','used_at','status','created_by',
    ];

    public function findByToken(string $token): ?object
    {
        return $this->where('token', $token)->first();
    }

    public function isValid(object $invite): bool
    {
        return $invite->status === 'pending'
            && $invite->used_at === null
            && strtotime((string) $invite->expires_at) > time();
    }

    public function markUsed(int $id): void
    {
        $this->update($id, ['status' => 'used', 'used_at' => date('Y-m-d H:i:s')]);
    }


    /**
     * Lista TODOS os convites agrupados por estado computado:
     *  - active:   pending + dentro do prazo + não utilizado
     *  - expired:  pending + fora do prazo + não utilizado
     *  - used:     utilizado (status = 'used')
     */
    public function listAllWithStatus(): array
    {
        $now = date('Y-m-d H:i:s');
        $all = $this->orderBy('created_at', 'DESC')->findAll();

        $active  = [];
        $expired = [];
        $used    = [];

        foreach ($all as $inv) {
            if ($inv->status === 'used' || $inv->used_at !== null) {
                $used[] = $inv;
            } elseif (strtotime((string) $inv->expires_at) <= time()) {
                $expired[] = $inv;
            } else {
                $active[] = $inv;
            }
        }

        return compact('active', 'expired', 'used');
    }

    public function listActive(): array
    {
        return $this->where('status', 'pending')
                    ->where('expires_at >', date('Y-m-d H:i:s'))
                    ->where('used_at IS NULL', null, false)
                    ->orderBy('created_at', 'DESC')
                    ->findAll();
    }
}
