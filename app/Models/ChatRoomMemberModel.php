<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ChatRoomMember Model
 *
 * Manages chat room memberships
 */
class ChatRoomMemberModel extends Model
{
    protected $table            = 'chat_room_members';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'room_id',
        'employee_id',
        'role',
        'last_read_at',
        'joined_at',
    ];

    // Dates
    protected $useTimestamps = false;

    // Validation
    protected $validationRules = [
        'room_id'     => 'required|integer',
        'employee_id' => 'required|integer',
        'role'        => 'permit_empty|in_list[member,admin]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Get members of a room
     *
     * @param int $roomId
     * @return array
     */
    public function getRoomMembers(int $roomId): array
    {
        return $this->select('chat_room_members.*, employees.name, employees.email, employees.department')
            ->join('employees', 'employees.id = chat_room_members.employee_id', 'left')
            ->where('chat_room_members.room_id', $roomId)
            ->findAll();
    }

    /**
     * Check if employee is member of room
     *
     * @param int $roomId
     * @param int $employeeId
     * @return bool
     */
    public function isMember(int $roomId, int $employeeId): bool
    {
        return $this->where('room_id', $roomId)
            ->where('employee_id', $employeeId)
            ->countAllResults() > 0;
    }

    /**
     * Update last read timestamp
     *
     * @param int $roomId
     * @param int $employeeId
     * @return bool
     */
    public function markAsRead(int $roomId, int $employeeId): bool
    {
        $member = $this->where('room_id', $roomId)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$member) {
            return false;
        }

        return $this->update($member->id, [
            'last_read_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
