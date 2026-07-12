<?php

namespace App\Services;

class ChatApiWorkflowService
{
    private ChatService $chatService;

    public function __construct()
    {
        $this->chatService = new ChatService();
    }

    public function rooms(int $employeeId): array
    {
        return ['success' => true, 'data' => ['rooms' => $this->chatService->getEmployeeRooms($employeeId)]];
    }

    public function messages(int $roomId, int $employeeId, int $limit = 50, int $offset = 0): array
    {
        $result = $this->chatService->getRoomMessages($roomId, $employeeId, $limit, $offset);
        if (!$result['success']) {
            return ['success' => false, 'status' => 403, 'code' => 'room_forbidden', 'message' => $result['message']];
        }

        return ['success' => true, 'data' => ['messages' => $result['messages'] ?? []]];
    }

    public function sendMessage(int $roomId, int $employeeId, string $message, ?int $replyTo = null): array
    {
        $result = $this->chatService->sendMessage($roomId, $employeeId, $message, $replyTo);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'send_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'status' => 201, 'data' => $result];
    }

    public function editMessage(int $messageId, int $employeeId, string $message): array
    {
        $result = $this->chatService->editMessage($messageId, $employeeId, $message);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'edit_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'data' => $result];
    }

    public function deleteMessage(int $messageId, int $employeeId): array
    {
        $result = $this->chatService->deleteMessage($messageId, $employeeId);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'delete_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'data' => $result];
    }

    public function addReaction(int $messageId, int $employeeId, string $emoji): array
    {
        $ok = $this->chatService->addReaction($messageId, $employeeId, $emoji);
        return [
            'success' => true,
            'data' => [
                'success' => $ok,
                'message' => $ok ? 'Reação adicionada/removida com sucesso.' : 'Erro ao adicionar reação.',
            ],
        ];
    }

    public function markAsRead(int $roomId, int $employeeId): array
    {
        $ok = $this->chatService->markAsRead($roomId, $employeeId);
        return [
            'success' => true,
            'data' => [
                'success' => $ok,
                'message' => $ok ? 'Marcado como lido.' : 'Erro ao marcar como lido.',
            ],
        ];
    }

    public function members(int $roomId, int $employeeId): array
    {
        $result = $this->chatService->getRoomMembers($roomId, $employeeId);
        if (!$result['success']) {
            return ['success' => false, 'status' => 403, 'code' => 'room_forbidden', 'message' => $result['message']];
        }

        return ['success' => true, 'data' => ['members' => $result['members'] ?? []]];
    }

    public function addMember(int $roomId, int $employeeId, int $newMemberId): array
    {
        $result = $this->chatService->addMember($roomId, $employeeId, $newMemberId);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'add_member_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'status' => 201, 'data' => $result];
    }

    public function removeMember(int $roomId, int $employeeId, int $memberId): array
    {
        $result = $this->chatService->removeMember($roomId, $employeeId, $memberId);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'remove_member_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'data' => $result];
    }

    public function searchMessages(int $roomId, int $employeeId, string $query): array
    {
        $result = $this->chatService->searchMessages($roomId, $employeeId, $query);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'search_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'data' => ['messages' => $result['messages'] ?? []]];
    }

    public function onlineUsers(): array
    {
        return ['success' => true, 'data' => ['users' => $this->chatService->getOnlineUsers()]];
    }

    public function createPrivateRoom(int $employeeId, int $otherEmployeeId): array
    {
        $result = $this->chatService->getOrCreatePrivateRoom($employeeId, $otherEmployeeId);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'private_room_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'status' => 201, 'data' => $result];
    }

    public function createGroupRoom(int $employeeId, string $name, array $members): array
    {
        $result = $this->chatService->createGroupRoom($employeeId, $name, $members);
        if (!$result['success']) {
            return ['success' => false, 'status' => 400, 'code' => 'group_room_failed', 'message' => $result['message']];
        }

        return ['success' => true, 'status' => 201, 'data' => $result];
    }
}
