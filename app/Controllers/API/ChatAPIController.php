<?php

namespace App\Controllers\API;

use App\Services\Chat\ChatApiControllerActionService;
use App\Services\ChatApiWorkflowService;
use CodeIgniter\HTTP\ResponseInterface;

class ChatAPIController extends BaseApiController
{

    protected ChatApiWorkflowService $chatApiWorkflowService;
    protected ChatApiControllerActionService $chatApiControllerActionService;

    public function __construct()
    {
        parent::__construct();
        $this->chatApiWorkflowService = new ChatApiWorkflowService();
        $this->chatApiControllerActionService = new ChatApiControllerActionService();
    }

    public function getRooms(): ResponseInterface
    {
        $employee = $this->authenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $result = $this->chatApiWorkflowService->rooms((int) $employee->id);
        return $this->respondStandard($result['data'] ?? [], 'Salas carregadas com sucesso.', 200, 'chat_rooms_success');
    }

    public function getMessages($roomId = null): ResponseInterface
    {
        return $this->roomAction($roomId, function (object $employee, int $roomId): array {
            return $this->chatApiWorkflowService->messages(
                $roomId,
                (int) $employee->id,
                (int) ($this->requestValue('limit', 50)),
                (int) ($this->requestValue('offset', 0))
            );
        }, 'Mensagens carregadas com sucesso.', 'chat_messages_success');
    }

    public function sendMessage($roomId = null): ResponseInterface
    {
        if (!$this->validate($this->chatApiControllerActionService->sendMessageRules())) {
            return $this->failValidationErrors($this->validator->getErrors(), 'validation_error', 'Dados da mensagem inválidos.');
        }

        return $this->roomAction($roomId, function (object $employee, int $roomId): array {
            return $this->chatApiWorkflowService->sendMessage(
                $roomId,
                (int) $employee->id,
                (string) $this->requestValue('message'),
                $this->chatApiControllerActionService->replyTo($this->request)
            );
        }, 'Mensagem enviada com sucesso.', 'chat_send_success', 201);
    }

    public function editMessage($messageId = null): ResponseInterface
    {
        if (!$this->validate($this->chatApiControllerActionService->editMessageRules())) {
            return $this->failValidationErrors($this->validator->getErrors(), 'validation_error', 'Dados da mensagem inválidos.');
        }

        $message = trim((string) $this->requestValue('message'));
        if ($message === '') {
            return $this->failStandard('validation_error', 'Mensagem é obrigatória.', 400);
        }

        return $this->messageAction($messageId, function (object $employee, int $messageId) use ($message): array {
            return $this->chatApiWorkflowService->editMessage($messageId, (int) $employee->id, $message);
        }, 'Mensagem editada com sucesso.', 'chat_edit_success');
    }

    public function deleteMessage($messageId = null): ResponseInterface
    {
        return $this->messageAction($messageId, function (object $employee, int $messageId): array {
            return $this->chatApiWorkflowService->deleteMessage($messageId, (int) $employee->id);
        }, 'Mensagem excluída com sucesso.', 'chat_delete_success');
    }

    public function addReaction($messageId = null): ResponseInterface
    {
        $emoji = (string) $this->requestValue('emoji');
        if ($emoji === '') {
            return $this->failStandard('validation_error', 'Emoji é obrigatório.', 400);
        }

        return $this->messageAction($messageId, function (object $employee, int $messageId) use ($emoji): array {
            return $this->chatApiWorkflowService->addReaction($messageId, (int) $employee->id, $emoji);
        }, 'Reação processada.', 'chat_reaction_success');
    }

    public function markAsRead($roomId = null): ResponseInterface
    {
        return $this->roomAction($roomId, fn (object $employee, int $roomId): array => $this->chatApiWorkflowService->markAsRead($roomId, (int) $employee->id), 'Leitura atualizada.', 'chat_read_success');
    }

    public function getMembers($roomId = null): ResponseInterface
    {
        return $this->roomAction($roomId, fn (object $employee, int $roomId): array => $this->chatApiWorkflowService->members($roomId, (int) $employee->id), 'Membros carregados com sucesso.', 'chat_members_success');
    }

