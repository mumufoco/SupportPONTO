<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ChatMessage Model
 *
 * Manages chat messages in rooms
 */
class ChatMessageModel extends Model
{
    protected $table            = 'chat_messages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'room_id',
        'sender_id',
        'message',
        'type',
        'file_path',
        'file_name',
        'file_size',
        'reply_to',
        'edited_at',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = null;
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'room_id'   => 'required|integer',
        'sender_id' => 'required|integer',
        'message'   => 'required|max_length[5000]',
        'type'      => 'permit_empty|in_list[text,file,image,system]',
    ];

    protected $validationMessages = [
        'message' => [
            'required'   => 'A mensagem é obrigatória.',
            'max_length' => 'A mensagem não pode ter mais de 5000 caracteres.',
        ],
    ];

    protected $skipValidation = false;

    /**
     * Get messages for a room
     *
     * @param int    $roomId
     * @param int    $limit
     * @param int    $offset
     * @param string $beforeId Optional message ID to load messages before
     * @return array
     */
    public function getRoomMessages(int $roomId, int $limit = 50, int $offset = 0, ?string $beforeId = null): array
    {
        $query = $this->select('chat_messages.*,
                               employees.name as sender_name,
                               employees.email as sender_email,
                               reply_msg.message as reply_message,
                               reply_sender.name as reply_sender_name')
            ->join('employees', 'employees.id = chat_messages.sender_id', 'left')
            ->join('chat_messages as reply_msg', 'reply_msg.id = chat_messages.reply_to', 'left')
            ->join('employees as reply_sender', 'reply_sender.id = reply_msg.sender_id', 'left')
            ->where('chat_messages.room_id', $roomId);

        if ($beforeId) {
            $query->where('chat_messages.id <', $beforeId);
        }

        return $query->orderBy('chat_messages.created_at', 'DESC')
            ->limit($limit, $offset)
            ->findAll();
    }

    /**
     * Get message with reactions
     *
     * @param int $messageId
     * @return object|null
     */
    public function getMessageWithReactions(int $messageId): ?object
    {
        $message = $this->find($messageId);

        if (!$message) {
            return null;
        }

        $reactionModel = new \App\Models\ChatMessageReactionModel();
        $message->reactions = $reactionModel->getMessageReactions($messageId);

        return $message;
    }

    /**
     * Search messages in room
     *
     * @param int    $roomId
     * @param string $query
     * @param int    $limit
     * @return array
     */
    public function searchMessages(int $roomId, string $query, int $limit = 20): array
    {
        return $this->select('chat_messages.*, employees.name as sender_name')
            ->join('employees', 'employees.id = chat_messages.sender_id', 'left')
            ->where('chat_messages.room_id', $roomId)
            ->like('chat_messages.message', $query)
            ->orderBy('chat_messages.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Get message count for room
     *
     * @param int $roomId
     * @return int
     */
    public function getMessageCount(int $roomId): int
    {
        return $this->where('room_id', $roomId)->countAllResults();
    }

    /**
     * Update message (mark as edited)
     *
     * @param int    $messageId
     * @param string $newMessage
     * @return bool
     */
    public function editMessage(int $messageId, string $newMessage): bool
    {
        return $this->update($messageId, [
            'message'   => $newMessage,
            'edited_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete old messages (cleanup)
     *
     * @param int $days
     * @return int Number of deleted messages
     */
    public function deleteOldMessages(int $days = 90): int
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $this->where('created_at <', $cutoffDate)->delete();
    }
}
