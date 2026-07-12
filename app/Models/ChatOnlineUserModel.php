<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ChatOnlineUser Model
 *
 * Tracks online users and their WebSocket connections
 */
class ChatOnlineUserModel extends Model
{
    protected $table            = 'chat_online_users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'employee_id',
        'connection_id',
        'status',
        'last_activity',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = null;

    // Validation
    protected $validationRules = [
        'employee_id'   => 'required|integer',
        'connection_id' => 'required|max_length[64]',
        'status'        => 'permit_empty|in_list[online,away,busy,offline]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Add online user
     *
     * @param int    $employeeId
     * @param string $connectionId
     * @return int|false
     */
    public function addOnlineUser(int $employeeId, string $connectionId): int|false
    {
        return $this->insert([
            'employee_id'   => $employeeId,
            'connection_id' => $connectionId,
            'status'        => 'online',
            'last_activity' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Remove online user by connection ID
     *
     * @param string $connectionId
     * @return bool
     */
    public function removeOnlineUser(string $connectionId): bool
    {
        return $this->where('connection_id', $connectionId)->delete();
    }

    /**
     * Update user activity
     *
     * @param string $connectionId
     * @return bool
     */
    public function updateActivity(string $connectionId): bool
    {
        return $this->where('connection_id', $connectionId)
            ->set(['last_activity' => date('Y-m-d H:i:s')])
            ->update();
    }

    /**
     * Update user status
     *
     * @param string $connectionId
     * @param string $status
     * @return bool
     */
    public function updateStatus(string $connectionId, string $status): bool
    {
        return $this->where('connection_id', $connectionId)
            ->set([
                'status'        => $status,
                'last_activity' => date('Y-m-d H:i:s'),
            ])
            ->update();
    }

    /**
     * Get online users
     *
     * @return array
     */
    public function getOnlineUsers(): array
    {
        $sql = "
            SELECT DISTINCT ON (cou.employee_id)
                cou.id, cou.employee_id, cou.connection_id, cou.status,
                cou.last_activity, cou.created_at,
                e.name, e.email, e.department
            FROM chat_online_users cou
            LEFT JOIN employees e ON e.id = cou.employee_id
            WHERE cou.status != 'offline'
            ORDER BY cou.employee_id, cou.last_activity DESC
        ";
        $result = $this->db->query($sql);
        return $result ? $result->getResultObject() : [];
    }

    /**
     * Get connections for employee
     *
     * @param int $employeeId
     * @return array
     */
    public function getEmployeeConnections(int $employeeId): array
    {
        return $this->where('employee_id', $employeeId)
            ->where('status !=', 'offline')
            ->findAll();
    }

    /**
     * Check if employee is online
     *
     * @param int $employeeId
     * @return bool
     */
    public function isOnline(int $employeeId): bool
    {
        return $this->where('employee_id', $employeeId)
            ->where('status', 'online')
            ->countAllResults() > 0;
    }

    /**
     * Cleanup inactive connections
     *
     * @param int $minutes
     * @return int Number of deleted connections
     */
    public function cleanupInactive(int $minutes = 5): int
    {
        $cutoffTime = date('Y-m-d H:i:s', strtotime("-{$minutes} minutes"));

        return $this->where('last_activity <', $cutoffTime)->delete();
    }
}
