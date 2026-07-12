<?php

namespace App\Controllers\Biometric;

use App\Controllers\BaseController;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Services\Biometric\FaceRecognitionWorkflowService;
use App\Services\Biometric\Face\FaceImageService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class FaceRecognitionController extends BaseController
{
    protected FaceRecognitionWorkflowService $workflowService;
    protected EmployeeModel $employeeModel;
    protected BiometricTemplateModel $biometricTemplateModel;
    protected FaceImageService $faceImageService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->workflowService = new FaceRecognitionWorkflowService();
        $this->employeeModel = new EmployeeModel();
        $this->biometricTemplateModel = new BiometricTemplateModel();
        $this->faceImageService = new FaceImageService();
    }

    /**
     * Display biometric enrollment page
     */
    public function index()
    {
        $this->requireAuth();
        return view('biometric/enrollment', $this->workflowService->enrollmentPageData($this->currentUser));
    }

    /**
     * Display biometric enrollment page for a specific employee (admin/rh/gestor only)
     */
    public function enrollmentForEmployee(int $employeeId)
    {
        $this->requireAuth();
        $this->requireBiometricManagerArea();

        $targetEmployee = $this->employeeModel->find($employeeId);
        if (!$targetEmployee) {
            $this->setError('Colaborador não encontrado.');
            return redirect()->to(site_url('biometric/manage'));
        }

        // LGPD gate: check if employee has accepted current biometric consent term
        $termModel    = new \App\Models\ConsentTermModel();
        $activeTerm   = $termModel->getActiveTerm('biometric_face');
        if ($activeTerm) {
            $db = \Config\Database::connect();
            $hasConsent = $db->table('user_consents')
                ->where('employee_id', $employeeId)
                ->where('consent_type', 'biometric_face')
                ->where('granted', true)
                ->where('version', $activeTerm->version)
                ->where('revoked_at IS NULL')
                ->countAllResults();
            if (!$hasConsent) {
                return redirect()->to(site_url('biometric/consent-term/' . $employeeId));
            }
        }

        $data = $this->workflowService->enrollmentPageData($targetEmployee);
        $data['targetEmployee'] = $targetEmployee;
        $data['isAdminEnrollment'] = true;
        $data['hasConsent'] = true; // consent verified by gate above

        return view('biometric/enrollment', $data);
    }

    /**
     * Enroll face biometric (using DeepFace API)
     */
    public function enrollFace()
    {
        $this->requireAuth();

        $httpsResponse = $this->requireHttps('Dados biométricos devem ser transmitidos via HTTPS.');
        if ($httpsResponse) {
            return $httpsResponse;
        }

        $photoBase64 = $this->resolvePhotoPayload();
        if (!$photoBase64) {
            return $this->respondError('Foto é obrigatória.', null, 400);
        }

        $validation = $this->faceImageService->validateImage($photoBase64);
        if (($validation['valid'] ?? false) !== true) {
            return $this->respondError($validation['error'] ?? 'Imagem facial inválida.', null, 422);
        }

        try {
            $jsonPayload = $this->request->is('json') ? ($this->request->getJSON(true) ?? []) : [];
            $force = filter_var($this->request->getPost('force') ?? ($jsonPayload['force'] ?? false), FILTER_VALIDATE_BOOL);
            // Allow admin/rh/gestor to enroll on behalf of another employee
            $targetId = (int) $this->currentUser->id;
            $requestedEmployeeId = (int) ($this->request->getPost('employee_id') ?? ($jsonPayload['employee_id'] ?? 0));
            if ($requestedEmployeeId > 0 && $requestedEmployeeId !== $targetId) {
                $targetEmployee = $this->employeeModel->find($requestedEmployeeId);
                if (!$targetEmployee || !$this->canManageBiometricEmployee($targetEmployee)) {
                    return $this->respondError('Sem permissão para cadastrar biometria deste colaborador.', null, 403);
                }
                $targetId = $requestedEmployeeId;
            }
            $result = $this->workflowService->queueEnrollment($targetId, $photoBase64, (bool) $force, [
                'actor_id' => (int) ($this->currentUser->id ?? 0),
                'actor_role' => (string) ($this->currentUser->role ?? ''),
                'request_ip' => (string) $this->request->getIPAddress(),
                'user_agent' => (string) $this->request->getUserAgent(),
            ]);
            if (!$result['success']) {
                return $this->respondError($result['message'] ?? 'Erro ao processar cadastro facial.', null, (int) ($result['status'] ?? 500));
            }

            $this->writeAuditEvents($result['audit_events'] ?? []);

            return $this->respondSuccess(
                $result['data'] ?? null,
                $result['message'] ?? 'Biometria facial enfileirada para processamento.',
                (int) ($result['status'] ?? 202)
            );
        } catch (\Exception $e) {
            log_message('error', 'Face enrollment error for employee {id}: {error}', [
                'id' => $this->currentUser->id,
                'error' => $e->getMessage(),
            ]);
            return $this->respondError('Erro ao processar cadastro facial.', null, 500);
        }
    }

    /**
     * Display facial recognition terminal for punching
     */
    public function terminal()
    {
        return view('biometric/face_terminal');
    }

    /**
     * Delete biometric template
     */
    public function deleteTemplate(int $templateId)
    {
        $this->requireAuth();

        $template = $this->biometricTemplateModel->find($templateId);
        if (!$template) {
            return $this->respondError('Template não encontrado.', null, 404);
        }

        $targetEmployee = $this->employeeModel->find((int) $template->employee_id);
        if (!$targetEmployee) {
            return $this->respondError('Colaborador vinculado ao template não foi encontrado.', null, 404);
        }

        $isSelf = (int) ($this->currentUser->id ?? 0) === (int) $targetEmployee->id;
        $canDelete = $isSelf || $this->canManageBiometricEmployee($targetEmployee);

        if (!$canDelete) {
            return $this->respondError('Você não tem permissão para excluir este template.', null, 403);
        }

        try {
            $result = $this->workflowService->deleteTemplate($templateId, (int) $this->currentUser->id, !$isSelf && $this->canManageBiometricEmployee($targetEmployee));
            if (!$result['success']) {
                return $this->respondError($result['message'] ?? 'Erro ao excluir template.', null, (int) ($result['status'] ?? 500));
            }

            $this->writeAuditEvents($result['audit_events'] ?? []);
            return $this->respondSuccess($result['data'] ?? null, $result['message'] ?? 'Template biométrico excluído com sucesso.');
        } catch (\Exception $e) {
            log_message('error', 'Template deletion error: ' . $e->getMessage());
            return $this->respondError('Erro ao excluir template.', null, 500);
        }
    }

    /**
     * Delete all biometric templates for a user
     */
    public function deleteUserTemplates(int $employeeId)
    {
        $this->requireAuth();

        $targetEmployee = $this->employeeModel->find($employeeId);
        if (!$targetEmployee) {
            return $this->respondError('Colaborador não encontrado.', null, 404);
        }

        if (!$this->canManageBiometricEmployee($targetEmployee)) {
            return $this->respondError('Você não tem permissão para excluir templates deste usuário.', null, 403);
        }

        try {
            $result = $this->workflowService->deleteUserTemplates($employeeId);
            if (!$result['success']) {
                return $this->respondError($result['message'] ?? 'Erro ao excluir templates do usuário.', null, (int) ($result['status'] ?? 500));
            }

            $this->writeAuditEvents($result['audit_events'] ?? []);
            return $this->respondSuccess($result['data'] ?? null, $result['message'] ?? 'Templates do usuário removidos com sucesso.');
        } catch (\Exception $e) {
            log_message('error', 'User template deletion error: ' . $e->getMessage());
            return $this->respondError('Erro ao excluir templates do usuário.', null, 500);
        }
    }

    /**
     * Grant biometric consent
     */
    public function grantConsent()
    {
        $this->requireAuth();

        $result = $this->workflowService->grantConsent((int) $this->currentUser->id, $this->getClientIp());
        if (!$result['success']) {
            return $this->respondError($result['message'] ?? 'Erro ao registrar consentimento.', null, (int) ($result['status'] ?? 500));
        }

        $this->writeAuditEvents($result['audit_events'] ?? []);
        return $this->respondSuccess(null, $result['message'] ?? 'Consentimento registrado com sucesso!', (int) ($result['status'] ?? 201));
    }

    /**
     * Revoke biometric consent
     */
    public function revokeConsent()
    {
        $this->requireAuth();

        $result = $this->workflowService->revokeConsent((int) $this->currentUser->id);
        if (!$result['success']) {
            return $this->respondError($result['message'] ?? 'Erro ao revogar consentimento.', null, (int) ($result['status'] ?? 500));
        }

        $this->writeAuditEvents($result['audit_events'] ?? []);
        return $this->respondSuccess(null, $result['message'] ?? 'Consentimento revogado. Seus dados biométricos foram desativados.');
    }

    /**
     * Test facial recognition (using DeepFace API)
     */
    public function testRecognition()
    {
        $this->requireAuth();

        $httpsResponse = $this->requireHttps('Dados biométricos devem ser transmitidos via HTTPS.');
        if ($httpsResponse) {
            return $httpsResponse;
        }

        $photoBase64 = $this->resolvePhotoPayload();
        if (!$photoBase64) {
            return $this->respondError('Foto é obrigatória.', null, 400);
        }

        $validation = $this->faceImageService->validateImage($photoBase64);
        if (($validation['valid'] ?? false) !== true) {
            return $this->respondError($validation['error'] ?? 'Imagem facial inválida.', null, 422);
        }

        try {
            $result = $this->workflowService->testRecognition((int) $this->currentUser->id, $photoBase64);
            $this->writeAuditEvents($result['audit_events'] ?? []);

            if (!$result['success']) {
                return $this->respondError(
                    $result['message'] ?? 'Erro ao processar reconhecimento.',
                    $result['errors'] ?? null,
                    (int) ($result['status'] ?? 500)
                );
            }

            return $this->respondSuccess($result['data'] ?? null, $result['message'] ?? 'Teste bem-sucedido!');
        } catch (\Exception $e) {
            log_message('error', 'Face recognition test error: ' . $e->getMessage());
            return $this->respondError('Erro ao processar reconhecimento facial.', null, 500);
        }
    }


    /**
     * Production diagnostics for DeepFace and biometric storage.
     */
    public function diagnostics()
    {
        $this->requireBiometricManagerArea();

        $withConnections = ! filter_var($this->request->getGet('no_connections') ?? false, FILTER_VALIDATE_BOOL);
        $result = $this->workflowService->biometricDiagnostics($withConnections);

        return $this->attachResponseContext($this->response, true)
            ->setJSON($result)
            ->setStatusCode(($result['status'] ?? 'error') === 'error' ? 503 : 200);
    }

    /**
     * Management dashboard for biometric and face recognition
     */
    public function manage()
    {
        $this->requireBiometricManagerArea();
        return view('biometric/manage', $this->workflowService->managementData());
    }

    private function requireBiometricManagerArea(): void
    {
        $this->requireBiometricArea('A área de gestão biométrica está disponível apenas para admin, gestor ou RH.');
    }

    private function canManageBiometricEmployee(object $targetEmployee): bool
    {
        if (!$this->authorizationService->canAccessBiometricArea($this->currentUser)) {
            return false;
        }

        return $this->canAccessEmployeeRecord($targetEmployee, false);
    }

    private function resolvePhotoPayload(): ?string
    {
        $photoBase64 = $this->request->getPost('photo') ?: $this->request->getPost('image');
        if ($photoBase64) {
            return $photoBase64;
        }

        if ($this->request->is('json')) {
            $payload = $this->request->getJSON(true) ?? [];
            return $payload['photo'] ?? $payload['image'] ?? null;
        }

        return null;
    }

    private function writeAuditEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->logAudit(
                $event['action'] ?? 'BIOMETRIC_EVENT',
                $event['entity'] ?? 'biometric',
                $event['entity_id'] ?? null,
                $event['old_values'] ?? null,
                $event['new_values'] ?? null,
                $event['description'] ?? 'Evento biométrico'
            );
        }
    }

    /**
     * Self-enrollment page for the authenticated employee
     */
    public function selfEnroll()
    {
        $this->requireAuth();
        $data = $this->workflowService->enrollmentPageData($this->currentUser);
        return view('biometric/self_enroll', $data);
    }

}
