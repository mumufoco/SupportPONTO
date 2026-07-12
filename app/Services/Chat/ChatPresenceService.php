<?php

namespace App\Services\Chat;

use App\Models\ChatOnlineUserModel;
use App\Models\ChatRoomMemberModel;

class ChatPresenceService
{
    public function __construct(
        private readonly ChatRoomMemberModel $memberModel = new ChatRoomMemberModel(),
        private readonly ChatOnlineUserModel $onlineUserModel = new ChatOnlineUserModel(),
    ) {
    }

    public function getOnlineUsers(): array
    {
        return $this->onlineUserModel->getOnlineUsers();
    }

    public function getRoomMembers(int $roomId, int $employeeId): array
    {
        if (!$this->memberModel->isMember($roomId, $employeeId)) {
            return ['success' => false, 'message' => 'Você não é membro desta sala.'];
        }

        $members = $this->memberModel->getRoomMembers($roomId);

        if (!empty($members)) {
            // Batch fetch online status for all members in one query — eliminates N+1 (was 1 query per member)
            $memberIds = array_column((array) $members, 'employee_id');
            $onlineRows = $this->onlineUserModel
                ->select('employee_id')
                ->whereIn('employee_id', $memberIds)
                ->where('status', 'online')
                ->findAll();

            $onlineIds = [];
            foreach ($onlineRows as $row) {
                $onlineIds[(int) $row->employee_id] = true;
            }

            foreach ($members as &$member) {
                $member->is_online = isset($onlineIds[(int) $member->employee_id]);
            }
        }

        return ['success' => true, 'members' => $members];
    }
}
