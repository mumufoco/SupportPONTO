<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ChatMessageReaction Model
 *
 * Manages emoji reactions to messages
 */
class ChatMessageReactionModel extends Model
{
    protected $table            = 'chat_message_reactions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'message_id',
        'employee_id',
        'emoji',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = null;

    // Validation
    protected $validationRules = [
        'message_id'  => 'required|integer',
        'employee_id' => 'required|integer',
        'emoji'       => 'required|max_length[10]',
    ];

    protected $validationMessages = [];
    protected $skipValidation     = false;

    /**
     * Get reactions for a message
     *
     * @param int $messageId
     * @return array
     */
    public function getMessageReactions(int $messageId): array
    {
        return $this->select('chat_message_reactions.*, employees.name')
            ->join('employees', 'employees.id = chat_message_reactions.employee_id', 'left')
            ->where('message_id', $messageId)
            ->findAll();
    }

    /**
     * Get reaction summary for a message
     *
     * @param int $messageId
     * @return array
     */
    public function getReactionSummary(int $messageId): array
    {
        $reactions = $this->select('emoji, COUNT(*) as count')
            ->where('message_id', $messageId)
            ->groupBy('emoji')
            ->findAll();

        $summary = [];

        foreach ($reactions as $reaction) {
            $summary[$reaction->emoji] = $reaction->count;
        }

        return $summary;
    }

    /**
     * Get reaction summaries for multiple messages in a single query.
     *
     * Eliminates N+1 when loading reactions for a list of messages
     * (getRoomMessages was calling getReactionSummary once per message).
     *
     * @param int[] $messageIds
     * @return array<int,array<string,int>>  map of message_id => [emoji => count]
     */
    public function getReactionSummariesBatch(array $messageIds): array
    {
        if (empty($messageIds)) {
            return [];
        }

        $reactions = $this->select('message_id, emoji, COUNT(*) as count', false)
            ->whereIn('message_id', $messageIds)
            ->groupBy('message_id, emoji')
            ->findAll();

        $summaries = [];
        foreach ($reactions as $reaction) {
            $summaries[(int) $reaction->message_id][$reaction->emoji] = (int) $reaction->count;
        }

        return $summaries;
    }

    /**
     * Toggle reaction (add if not exists, remove if exists)
     *
     * @param int    $messageId
     * @param int    $employeeId
     * @param string $emoji
     * @return bool
     */
    public function toggleReaction(int $messageId, int $employeeId, string $emoji): bool
    {
        $existing = $this->where('message_id', $messageId)
            ->where('employee_id', $employeeId)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            return $this->delete($existing->id);
        }

        return (bool) $this->insert([
            'message_id'  => $messageId,
            'employee_id' => $employeeId,
            'emoji'       => $emoji,
        ]);
    }
}
