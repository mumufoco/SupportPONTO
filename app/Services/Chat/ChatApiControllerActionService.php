<?php

namespace App\Services\Chat;

use CodeIgniter\HTTP\IncomingRequest;

class ChatApiControllerActionService
{
    public function requireIdOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function sendMessageRules(): array
    {
        return ['message' => 'required|max_length[5000]', 'reply_to' => 'permit_empty|integer'];
    }

    public function editMessageRules(): array
    {
        return ['message' => 'required|max_length[5000]'];
    }

    public function createGroupRules(): array
    {
        return ['name' => 'required|min_length[3]|max_length[255]', 'members' => 'required'];
    }

    public function replyTo(IncomingRequest $request): ?int
    {
        $value = $request->getPost('reply_to') ?? $request->getJSON(true)['reply_to'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function members(IncomingRequest $request): array
    {
        $members = $request->getPost('members') ?? $request->getJSON(true)['members'] ?? [];
        if (!is_array($members)) {
            $members = explode(',', (string) $members);
        }

        return array_values(array_filter(array_map('intval', $members), static fn (int $id): bool => $id > 0));
    }
}
