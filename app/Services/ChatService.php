<?php

namespace App\Services;

use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatPresenceService;
use App\Services\Chat\ChatRoomManagementService;

/**
 * Chat Service
 *
 * Fachada para casos de uso de chat, delegando para serviços especializados.
 */
class ChatService
{
    protected ChatRoomManagementService $roomService;
    protected ChatMessageService $messageService;
    protected ChatPresenceService $presenceService;

    public function __construct()
    {
        $this->roomService = new ChatRoomManagementService();
        $this->messageService = new ChatMessageService();
        $this->presenceService = new ChatPresenceService();
    }

    public function getOrCreatePrivateRoom(int $employee1Id, int $employee2Id): array
    {
        return $this->roomService->getOrCreatePrivateRoom($employee1Id, $employee2Id);
    }

    public function createGroupRoom(int $creatorId, string $name, array $memberIds): array
    {
        return $this->roomService->createGroupRoom($creatorId, $name, $memberIds);
    }

    public function getEmployeeRooms(int $employeeId): array
    {
        return $this->roomService->getEmployeeRooms($employeeId);
    }

    public function getRoomMessages(int $roomId, int $employeeId, int $limit = 50, int $offset = 0): array
    {
        return $this->messageService->getRoomMessages($roomId, $employeeId, $limit, $offset);
    }

    public function sendMessage(int $roomId, int $senderId, string $message, ?int $replyTo = null): array
    {
        return $this->messageService->sendMessage($roomId, $senderId, $message, $replyTo);
    }

    public function sendFileMessage(
        int $roomId,
        int $senderId,
        string $caption,
        string $filePath,
        string $fileName,
        int $fileSize,
        string $fileType,
        ?int $replyTo = null
    ): array {
        return $this->messageService->sendFileMessage(
            $roomId,
            $senderId,
            $caption,
            $filePath,
            $fileName,
            $fileSize,
            $fileType,
            $replyTo
        );
    }

    public function markAsRead(int $roomId, int $employeeId): bool
    {
        return $this->messageService->markAsRead($roomId, $employeeId);
    }

    public function addReaction(int $messageId, int $employeeId, string $emoji): bool
    {
        return $this->messageService->addReaction($messageId, $employeeId, $emoji);
    }

    public function editMessage(int $messageId, int $employeeId, string $newMessage): array
    {
        return $this->messageService->editMessage($messageId, $employeeId, $newMessage);
    }

    public function deleteMessage(int $messageId, int $employeeId): array
    {
        return $this->messageService->deleteMessage($messageId, $employeeId);
    }

    public function searchMessages(int $roomId, int $employeeId, string $query): array
    {
        return $this->messageService->searchMessages($roomId, $employeeId, $query);
    }

    public function getOnlineUsers(): array
    {
        return $this->presenceService->getOnlineUsers();
    }

    public function getRoomMembers(int $roomId, int $employeeId): array
    {
        return $this->presenceService->getRoomMembers($roomId, $employeeId);
    }

    public function addMember(int $roomId, int $employeeId, int $newMemberId): array
    {
        return $this->roomService->addMember($roomId, $employeeId, $newMemberId);
    }

    public function removeMember(int $roomId, int $employeeId, int $memberToRemove): array
    {
        return $this->roomService->removeMember($roomId, $employeeId, $memberToRemove);
    }
}
