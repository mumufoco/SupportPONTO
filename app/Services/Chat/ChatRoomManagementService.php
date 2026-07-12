<?php

namespace App\Services\Chat;

use App\Models\ChatRoomMemberModel;
use App\Models\ChatRoomModel;

class ChatRoomManagementService
{
    public function __construct(
        private readonly ChatRoomModel $roomModel = new ChatRoomModel(),
        private readonly ChatRoomMemberModel $memberModel = new ChatRoomMemberModel(),
    ) {
    }

    public function getOrCreatePrivateRoom(int $employee1Id, int $employee2Id): array
    {
        $room = $this->roomModel->getOrCreatePrivateRoom($employee1Id, $employee2Id);
        if (!$room) {
            return ['success' => false, 'message' => 'Erro ao criar sala de chat.'];
        }

        return ['success' => true, 'room' => $room];
    }

    public function createGroupRoom(int $creatorId, string $name, array $memberIds): array
    {
        $roomId = $this->roomModel->insert([
            'name' => $name,
            'type' => 'group',
            'created_by' => $creatorId,
            'active' => true,
        ]);

        if (!$roomId) {
            return ['success' => false, 'message' => 'Erro ao criar sala de chat em grupo.'];
        }

        $this->memberModel->insert([
            'room_id' => $roomId,
            'employee_id' => $creatorId,
            'role' => 'admin',
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($memberIds as $memberId) {
            if ($memberId !== $creatorId) {
                $this->memberModel->insert([
                    'room_id' => $roomId,
                    'employee_id' => $memberId,
                    'role' => 'member',
                    'joined_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return ['success' => true, 'room' => $this->roomModel->find($roomId)];
    }

    public function getEmployeeRooms(int $employeeId): array
    {
        $rooms = $this->roomModel->getRoomsForEmployee($employeeId);

        if (!empty($rooms)) {
            // Batch fetch all unread counts in one query — eliminates N+1 (was 2 queries per room)
            $roomIds = array_column((array) $rooms, 'id');
            $unreadCounts = $this->roomModel->getUnreadCountsForEmployee($employeeId, $roomIds);

            foreach ($rooms as &$room) {
                $room->unread_count = $unreadCounts[$room->id] ?? 0;
            }
        }

        return $rooms;
    }

    public function addMember(int $roomId, int $employeeId, int $newMemberId): array
    {
        $room = $this->roomModel->find($roomId);
        if (!$room) {
            return ['success' => false, 'message' => 'Sala não encontrada.'];
        }

        $member = $this->memberModel->where('room_id', $roomId)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$member || ($member->role !== 'admin' && $room->created_by !== $employeeId)) {
            return ['success' => false, 'message' => 'Você não tem permissão para adicionar membros.'];
        }

        if ($this->memberModel->isMember($roomId, $newMemberId)) {
            return ['success' => false, 'message' => 'Usuário já é membro desta sala.'];
        }

        $this->memberModel->insert([
            'room_id' => $roomId,
            'employee_id' => $newMemberId,
            'role' => 'member',
            'joined_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true, 'message' => 'Membro adicionado com sucesso.'];
    }

    public function removeMember(int $roomId, int $employeeId, int $memberToRemove): array
    {
        $room = $this->roomModel->find($roomId);
        if (!$room) {
            return ['success' => false, 'message' => 'Sala não encontrada.'];
        }

        $member = $this->memberModel->where('room_id', $roomId)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$member || ($member->role !== 'admin' && $room->created_by !== $employeeId)) {
            return ['success' => false, 'message' => 'Você não tem permissão para remover membros.'];
        }

        $this->memberModel->where('room_id', $roomId)
            ->where('employee_id', $memberToRemove)
            ->delete();

        return ['success' => true, 'message' => 'Membro removido com sucesso.'];
    }
}
