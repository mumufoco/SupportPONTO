<?php

namespace App\Controllers;

use App\Services\ChatWebWorkflowService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Chat Controller
 *
 * Thin HTTP layer for chat web module.
 */
class ChatController extends BaseController
{
    protected ?ChatWebWorkflowService $chatWorkflowService = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        try {
            $this->chatWorkflowService = new ChatWebWorkflowService();
        } catch (\Throwable $e) {
            log_message('error', 'ChatController: failed to init ChatWebWorkflowService: ' . $e->getMessage());
            $this->chatWorkflowService = null;
        }
    }

    public function index()
    {
        if ($this->chatWorkflowService === null) {
            return view('chat/unavailable');
        }
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }
        return view('chat/index', $this->chatWorkflowService->indexData($employee));
    }

    public function room($roomId)
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }

        $result = $this->chatWorkflowService->roomData((int) $roomId, $employee);
        if (!$result['success']) {
            return $this->redirectChatIndex($result['message'] ?? 'Não foi possível abrir a sala de chat.');
        }

        return view('chat/room', $result['data']);
    }

    public function newChat($employeeId)
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }

        $result = $this->chatWorkflowService->createPrivateRoom((int) $employee['id'], (int) $employeeId);
        if (!$result['success']) {
            return $this->redirectChatIndex($result['message'] ?? 'Não foi possível iniciar a conversa.');
        }

        return redirect()->to(route_to('chat.room', (int) $result['room']->id));
    }

    public function createGroup()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }

        return view('chat/create_group', $this->chatWorkflowService->createGroupData($employee));
    }

    public function storeGroup()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }

        $rules = [
            'name' => 'required|min_length[3]|max_length[255]',
            'members' => 'required',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $members = $this->request->getPost('members');
        if (!is_array($members)) {
            $members = explode(',', (string) $members);
        }

        $result = $this->chatWorkflowService->createGroup((int) $employee['id'], (string) $this->request->getPost('name'), $members);
        if (!$result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message'] ?? 'Não foi possível criar o grupo.');
        }

        return redirect()->to(route_to('chat.room', (int) $result['room']->id))->with('success', 'Grupo criado com sucesso!');
    }

    public function roomSettings($roomId)
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }

        $result = $this->chatWorkflowService->roomSettingsData((int) $roomId, $employee);
        if (!$result['success']) {
            return $this->redirectChatIndex($result['message'] ?? 'Não foi possível carregar as configurações da sala.');
        }

        return view('chat/settings', $result['data']);
    }

    public function addMember($roomId)
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        $newMemberId = (int) $this->request->getPost('employee_id');
        if ($newMemberId <= 0) {
            return $this->jsonFail('ID do funcionário é obrigatório.', 422);
        }

        return $this->jsonFromResult(
            $this->chatWorkflowService->addMember((int) $roomId, (int) $employee['id'], $newMemberId)
        );
    }

    public function removeMember($roomId)
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        $memberToRemove = (int) $this->request->getPost('employee_id');
        if ($memberToRemove <= 0) {
            return $this->jsonFail('ID do funcionário é obrigatório.', 422);
        }

        return $this->jsonFromResult(
            $this->chatWorkflowService->removeMember((int) $roomId, (int) $employee['id'], $memberToRemove)
        );
    }

    public function search($roomId)
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        $query = trim((string) $this->request->getGet('q'));
        if ($query === '') {
            return $this->jsonFail('Query de busca é obrigatória.', 422);
        }

        return $this->jsonFromResult(
            $this->chatWorkflowService->searchMessages((int) $roomId, (int) $employee['id'], $query)
        );
    }

    public function uploadFile()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        return $this->jsonFromResult(
            $this->chatWorkflowService->uploadChatFile($employee, $this->request->getFile('file'))
        );
    }

    public function downloadFile()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->redirectLogin();
        }

        $filePath = (string) $this->request->getGet('path');
        $resolved = $this->chatWorkflowService->resolveDownload($filePath, (int) $employee['id']);

        if (!$resolved) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return $this->response->download($resolved['path'], null)->setFileName($resolved['name']);
    }

    public function getVapidKey()
    {
        $publicKey = trim($this->chatWorkflowService->getVapidKey());
        if ($publicKey === '') {
            return $this->response->setStatusCode(503)->setJSON([
                'success' => false,
                'message' => 'Chaves VAPID não configuradas.',
            ]);
        }

        return $this->response->setJSON([
            'success' => true,
            'publicKey' => $publicKey,
        ]);
    }

    public function subscribePush()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        $subscription = $this->request->getJSON(true) ?? [];
        return $this->jsonFromResult($this->chatWorkflowService->subscribePush((int) $employee['id'], $subscription));
    }

    public function unsubscribePush()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        $data = $this->request->getJSON(true) ?? [];
        return $this->jsonFromResult(
            $this->chatWorkflowService->unsubscribePush((int) $employee['id'], (string) ($data['endpoint'] ?? ''))
        );
    }

    public function testPush()
    {
        $employee = $this->requireEmployeeSession();
        if (!$employee) {
            return $this->jsonFail('Não autenticado.', 401);
        }

        return $this->jsonFromResult($this->chatWorkflowService->testPush((int) $employee['id']));
    }

    private function requireEmployeeSession(): ?array
    {
        if ($this->chatWorkflowService === null) {
            return null;
        }
        return $this->chatWorkflowService->authenticatedEmployeeBySession();
    }

    private function redirectLogin()
    {
        return redirect()->to(route_to('login'))->with('error', 'Você precisa estar autenticado.');
    }

    private function redirectChatIndex(string $message)
    {
        return redirect()->to(route_to('chat'))->with('error', $message);
    }

    private function jsonFail(string $message, int $statusCode = 400)
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }

    private function jsonFromResult(array $result, int $successStatus = 200)
    {
        $statusCode = ($result['success'] ?? false)
            ? $successStatus
            : (int) ($result['status'] ?? $this->inferStatusCode($result['message'] ?? null));

        return $this->response->setStatusCode($statusCode)->setJSON($result);
    }

    private function inferStatusCode(?string $message): int
    {
        $message = mb_strtolower(trim((string) $message));

        if ($message === '') {
            return 400;
        }

        return match (true) {
            str_contains($message, 'não autenticado') => 401,
            str_contains($message, 'não autorizado'), str_contains($message, 'sem permissão'), str_contains($message, 'não tem permissão') => 403,
            str_contains($message, 'não encontrada'), str_contains($message, 'não encontrado') => 404,
            str_contains($message, 'obrigatória'), str_contains($message, 'obrigatório'), str_contains($message, 'inválido'), str_contains($message, 'inválida') => 422,
            default => 400,
        };
    }
}