    public function addMember($roomId = null): ResponseInterface
    {
        $newMemberId = (int) $this->requestValue('employee_id');
        if ($newMemberId <= 0) {
            return $this->failStandard('validation_error', 'ID do colaborador é obrigatório.', 400);
        }

        return $this->roomAction($roomId, fn (object $employee, int $roomId): array => $this->chatApiWorkflowService->addMember($roomId, (int) $employee->id, $newMemberId), 'Membro adicionado com sucesso.', 'chat_add_member_success', 201);
    }

    public function removeMember($roomId = null, $memberId = null): ResponseInterface
    {
        $member = $this->chatApiControllerActionService->requireIdOrNull($memberId);
        if ($member === null) {
            return $this->failStandard('validation_error', 'IDs de sala e membro são obrigatórios.', 400);
        }

        return $this->roomAction($roomId, fn (object $employee, int $roomId): array => $this->chatApiWorkflowService->removeMember($roomId, (int) $employee->id, $member), 'Membro removido com sucesso.', 'chat_remove_member_success');
    }

    public function searchMessages($roomId = null): ResponseInterface
    {
        $query = (string) $this->requestValue('q');
        if ($query === '') {
            return $this->failStandard('validation_error', 'Query de busca é obrigatória.', 400);
        }

        return $this->roomAction($roomId, fn (object $employee, int $roomId): array => $this->chatApiWorkflowService->searchMessages($roomId, (int) $employee->id, $query), 'Busca realizada com sucesso.', 'chat_search_success');
    }

    public function getOnlineUsers(): ResponseInterface
    {
        $employee = $this->authenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $result = $this->chatApiWorkflowService->onlineUsers();
        return $this->respondStandard($result['data'] ?? [], 'Usuários online carregados.', 200, 'chat_online_success');
    }

    public function createPrivateRoom(): ResponseInterface
    {
        $employee = $this->authenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $otherEmployeeId = (int) $this->requestValue('employee_id');
        if ($otherEmployeeId <= 0) {
            return $this->failStandard('validation_error', 'ID do colaborador é obrigatório.', 400);
        }

        return $this->respondWorkflowResult(
            $this->chatApiWorkflowService->createPrivateRoom((int) $employee->id, $otherEmployeeId),
            'Sala privada pronta.',
            'chat_private_room_success',
            201
        );
    }

    public function createGroupRoom(): ResponseInterface
    {
        $employee = $this->authenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        if (!$this->validate($this->chatApiControllerActionService->createGroupRules())) {
            return $this->failValidationErrors($this->validator->getErrors(), 'validation_error', 'Dados do grupo inválidos.');
        }

        return $this->respondWorkflowResult(
            $this->chatApiWorkflowService->createGroupRoom(
                (int) $employee->id,
                (string) $this->requestValue('name'),
                $this->chatApiControllerActionService->members($this->request)
            ),
            'Grupo criado com sucesso.',
            'chat_group_room_success',
            201
        );
    }

    private function authenticatedEmployee()
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee) {
            return $this->failStandard('unauthorized', 'Não autenticado.', 401);
        }

        return $employee;
    }

    private function roomAction($roomId, callable $action, string $successMessage, string $successCode, int $defaultStatus = 200): ResponseInterface
    {
        $employee = $this->authenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $room = $this->chatApiControllerActionService->requireIdOrNull($roomId);
        if ($room === null) {
            return $this->failStandard('validation_error', 'ID da sala é obrigatório.', 400);
        }

        return $this->respondWorkflowResult($action($employee, $room), $successMessage, $successCode, $defaultStatus);
    }

    private function messageAction($messageId, callable $action, string $successMessage, string $successCode, int $defaultStatus = 200): ResponseInterface
    {
        $employee = $this->authenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $message = $this->chatApiControllerActionService->requireIdOrNull($messageId);
        if ($message === null) {
            return $this->failStandard('validation_error', 'ID da mensagem é obrigatório.', 400);
        }

        return $this->respondWorkflowResult($action($employee, $message), $successMessage, $successCode, $defaultStatus);
    }

    private function respondWorkflowResult(array $result, string $successMessage, string $successCode, int $defaultStatus = 200): ResponseInterface
    {
        if (!($result['success'] ?? false)) {
            return $this->failStandard($result['code'] ?? 'chat_action_failed', $result['message'] ?? 'Operação falhou.', (int) ($result['status'] ?? 400));
        }

        return $this->respondStandard(
            $result['data'] ?? [],
            $successMessage,
            (int) ($result['status'] ?? $defaultStatus),
            $successCode
        );
    }
}
