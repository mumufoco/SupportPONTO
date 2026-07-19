<?php

namespace App\Controllers\Timesheet;

use App\Controllers\BaseController;
use App\Models\PendingPunchModel;
use App\Models\PunchRuleViolationModel;
use App\Services\Timesheet\PendingPunchAttemptStore;
use App\Services\Timesheet\PendingPunchService;
use App\Services\Timesheet\PunchMethodReadinessService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Controlador para o fluxo de justificativa por falha automática.
 * Ativado quando todos os métodos de ponto disponíveis falharam tecnicamente.
 */
class PendingPunchController extends BaseController
{
    protected PendingPunchService         $pendingPunchService;
    protected PunchMethodReadinessService $readinessService;
    protected PendingPunchAttemptStore    $attemptStore;
    protected PunchRuleViolationModel     $violationModel;
    protected PendingPunchModel           $pendingPunchModel;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->pendingPunchService = new PendingPunchService();
        $this->readinessService    = new PunchMethodReadinessService();
        $this->attemptStore        = new PendingPunchAttemptStore();
        $this->violationModel      = new PunchRuleViolationModel();
        $this->pendingPunchModel   = new PendingPunchModel();
        helper(['datetime', 'format']);
    }

    /**
     * Página de justificativa — acessada após todos os métodos automáticos falharem.
     * GET /timesheet/punch/justify
     */
    public function index(): string|ResponseInterface
    {
        $this->requireAuth();

        $context = $this->attemptStore->getContextForEmployee((int) $this->currentUser->id);
        $attemptLog = $context['attempt_log'] ?? [];
        $enabledMethods = $context['enabled_methods'] ?? array_keys(array_filter((array) ($this->readinessService->summary()->getArrayCopy()['enabled'] ?? [])));

        $eligibility = $this->pendingPunchService->evaluateEligibility(
            (int) $this->currentUser->id,
            $attemptLog,
            $enabledMethods
        );

        if ($attemptLog === []) {
            $this->setWarning('Nenhum contexto recente de falha técnica foi encontrado. Tente registrar o ponto novamente antes de enviar justificativa.');
            return redirect()->to(sp_timesheet_punch_url());
        }

        return view('timesheet/punch_justification', [
            'eligibility'    => $eligibility,
            'attemptLog'     => $attemptLog,
            'failureSummary' => $eligibility['failure_summary'] ?? [],
            'punchTypes'    => [
                'entrada'          => 'Entrada',
                'saida'            => 'Saída',
                'intervalo_inicio' => 'Início de intervalo',
                'intervalo_fim'    => 'Fim de intervalo',
            ],
        ]);
    }

    /**
     * Submete a justificativa.
     * POST /timesheet/punch/justify
     */
    public function submit(): ResponseInterface
    {
        $this->requireAuth();

        $rules = [
            'punch_type'       => 'required|in_list[entrada,saida,intervalo_inicio,intervalo_fim]',
            'justification'    => 'required|min_length[20]|max_length[1000]',
            'situation_type'   => 'required|in_list[equipment_failure,system_slow,camera_inaccessible,biometric_failed,other]',
            'confirm_presence' => 'required',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Revise os campos da justificativa e tente novamente.')->with('errors', $this->validator->getErrors());
        }

        $context = $this->attemptStore->getContextForEmployee((int) $this->currentUser->id);
        $attemptLog = $context['attempt_log'] ?? [];
        $enabledMethods = $context['enabled_methods'] ?? [];

        if ($attemptLog === [] || $enabledMethods === []) {
            return redirect()->to(sp_timesheet_punch_url())->with('error', 'O contexto da falha técnica expirou. Tente novamente o registro de ponto antes de enviar a justificativa.');
        }

        $result = $this->pendingPunchService->submit(
            (int) $this->currentUser->id,
            (string) $this->request->getPost('punch_type'),
            (string) $this->request->getPost('justification'),
            (string) $this->request->getPost('situation_type'),
            $attemptLog,
            $enabledMethods,
            $this->request
        );

        if (!$result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message'] ?? 'Erro ao enviar justificativa.');
        }

        $this->attemptStore->clearForEmployee((int) $this->currentUser->id);
        $this->setSuccess($result['message']);
        return redirect()->to(route_to('dashboard.employee'));
    }

    /**
     * Pendências que o próprio SISTEMA gerou (cron punches:close-incomplete-days) ao
     * detectar, na virada do dia, um par de marcações incompleto do colaborador
     * logado — aguardando que ele preencha a justificativa.
     * GET /timesheet/punch/pendencias
     */
    public function myPending(): string
    {
        $this->requireAuth();

        $awaiting = $this->pendingPunchModel->getAwaitingEmployee((int) $this->currentUser->id);

        return view('timesheet/punch_pending_response', [
            'awaiting'   => $awaiting,
            'punchTypes' => [
                'entrada'          => 'Entrada',
                'saida'            => 'Saída',
                'intervalo_inicio' => 'Início de intervalo',
                'intervalo_fim'    => 'Fim de intervalo',
            ],
        ]);
    }

    /**
     * Colaborador preenche a justificativa de uma pendência gerada pelo sistema.
     * POST /timesheet/punch/pendencias/{id}
     */
    public function respondToPending(int $pendingId): ResponseInterface
    {
        $this->requireAuth();

        $rules = [
            'justification'  => 'required|min_length[20]|max_length[1000]',
            'situation_type' => 'required|in_list[equipment_failure,system_slow,camera_inaccessible,biometric_failed,missing_checkout,other]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Revise os campos da justificativa e tente novamente.')->with('errors', $this->validator->getErrors());
        }

        $result = $this->pendingPunchService->submitEmployeeResponseForSystemPending(
            $pendingId,
            (int) $this->currentUser->id,
            (string) $this->request->getPost('justification'),
            (string) $this->request->getPost('situation_type'),
            $this->request
        );

        if (!$result['success']) {
            return redirect()->back()->withInput()->with('error', $result['message'] ?? 'Erro ao enviar justificativa.');
        }

        $this->setSuccess($result['message']);
        return redirect()->to(route_to('timesheet.punch.pending'));
    }

    /**
     * Painel de aprovação para gestores/RH.
     * GET /manager/pending-punches
     */
    public function managerPanel(): string|ResponseInterface
    {
        $this->requireAuth();

        if (!$this->can('justifications.approve') && !$this->can('justifications.approve.team')) {
            if ($this->request->isAJAX()) {
                return $this->respondError('Acesso negado.', null, 403);
            }
            $this->setError('Acesso negado. Você não tem permissão para revisar registros pendentes.');
            return redirect()->to(route_to('dashboard'));
        }

        $actorRole = $this->authorizationService->getRole($this->currentUser);
        $department = ($actorRole === 'gestor')
            ? (string) ($this->currentUser->department ?? '')
            : null; // admin/RH vê todos

        $pending = $this->pendingPunchService->listPendingForManager($department);
        $violations = $this->violationModel->listPendingForManager($department);

        return view('timesheet/pending_punch_panel', [
            'pendingList' => $pending,
            'violationList' => $violations,
            'violationModel' => $this->violationModel,
            'title'       => 'Aprovação de Registros Pendentes',
        ]);
    }

    /**
     * Marca uma irregularidade de conformidade (CLT) como tratada.
     * POST /manager/pending-punches/violations/{id}/resolve
     */
    public function resolveViolation(int $id): ResponseInterface
    {
        $this->requireAuth();

        if (!$this->can('justifications.approve') && !$this->can('justifications.approve.team')) {
            return $this->approvalResponse(false, 'Acesso negado.', null, 403);
        }

        $notes = trim((string) ($this->request->getPost('review_notes') ?: ''));
        if ($notes === '') {
            return $this->approvalResponse(false, 'Descreva a providência tomada antes de marcar como tratada.', null, 400);
        }

        $violation = $this->violationModel->find($id);
        if (!$violation) {
            return $this->approvalResponse(false, 'Irregularidade não encontrada.', null, 404);
        }

        $actorRole = $this->authorizationService->getRole($this->currentUser);
        if ($actorRole === 'gestor') {
            $employee = model(\App\Models\EmployeeModel::class)->find((int) $violation->employee_id);
            if (!$employee || (string) $employee->department !== (string) ($this->currentUser->department ?? '')) {
                return $this->approvalResponse(false, 'Você só pode tratar irregularidades do seu departamento.', null, 403);
            }
        }

        $ok = $this->violationModel->markResolved($id, (int) $this->currentUser->id, $notes);

        return $this->approvalResponse($ok, $ok ? 'Irregularidade marcada como tratada.' : 'Não foi possível atualizar a irregularidade.', null, $ok ? 200 : 400);
    }

    /**
     * Aprova registro pendente.
     * POST /manager/pending-punches/{id}/approve
     */
    public function approve(int $pendingId): ResponseInterface
    {
        $this->requireAuth();

        if (!$this->can('justifications.approve') && !$this->can('justifications.approve.team')) {
            return $this->approvalResponse(false, 'Acesso negado.', null, 403);
        }

        $reviewNotes = trim((string) ($this->request->getPost('review_notes') ?: ''));

        $result = $this->pendingPunchService->approve(
            $pendingId,
            $this->currentUser,
            $reviewNotes
        );

        return $this->approvalResponse(
            (bool) ($result['success'] ?? false),
            (string) ($result['message'] ?? 'Não foi possível aprovar o registro.'),
            ['final_punch_id' => $result['final_punch_id'] ?? null],
            (int) ($result['status'] ?? 400)
        );
    }

    /**
     * Rejeita registro pendente.
     * POST /manager/pending-punches/{id}/reject
     */
    public function reject(int $pendingId): ResponseInterface
    {
        $this->requireAuth();

        if (!$this->can('justifications.approve') && !$this->can('justifications.approve.team')) {
            return $this->approvalResponse(false, 'Acesso negado.', null, 403);
        }

        $reviewNotes = trim((string) ($this->request->getPost('review_notes') ?: ''));

        $result = $this->pendingPunchService->reject(
            $pendingId,
            $this->currentUser,
            $reviewNotes
        );

        return $this->approvalResponse(
            (bool) ($result['success'] ?? false),
            (string) ($result['message'] ?? 'Não foi possível rejeitar o registro.'),
            [],
            (int) ($result['status'] ?? 400)
        );
    }

    private function approvalResponse(bool $success, string $message, ?array $data = null, int $status = 200): ResponseInterface
    {
        // Estes endpoints são exclusivamente chamados via AJAX (spFetch).
        // Sempre retorna JSON para evitar que o fetch siga redirects HTML.
        return $success
            ? $this->respondSuccess($data ?? [], $message, $status)
            : $this->respondError($message, null, $status);
    }
}

