<?php

namespace App\Services\Chat;

use App\Models\ChatMessageModel;
use App\Models\ChatMessageReactionModel;
use App\Models\ChatRoomMemberModel;
use App\Models\EmployeeModel;

class ChatMessageService
{
    public function __construct(
        private readonly ChatRoomMemberModel $memberModel = new ChatRoomMemberModel(),
        private readonly ChatMessageModel $messageModel = new ChatMessageModel(),
        private readonly ChatMessageReactionModel $reactionModel = new ChatMessageReactionModel(),
        private readonly EmployeeModel $employeeModel = new EmployeeModel(),
    ) {
    }

    public function getRoomMessages(int $roomId, int $employeeId, int $limit = 50, int $offset = 0): array
    {
        if (!$this->memberModel->isMember($roomId, $employeeId)) {
            return ['success' => false, 'message' => 'Você não é membro desta sala.'];
        }

        $messages = $this->messageModel->getRoomMessages($roomId, $limit, $offset);

        if (!empty($messages)) {
            // Batch fetch all reaction summaries in one query — eliminates N+1 (was 1 query per message)
            $messageIds = array_column((array) $messages, 'id');
            $reactionSummaries = $this->reactionModel->getReactionSummariesBatch($messageIds);

            foreach ($messages as &$message) {
                $message->reactions = $reactionSummaries[$message->id] ?? [];
            }
        }

        return ['success' => true, 'messages' => array_reverse($messages)];
    }

    public function sendMessage(int $roomId, int $senderId, string $message, ?int $replyTo = null): array
    {
        if (!$this->memberModel->isMember($roomId, $senderId)) {
            return ['success' => false, 'message' => 'Você não é membro desta sala.'];
        }

        $messageId = $this->messageModel->insert([
            'room_id' => $roomId,
            'sender_id' => $senderId,
            'message' => $message,
            'type' => 'text',
            'reply_to' => $replyTo,
        ]);

        if (!$messageId) {
            return ['success' => false, 'message' => 'Erro ao enviar mensagem.'];
        }

        return ['success' => true, 'message' => $this->messageModel->find($messageId)];
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
        if (!$this->memberModel->isMember($roomId, $senderId)) {
            return ['success' => false, 'message' => 'Você não é membro desta sala.'];
        }

        $messageId = $this->messageModel->insert([
            'room_id' => $roomId,
            'sender_id' => $senderId,
            'message' => $caption ?: 'Arquivo enviado',
            'type' => $fileType,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'reply_to' => $replyTo,
        ]);

        if (!$messageId) {
            return ['success' => false, 'message' => 'Erro ao enviar arquivo.'];
        }

        return ['success' => true, 'message' => $this->messageModel->find($messageId)];
    }

    public function markAsRead(int $roomId, int $employeeId): bool
    {
        return $this->memberModel->markAsRead($roomId, $employeeId);
    }

    public function addReaction(int $messageId, int $employeeId, string $emoji): bool
    {
        return $this->reactionModel->toggleReaction($messageId, $employeeId, $emoji);
    }

    public function editMessage(int $messageId, int $employeeId, string $newMessage): array
    {
        $message = $this->messageModel->find($messageId);
        if (!$message) {
            return ['success' => false, 'message' => 'Mensagem não encontrada.'];
        }

        if ($message->sender_id !== $employeeId) {
            return ['success' => false, 'message' => 'Você não pode editar esta mensagem.'];
        }

        if ((time() - strtotime($message->created_at)) > 900) {
            return [
                'success' => false,
                'message' => 'Você só pode editar mensagens enviadas nos últimos 15 minutos.',
            ];
        }

        $this->messageModel->editMessage($messageId, $newMessage);

        return ['success' => true, 'message' => 'Mensagem editada com sucesso.'];
    }

    public function deleteMessage(int $messageId, int $employeeId): array
    {
        $message = $this->messageModel->find($messageId);
        if (!$message) {
            return ['success' => false, 'message' => 'Mensagem não encontrada.'];
        }

        $employee = $this->employeeModel->find($employeeId);
        if ($message->sender_id !== $employeeId && $employee->role !== 'admin') {
            return ['success' => false, 'message' => 'Você não pode excluir esta mensagem.'];
        }

        $this->messageModel->delete($messageId);

        return ['success' => true, 'message' => 'Mensagem excluída com sucesso.'];
    }

    public function searchMessages(int $roomId, int $employeeId, string $query): array
    {
        if (!$this->memberModel->isMember($roomId, $employeeId)) {
            return ['success' => false, 'message' => 'Você não é membro desta sala.'];
        }

        return [
            'success' => true,
            'messages' => $this->messageModel->searchMessages($roomId, $query),
        ];
    }
}
