<?php

namespace App\Services;

use App\Models\ChatRoomMemberModel;
use App\Models\ChatRoomModel;
use App\Models\EmployeeModel;

class ChatWebWorkflowService
{
    private ChatService $chatService;
    private PushNotificationService $pushService;
    private EmployeeModel $employeeModel;
    private ChatRoomModel $chatRoomModel;
    private ChatRoomMemberModel $chatRoomMemberModel;
    private ChatWebSocketAuthService $chatWebSocketAuthService;

    public function __construct()
    {
        $this->chatService = new ChatService();
        $this->pushService = new PushNotificationService();
        $this->employeeModel = new EmployeeModel();
        $this->chatRoomModel = new ChatRoomModel();
        $this->chatRoomMemberModel = new ChatRoomMemberModel();
        $this->chatWebSocketAuthService = new ChatWebSocketAuthService();
    }

    public function indexData(array $employee): array
    {
        return [
            'title' => 'Chat',
            'employee' => $employee,
            'rooms' => $this->chatService->getEmployeeRooms((int) $employee['id']),
            'onlineUsers' => $this->chatService->getOnlineUsers(),
            'websocketToken' => $this->chatWebSocketAuthService->issueBrowserToken($employee),
            'employees' => $this->employeeModel
                ->where('active', true)
                ->where('id !=', (int) $employee['id'])
                ->orderBy('name', 'ASC')
                ->findAll(),
        ];
    }

    public function roomData(int $roomId, array $employee): array
    {
        $messagesResult = $this->chatService->getRoomMessages($roomId, (int) $employee['id'], 50, 0);
        if (!$messagesResult['success']) {
            return ['success' => false, 'message' => $messagesResult['message'] ?? 'Erro ao abrir sala.'];
        }

        $membersResult = $this->chatService->getRoomMembers($roomId, (int) $employee['id']);

        $roomContext = $this->buildRoomContext($roomId, (int) $employee['id']);
        if (!$roomContext['success']) {
            return $roomContext;
        }

        return [
            'success' => true,
            'data' => [
                'title' => 'Chat - Sala',
                'employee' => $employee,
                'roomId' => $roomId,
                'room' => $roomContext['room'],
                'isAdmin' => $roomContext['isAdmin'],
                'messages' => $messagesResult['messages'],
                'members' => $membersResult['members'] ?? [],
                'websocketToken' => $this->chatWebSocketAuthService->issueBrowserToken($employee),
            ],
        ];
    }

    public function createPrivateRoom(int $employeeId, int $targetEmployeeId): array
    {
        if ($employeeId <= 0 || $targetEmployeeId <= 0) {
            return ['success' => false, 'message' => 'Colaborador inválido.', 'status' => 422];
        }

        if ($employeeId === $targetEmployeeId) {
            return ['success' => false, 'message' => 'Não é possível iniciar uma conversa com você mesmo.', 'status' => 422];
        }

        return $this->chatService->getOrCreatePrivateRoom($employeeId, $targetEmployeeId);
    }

    public function createGroupData(array $employee): array
    {
        return [
            'title' => 'Criar Grupo',
            'employee' => $employee,
            'employees' => $this->employeeModel
                ->where('active', true)
                ->where('id !=', (int) $employee['id'])
                ->orderBy('name', 'ASC')
                ->findAll(),
        ];
    }

    public function createGroup(int $employeeId, string $name, array $members): array
    {
        $name = trim($name);
        $members = array_values(array_unique(array_filter(array_map(static fn ($member): int => (int) $member, $members), static fn (int $member): bool => $member > 0)));

        if ($name === '') {
            return ['success' => false, 'message' => 'Nome do grupo é obrigatório.', 'status' => 422];
        }

        return $this->chatService->createGroupRoom($employeeId, $name, $members);
    }

