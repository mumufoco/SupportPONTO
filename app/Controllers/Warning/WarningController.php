<?php

namespace App\Controllers\Warning;

use App\Controllers\BaseController;
use App\Models\WarningModel;
use App\Services\Warning\WarningAccessService;
use App\Services\Warning\WarningControllerActionService;
use App\Services\Warning\WarningQueryService;
use App\Services\Warning\WarningWorkflowService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;

class WarningController extends BaseController
{
    protected WarningModel $warningModel;
    protected WarningAccessService $accessService;
    protected WarningQueryService $queryService;
    protected WarningWorkflowService $workflowService;
    protected WarningControllerActionService $controllerActionService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->warningModel = Services::warningModel();
        $this->accessService = Services::warningAccessService();
        $this->queryService = Services::warningQueryService();
        $this->workflowService = Services::warningWorkflowService();
        $this->controllerActionService = Services::warningControllerActionService();

        helper(['form', 'datetime', 'format', 'operational_link']);
    }

    public function index()
    {
        $employee = $this->requireManagerEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $warningType = $this->request->getGet('warning_type') ?? 'all';
        $status = $this->request->getGet('status') ?? 'all';
        $result = $this->queryService->listWarnings($employee, (string) $warningType, (string) $status);

        return view('warnings/index', [
            'employee' => $employee,
            'warnings' => $result['warnings'],
            'pager' => $result['pager'],
            'warningType' => $warningType,
            'status' => $status,
            'counts' => $result['counts'],
            'warning' => $result['warning'] ?? null,
        ]);
    }

    public function create()
    {
        $employee = $this->requireManagerEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        return view('warnings/create', [
            'employee' => $employee,
            'employees' => $this->queryService->createFormData($employee)['employees'],
        ]);
    }

    public function store()
    {
        $employee = $this->requireManagerEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        if (! $this->validate($this->controllerActionService->createValidationRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $payload = $this->controllerActionService->createPayload($this->request);
        if (! $this->accessService->canManageEmployee($employee, (int) $payload['employee_id'])) {
            return redirect()->back()->withInput()->with('error', 'Você só pode advertir funcionários do seu departamento.');
        }

        if (strtotime($payload['occurrence_date']) > strtotime('today')) {
            return redirect()->back()->withInput()->with('error', 'A data da ocorrência não pode ser no futuro.');
        }

        $result = $this->workflowService->createWarning($employee, $payload, $this->request->getFiles());
        if (! ($result['success'] ?? false)) {
            $key = isset($result['warning']) ? 'warning' : 'error';
            return redirect()->back()->withInput()->with($key, $result[$key]);
        }

        return redirect()->to(sp_warning_index_url())->with('success', 'Advertência emitida com sucesso. Notificação enviada ao funcionário.');
    }

    public function show($id = null)
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $details = $this->queryService->warningDetails((int) $id);
        if (! $details) {
            return $this->warningNotFoundRedirect();
        }

        if (! $this->accessService->canViewWarning($employee, $details['warning'])) {
            return $this->denyAccess();
        }

        return view('warnings/show', ['employee' => $employee] + $details);
    }

    public function signForm($id = null)
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $details = $this->queryService->warningDetails((int) $id);
        if (! $details) {
            return $this->warningNotFoundRedirect();
        }

        if (! $this->accessService->isOwner($employee, $details['warning'])) {
            return redirect()->to(sp_warning_show_url((int) $id))->with('error', 'Apenas o funcionário advertido pode assinar a advertência.');
        }

        if ($details['warning']->status !== 'pendente-assinatura') {
            return redirect()->to(sp_warning_show_url((int) $id))->with('info', 'Esta advertência já foi processada.');
        }

        return view('warnings/sign', ['employee' => $employee] + $details);
    }

    public function sign($id = null)
    {
        $employee = $this->accessService->getAuthenticatedEmployee();
        if (! $employee) {
            return $this->requestExpectsJson()
                ? $this->jsonError('Não autenticado.')
                : redirect()->to(sp_login_url())->with('error', 'Sua sessão expirou. Faça login novamente.');
        }

        $warning = $this->findWarningForJson((int) $id);
        if ($warning instanceof ResponseInterface) {
            return $warning;
        }

        if (! $this->accessService->isOwner($employee, $warning)) {
            return $this->requestExpectsJson()
                ? $this->jsonError('Apenas o funcionário advertido pode assinar.')
                : redirect()->to(sp_warning_show_url((int) $id))->with('error', 'Apenas o funcionário advertido pode assinar.');
        }

        if ($warning->status !== 'pendente-assinatura') {
            return $this->requestExpectsJson()
                ? $this->jsonError('Esta advertência já foi processada.')
                : redirect()->to(sp_warning_show_url((int) $id))->with('info', 'Esta advertência já foi processada.');
        }

        $result = $this->workflowService->signWarning(
            $employee,
            $warning,
            security_sanitize($this->request->getPost() ?? []),
            $this->request->getFile('certificate')
        );

        if (! ($result['success'] ?? false)) {
            return $this->requestExpectsJson()
                ? $this->jsonError($result['message'] ?? 'Erro ao assinar advertência.')
                : redirect()->back()->withInput()->with('error', $result['message'] ?? 'Erro ao assinar advertência.');
        }

        if ($this->requestExpectsJson()) {
            return $this->response->setJSON($result);
        }

        return redirect()->to(sp_warning_show_url((int) $id))->with('success', $result['message'] ?? 'Advertência assinada com sucesso.');
    }

    public function sendSMSCode($id = null)
    {
        $employee = $this->accessService->getAuthenticatedEmployee();
        if (! $employee) {
            return $this->jsonError('Não autenticado.');
        }

        $warning = $this->findWarningForJson((int) $id);
        if ($warning instanceof ResponseInterface) {
            return $warning;
        }

        if (! $this->accessService->isOwner($employee, $warning)) {
            return $this->jsonError('Advertência não encontrada ou sem permissão.');
        }

        return $this->response->setJSON($this->workflowService->sendSMSCode($employee));
    }

    public function addWitnessForm($id = null)
    {
        $employee = $this->requireManagerEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $details = $this->queryService->warningDetails((int) $id);
        if (! $details) {
            return $this->warningNotFoundRedirect();
        }

        if (! $details['canAddWitness']) {
            return redirect()->to(sp_warning_show_url((int) $id))->with('error', 'Testemunha só pode ser adicionada após 48 horas sem assinatura.');
        }

        return view('warnings/add_witness', ['employee' => $employee] + $details);
    }

    public function addWitness($id = null)
    {
        $employee = $this->requireManagerEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $warning = $this->warningModel->find((int) $id);
        if (! $warning) {
            return $this->requestExpectsJson()
                ? $this->jsonError('Advertência não encontrada.')
                : $this->warningNotFoundRedirect();
        }

        if (! $this->controllerActionService->canAddWitnessByTime($warning)) {
            if ($this->requestExpectsJson()) {
                return $this->jsonError('Testemunha só pode ser adicionada após 48 horas.');
            }

            return redirect()->to(sp_warning_show_url((int) $id))->with('error', 'Testemunha só pode ser adicionada após 48 horas.');
        }

        if (! $this->validate($this->controllerActionService->witnessValidationRules())) {
            if ($this->requestExpectsJson()) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Dados da testemunha inválidos.',
                    'errors' => $this->validator->getErrors(),
                ]);
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->workflowService->refuseWithWitness(
            $employee,
            $warning,
            $this->controllerActionService->witnessPayload($this->request)
        );

        if ($this->requestExpectsJson()) {
            return $this->response->setJSON($result);
        }

        $flashKey = ($result['success'] ?? false) ? 'success' : 'error';
        $flashMessage = $result['message'] ?? 'Não foi possível adicionar a testemunha.';

        return redirect()->to(sp_warning_show_url((int) $warning->id))->with($flashKey, $flashMessage);
    }

    public function refuseSignature($id = null)
    {
        $employee = $this->accessService->getAuthenticatedEmployee();
        if (! $employee || ! $this->accessService->canManageWarnings($employee)) {
            return $this->jsonError('Acesso negado.');
        }

        $warning = $this->findWarningForJson((int) $id);
        if ($warning instanceof ResponseInterface) {
            return $warning;
        }

        if (! $this->controllerActionService->canAddWitnessByTime($warning)) {
            return $this->jsonError('Testemunha só pode ser adicionada após 48 horas.');
        }

        if (! $this->validate($this->controllerActionService->witnessValidationRules())) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Dados da testemunha inválidos.',
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $result = $this->workflowService->refuseWithWitness(
            $employee,
            $warning,
            $this->controllerActionService->witnessPayload($this->request)
        );

        return $this->response->setJSON($result);
    }

    private function requestExpectsJson(): bool
    {
        if ($this->request->isAJAX()) {
            return true;
        }

        $accept = strtolower((string) $this->request->getHeaderLine('Accept'));
        $contentType = strtolower((string) $this->request->getHeaderLine('Content-Type'));

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'application/vnd.supportponto')
            || str_contains($contentType, 'application/json');
    }

    public function dashboard($employeeId = null)
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $targetId = $employeeId ? (int) $employeeId : (int) $employee['id'];
        $data = $this->queryService->dashboardData($targetId);

        if (! $data) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Funcionário não encontrado.');
        }

        if (! $this->accessService->canViewEmployeeDashboard($employee, $data['targetEmployee'])) {
            return $this->denyAccess();
        }

        return view('warnings/dashboard', ['employee' => $employee] + $data);
    }

    public function downloadPDF($id = null)
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        $data = $this->queryService->downloadData((int) $id);
        if (! $data) {
            return redirect()->to(sp_warning_index_url())->with('error', 'PDF não encontrado.');
        }

        if (! $this->accessService->canViewWarning($employee, $data['warning'])) {
            return $this->denyAccess();
        }

        if (! $data['filepath']) {
            return redirect()->to(sp_warning_show_url((int) $id))->with('error', 'Arquivo PDF não encontrado.');
        }

        return $this->response->download($data['filepath'], null)->setFileName('advertencia_' . $id . '.pdf');
    }

    public function delete($id = null)
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        if (! $this->accessService->isAdmin($employee)) {
            return redirect()->to(route_to('dashboard'))->with('error', 'Apenas administradores podem excluir advertências.');
        }

        $warning = $this->findWarningForRedirect((int) $id);
        if ($warning instanceof ResponseInterface) {
            return $warning;
        }

        $this->workflowService->deleteWarning($employee, $warning);

        return redirect()->to(sp_warning_index_url())->with('success', 'Advertência excluída com sucesso.');
    }

    private function requireAuthenticatedEmployee(): array|ResponseInterface
    {
        $employee = $this->accessService->getAuthenticatedEmployee();
        if (! $employee) {
            return redirect()->to(sp_login_url())->with('error', 'Você precisa estar autenticado.');
        }

        return $employee;
    }

    private function requireManagerEmployee(): array|ResponseInterface
    {
        $employee = $this->requireAuthenticatedEmployee();
        if ($employee instanceof ResponseInterface) {
            return $employee;
        }

        if (! $this->accessService->canManageWarnings($employee)) {
            return $this->denyManagerOnly();
        }

        return $employee;
    }

    private function findWarningForJson(int $warningId)
    {
        $warning = $this->warningModel->find($warningId);

        return $warning ?: $this->jsonError('Advertência não encontrada.');
    }

    private function findWarningForRedirect(int $warningId)
    {
        $warning = $this->warningModel->find($warningId);

        return $warning ?: $this->warningNotFoundRedirect();
    }

    private function denyManagerOnly(): ResponseInterface
    {
        return redirect()->to(route_to('dashboard'))
            ->with('error', 'Acesso negado. Apenas gestores, RH e administradores podem acessar advertências.');
    }

    private function denyAccess(): ResponseInterface
    {
        return redirect()->to(route_to('dashboard'))->with('error', 'Acesso negado.');
    }

    private function warningNotFoundRedirect(): ResponseInterface
    {
        return redirect()->to(sp_warning_index_url())->with('error', 'Advertência não encontrada.');
    }

    private function jsonError(string $message): ResponseInterface
    {
        return $this->response->setJSON(['success' => false, 'message' => $message]);
    }
}
