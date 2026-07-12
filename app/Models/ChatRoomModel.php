<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ChatRoom Model
 *
 * Manages chat rooms (private, group, department, broadcast)
 */
class ChatRoomModel extends Model
{
    protected $table            = 'chat_rooms';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'type',
        'department',
        'created_by',
        'active',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'       => 'required|min_length[3]|max_length[255]',
        'type'       => 'required|in_list[private,group,department,broadcast]',
        'department' => 'permit_empty|max_length[100]',
        'created_by' => 'required|integer',
    ];

    protected $validationMessages = [
        'name' => [
            'required'   => 'O nome da sala é obrigatório.',
            'min_length' => 'O nome deve ter pelo menos 3 caracteres.',
        ],
        'type' => [
            'required' => 'O tipo da sala é obrigatório.',
            'in_list'  => 'Tipo de sala inválido.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Get rooms for a specific employee
     *
     * @param int $employeeId
     * @return array
     */
    public function getRoomsForEmployee(int $employeeId): array
    {
        return $this->select('chat_rooms.*, COUNT(DISTINCT chat_messages.id) as message_count,
                              MAX(chat_messages.created_at) as last_message_at')
            ->join('chat_room_members', 'chat_room_members.room_id = chat_rooms.id', 'left')
            ->join('chat_messages', 'chat_messages.room_id = chat_rooms.id', 'left')
            ->where('chat_room_members.employee_id', $employeeId)
            ->where('chat_rooms.active', true)
            ->groupBy('chat_rooms.id')
            ->orderBy('last_message_at', 'DESC')
            ->findAll();
    }

    /**
     * Get or create private room between two employees
     *
     * @param int $employee1Id
     * @param int $employee2Id
     * @return object|null
     */
    public function getOrCreatePrivateRoom(int $employee1Id, int $employee2Id): ?object
    {
        // Check if room already exists
        $existingRoom = $this->select('chat_rooms.*')
            ->join('chat_room_members as m1', 'm1.room_id = chat_rooms.id', 'inner')
            ->join('chat_room_members as m2', 'm2.room_id = chat_rooms.id', 'inner')
            ->where('chat_rooms.type', 'private')
            ->where('m1.employee_id', $employee1Id)
            ->where('m2.employee_id', $employee2Id)
            ->first();

        if ($existingRoom) {
            return $existingRoom;
        }

        // Create new private room
        $roomId = $this->insert([
            'name'       => 'Private Chat',
            'type'       => 'private',
            'created_by' => $employee1Id,
            'active'     => true,
        ]);

        if ($roomId) {
            // Add both members
            $memberModel = new \App\Models\ChatRoomMemberModel();
            $memberModel->insert([
                'room_id'     => $roomId,
                'employee_id' => $employee1Id,
                'role'        => 'admin',
                'joined_at'   => date('Y-m-d H:i:s'),
            ]);
            $memberModel->insert([
                'room_id'     => $roomId,
                'employee_id' => $employee2Id,
                'role'        => 'member',
                'joined_at'   => date('Y-m-d H:i:s'),
            ]);

            return $this->find($roomId);
        }

        return null;
    }

    /**
     * Get department broadcast room
     *
     * @param string $department
     * @return object|null
     */
    public function getDepartmentRoom(string $department): ?object
    {
        return $this->where('type', 'department')
            ->where('department', $department)
            ->where('active', true)
            ->first();
    }

    /**
     * Get broadcast room (company-wide)
     *
     * @return object|null
     */
    public function getBroadcastRoom(): ?object
    {
        return $this->where('type', 'broadcast')
            ->where('active', true)
            ->first();
    }

    /**
     * Get unread count for employee in room
     *
     * @param int $roomId
     * @param int $employeeId
     * @return int
     */
    public function getUnreadCount(int $roomId, int $employeeId): int
    {
        $memberModel = new \App\Models\ChatRoomMemberModel();
        $member = $memberModel->where('room_id', $roomId)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$member) {
            return 0;
        }

        $messageModel = new \App\Models\ChatMessageModel();
        $query = $messageModel->where('room_id', $roomId);

        if ($member->last_read_at) {
            $query->where('created_at >', $member->last_read_at);
        }

        return $query->countAllResults();
    }

    /**
     * Get unread counts for all given rooms at once for a specific employee.
     *
     * Uses a single LEFT JOIN between chat_room_members and chat_messages to compute
     * per-room unread counts, eliminating the N+1 caused by calling getUnreadCount()
     * once per room in getEmployeeRooms().
     *
     * @param int   $employeeId
     * @param int[] $roomIds
     * @return array<int,int>  map of room_id => unread_count
     */
    public function getUnreadCountsForEmployee(int $employeeId, array $roomIds): array
    {
        if (empty($roomIds)) {
            return [];
        }

        $db = \Config\Database::connect();
        $placeholders = implode(',', array_fill(0, count($roomIds), '?'));

        // Single query: join membership (for last_read_at) with messages (conditional on read cursor)
        $sql = "
            SELECT crm.room_id, COUNT(cm.id) AS unread_count
            FROM chat_room_members crm
            LEFT JOIN chat_messages cm
                ON cm.room_id = crm.room_id
                AND (crm.last_read_at IS NULL OR cm.created_at > crm.last_read_at)
            WHERE crm.employee_id = ?
              AND crm.room_id IN ({$placeholders})
            GROUP BY crm.room_id
        ";

        $params = array_merge([$employeeId], $roomIds);
        $rows = $db->query($sql, $params)->getResultArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['room_id']] = (int) $row['unread_count'];
        }

        return $counts;
    }
}