    public function roomSettingsData(int $roomId, array $employee): array
    {
        $membersResult = $this->chatService->getRoomMembers($roomId, (int) $employee['id']);
        if (!$membersResult['success']) {
            return ['success' => false, 'message' => $membersResult['message'] ?? 'Erro ao carregar sala.'];
        }

        $roomContext = $this->buildRoomContext($roomId, (int) $employee['id']);
        if (!$roomContext['success']) {
            return $roomContext;
        }

        return [
            'success' => true,
            'data' => [
                'title' => 'Configurações da Sala',
                'employee' => $employee,
                'roomId' => $roomId,
                'room' => $roomContext['room'],
                'isAdmin' => $roomContext['isAdmin'],
                'members' => $membersResult['members'],
                'employees' => $this->employeeModel
                    ->where('active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll(),
            ],
        ];
    }

    public function addMember(int $roomId, int $employeeId, int $newMemberId): array
    {
        return $this->chatService->addMember($roomId, $employeeId, $newMemberId);
    }

    public function removeMember(int $roomId, int $employeeId, int $memberToRemove): array
    {
        return $this->chatService->removeMember($roomId, $employeeId, $memberToRemove);
    }

    public function searchMessages(int $roomId, int $employeeId, string $query): array
    {
        return $this->chatService->searchMessages($roomId, $employeeId, $query);
    }

    public function uploadChatFile(array $employee, mixed $file): array
    {
        helper('file_upload');
        if (!$file) {
            return ['success' => false, 'message' => 'Nenhum arquivo foi enviado.'];
        }

        return upload_chat_file($file, (int) $employee['id']);
    }

    public function resolveDownload(string $filePath, int $employeeId): ?array
    {
        helper('file_upload');

        if ($filePath === '' || !validate_file_access($filePath, $employeeId)) {
            return null;
        }

        $baseDir = realpath(WRITEPATH . 'uploads/chat') ?: WRITEPATH . 'uploads/chat';
        $fullPath = supportponto_safe_download_path(WRITEPATH . $filePath, [$baseDir]);

        if ($fullPath === null) {
            log_message('warning', "[FileDownload] Download bloqueado para path inválido: {$filePath} por employee {$employeeId}");
            return null;
        }

        return ['path' => $fullPath, 'name' => basename($fullPath)];
    }

    public function getVapidKey(): string
    {
        return $this->pushService->getPublicKey();
    }

    public function subscribePush(int $employeeId, array $subscription): array
    {
        if (empty($subscription)) {
            return ['success' => false, 'message' => 'Dados de inscrição inválidos.'];
        }

        return $this->pushService->subscribe($employeeId, $subscription);
    }

    public function unsubscribePush(int $employeeId, string $endpoint): array
    {
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'Endpoint é obrigatório.'];
        }

        return $this->pushService->unsubscribe($employeeId, $endpoint);
    }

    public function testPush(int $employeeId): array
    {
        return $this->pushService->sendTestNotification($employeeId);
    }

    public function authenticatedEmployeeBySession(): ?array
    {
        helper('session_context');
        $employeeId = sp_session_user_id();
        if (!$employeeId) {
            return null;
        }

        $employee = $this->employeeModel->find($employeeId);
        return $employee ? (array) $employee : null;
    }

    private function buildRoomContext(int $roomId, int $employeeId): array
    {
        $room = $this->chatRoomModel->find($roomId);
        if (!$room) {
            return ['success' => false, 'message' => 'Sala não encontrada.', 'status' => 404];
        }

        $member = $this->chatRoomMemberModel
            ->where('room_id', $roomId)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$member) {
            return ['success' => false, 'message' => 'Você não é membro desta sala.', 'status' => 403];
        }

        $roomData = (array) $room;
        $roomData['member_count'] = $this->chatRoomMemberModel->where('room_id', $roomId)->countAllResults();
        $roomData['message_count'] = max(0, (int) ($roomData['message_count'] ?? 0));
        $roomData['is_group'] = ($roomData['type'] ?? null) === 'group';

        return [
            'success' => true,
            'room' => $roomData,
            'isAdmin' => (($member->role ?? 'member') === 'admin') || (int) ($roomData['created_by'] ?? 0) === $employeeId,
        ];
    }

}
