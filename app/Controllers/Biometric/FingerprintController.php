<?php

namespace App\Controllers\Biometric;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Services\Biometric\FingerprintWorkflowService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * FingerprintController
 *
 * Camada HTTP para fluxo de biometria digital.
 */
class FingerprintController extends BaseController
{
    protected EmployeeModel $employeeModel;
    protected FingerprintWorkflowService $workflowService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->employeeModel = new EmployeeModel();
        $this->workflowService = new FingerprintWorkflowService();
    }

    /**
     * Display fingerprint enrollment form for employee
     * GET /fingerprint/enroll/{employee_id}
     */
    public function enroll($employeeId = null)
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee || !$this->authorizationService->canAccessBiometricArea($employee)) {
            return redirect()->to(route_to('dashboard'))
                ->with('error', 'Acesso negado. Apenas perfis autorizados podem cadastrar biometrias.');
        }

        if ($employeeId === null) {
            return redirect()->to(sp_employees_index_url())->with('error', 'ID do colaborador é obrigatório.');
        }

        $targetEmployee = $this->workflowService->findEmployee((int) $employeeId);
        if (!$targetEmployee) {
            return redirect()->to(sp_employees_index_url())->with('error', 'Colaborador não encontrado.');
        }

        if (!$this->canManageEmployeeRecord($targetEmployee)) {
            return redirect()->to(sp_employees_index_url())->with('error', 'Você não tem permissão para gerenciar a biometria deste colaborador.');
        }

        // LGPD gate: verificar aceite do termo de biometria digital
        $termModel  = new \App\Models\ConsentTermModel();
        $activeTerm = $termModel->getActiveTerm('biometric_fingerprint');
        if ($activeTerm) {
            $db = \Config\Database::connect();
            $hasConsent = $db->table('user_consents')
                ->where('employee_id', (int) $employeeId)
                ->where('consent_type', 'biometric_fingerprint')
                ->where('granted', true)
                ->where('version', $activeTerm->version)
                ->where('revoked_at IS NULL')
                ->countAllResults();
            if (!$hasConsent) {
                return redirect()->to(site_url('biometric/consent-term/biometric_fingerprint/' . $employeeId));
            }
        }

        return view('biometric/enroll_fingerprint', $this->workflowService->enrollmentPageData($employee, (int) $employeeId));
    }

    /**
     * Store fingerprint template
     * POST /fingerprint/enroll
     */
    public function store()
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee || !$this->authorizationService->canAccessBiometricArea($employee)) {
            return $this->jsonError('Acesso negado', 403);
        }

        $response = $this->requireHttps('Biometric data must be transmitted over HTTPS.');
        if ($response) {
            return $response;
        }

        $rules = [
            'employee_id' => 'required|integer',
            'template' => 'required',
            'finger' => 'required|in_list[right_thumb,right_index,right_middle,right_ring,right_pinky,left_thumb,left_index,left_middle,left_ring,left_pinky]',
            'quality' => 'permit_empty|decimal',
        ];

        if (!$this->validate($rules)) {
            return $this->jsonError('Dados inválidos', 400, $this->validator->getErrors());
        }

        $employeeId = (int) $this->request->getPost('employee_id');
        $targetEmployee = $this->workflowService->findEmployee($employeeId);

        if (!$targetEmployee) {
            return $this->jsonError('Colaborador não encontrado', 404);
        }

        if (!$this->canManageEmployeeRecord($targetEmployee)) {
            return $this->jsonError('Você não tem permissão para gerenciar a biometria deste colaborador', 403);
        }

        $result = $this->workflowService->enrollFingerprint(
            $employee,
            $employeeId,
            (string) $this->request->getPost('template'),
            (string) $this->request->getPost('finger'),
            (float) ($this->request->getPost('quality') ?? 0.85),
            $this->request->getUserAgent()->getAgentString(),
            $this->request->getIPAddress(),
        );

        if (!$result['success']) {
            return $this->jsonError($result['message'] ?? 'Erro ao cadastrar digital', (int) ($result['status'] ?? 500));
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'] ?? [],
        ])->setStatusCode((int) ($result['status'] ?? 201));
    }

    /**
     * Delete fingerprint template
     * DELETE /fingerprint/{id}
     */
    public function delete($id = null)
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee || !$this->authorizationService->canAccessBiometricArea($employee)) {
            return $this->jsonError('Acesso negado', 403);
        }

        if ($id === null) {
            return $this->jsonError('ID do template é obrigatório', 400);
        }

        $result = $this->workflowService->deleteFingerprint(
            $employee,
            (int) $id,
            $this->request->getUserAgent()->getAgentString(),
            $this->request->getIPAddress(),
        );

        if (!$result['success']) {
            return $this->jsonError($result['message'] ?? 'Erro ao excluir template', (int) ($result['status'] ?? 500));
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    /**
     * Test fingerprint recognition
     * POST /fingerprint/test
     */
    public function test()
    {
        $employee = $this->getAuthenticatedEmployee();

        if (!$employee || !$this->authorizationService->canAccessBiometricArea($employee)) {
            return $this->jsonError('Acesso negado', 403);
        }

        $response = $this->requireHttps('Biometric data must be transmitted over HTTPS.');
        if ($response) {
            return $response;
        }

        $rules = [
            'template_id' => 'required|integer',
            'test_template' => 'required',
        ];

        if (!$this->validate($rules)) {
            return $this->jsonError('Dados inválidos', 400, $this->validator->getErrors());
        }

        $result = $this->workflowService->testFingerprint(
            $employee,
            (int) $this->request->getPost('template_id'),
            (string) $this->request->getPost('test_template'),
            $this->request->getUserAgent()->getAgentString(),
            $this->request->getIPAddress(),
        );

        if (!$result['success']) {
            return $this->jsonError($result['message'] ?? 'Erro ao testar digital', (int) ($result['status'] ?? 500));
        }

        return $this->response->setJSON($result);
    }


    /**
     * Generate a WebAuthn challenge for fingerprint enrollment via mobile device.
     * GET /biometric/fingerprint/webauthn-challenge
     */
    public function webauthnChallenge(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee || !$this->authorizationService->canAccessBiometricArea($employee)) {
            return $this->jsonError('Acesso negado', 403);
        }
        $challenge = base64_encode(random_bytes(32));
        session()->set('webauthn_fp_challenge', $challenge);
        return $this->response->setJSON(['challenge' => $challenge, 'success' => true]);
    }


    /**
     * Enroll fingerprint from uploaded camera image (mobile flow).
     * POST /biometric/fingerprint/enroll-from-image
     */
    public function enrollFromImage(): ResponseInterface
    {
        $employee = $this->getAuthenticatedEmployee();
        if (!$employee || !$this->authorizationService->canAccessBiometricArea($employee)) {
            return $this->jsonError('Acesso negado', 403);
        }

        $employeeId = (int) $this->request->getPost('employee_id');
        $finger     = (string) $this->request->getPost('finger');
        $quality    = (float) ($this->request->getPost('quality') ?: 0.75);

        if (!$employeeId || !$finger) {
            return $this->jsonError('Dados incompletos. Informe o colaborador e o dedo.', 400);
        }

        $targetEmployee = $this->workflowService->findEmployee($employeeId);
        if (!$targetEmployee) {
            return $this->jsonError('Colaborador nao encontrado.', 404);
        }

        // Accept base64 image (from JS canvas) or multipart file upload
        $imageBase64 = $this->request->getPost('image_base64');
        $uploadedFile = $this->request->getFile('fingerprint_image');

        $tmpPath = null;
        try {
            if ($imageBase64) {
                // Strip data URI prefix if present
                $b64data = preg_replace('#^data:image/\w+;base64,#', '', $imageBase64);
                $imgData = base64_decode($b64data);
                if (!$imgData) {
                    return $this->jsonError('Imagem invalida. Tente novamente.', 400);
                }
                $tmpPath = WRITEPATH . 'uploads/biometric_tmp_' . uniqid('', true) . '.jpg';
                file_put_contents($tmpPath, $imgData);
            } elseif ($uploadedFile && $uploadedFile->isValid() && !$uploadedFile->hasMoved()) {
                $tmpPath = WRITEPATH . 'uploads/biometric_tmp_' . uniqid('', true) . '.jpg';
                $uploadedFile->move(WRITEPATH . 'uploads/', basename($tmpPath));
            } else {
                return $this->jsonError('Nenhuma imagem recebida.', 400);
            }

            if (!file_exists($tmpPath)) {
                return $this->jsonError('Erro ao salvar imagem temporaria.', 500);
            }

            // Extract fingerprint template from image
            $sourceAFIS = new \App\Services\Biometric\SourceAFISService();
            $extraction = $sourceAFIS->extractTemplate($tmpPath);

            if (!($extraction['success'] ?? false) || empty($extraction['template'])) {
                return $this->jsonError(
                    'Nao foi possivel extrair a digital da imagem. Tente posicionar melhor o dedo e tirar a foto em boa iluminacao.',
                    422
                );
            }

            // Enroll using extracted template
            $result = $this->workflowService->enrollFingerprint(
                $employee,
                $employeeId,
                (string) $extraction['template'],
                $finger,
                $quality,
                $this->request->getUserAgent()->getAgentString(),
                $this->request->getIPAddress()
            );

            if (!$result['success']) {
                return $this->jsonError($result['message'] ?? 'Erro ao cadastrar digital.', (int) ($result['status'] ?? 500));
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Digital cadastrada com sucesso via captura de imagem!',
                'minutiae_count' => $extraction['minutiae_count'] ?? 0,
                'data' => $result['data'] ?? [],
            ])->setStatusCode(201);

        } finally {
            if ($tmpPath && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    protected function getAuthenticatedEmployee(): ?array
    {
        $session = session();
        $employeeId = $session->get('user_id');

        if (!$employeeId) {
            return null;
        }

        $employee = $this->employeeModel->find($employeeId);

        if (!$employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'role' => $employee->role,
            'department' => $employee->department,
        ];
    }

    private function jsonError(string $message, int $statusCode, ?array $errors = null): ResponseInterface
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return $this->response->setJSON($payload)->setStatusCode($statusCode);
    }
}
