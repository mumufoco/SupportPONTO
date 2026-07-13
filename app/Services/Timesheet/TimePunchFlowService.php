<?php

namespace App\Services\Timesheet;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Services\Terminal\PublicTerminalSecurityService;
use CodeIgniter\HTTP\RequestInterface;

class TimePunchFlowService
{
    protected PunchService $punchService;
    protected EmployeeModel $employeeModel;
    protected AuditModel $auditModel;
    protected SettingModel $settingModel;

    public function __construct()
    {
        $this->punchService = new PunchService();
        $this->employeeModel = new EmployeeModel();
        $this->auditModel = new AuditModel();
        $this->settingModel = new SettingModel();
    }

    public function getEnabledPunchMethods(): array
    {
        return $this->punchService->getEnabledPunchMethods();
    }

    public function handleCodePunch(RequestInterface $request, string $uniqueCode, ?string $punchType): array
    {
        $validation = $this->validateRequiredPunchInput([
            'unique_code' => $uniqueCode,
            'punch_type' => $punchType,
        ], [
            'unique_code' => 'required|min_length[4]|max_length[20]',
            'punch_type' => 'required|in_list[entrada,saida,intervalo_inicio,intervalo_fim]',
        ]);

        if (! $validation['valid']) {
            return $this->invalidDataResponse($validation['errors']);
        }

        $employee = $this->punchService->findEmployeeByCode($uniqueCode);

        if (! $employee) {
            $this->auditModel->log(null, 'PUNCH_FAILED', 'time_punches', null, null, null, "Tentativa de registro com código inválido: {$uniqueCode}", 'warning');
            return $this->errorResult('Código inválido.', 404);
        }

        if (! $employee->active) {
            return $this->errorResult('Funcionário inativo.', 403);
        }

        return $this->processPunchRegistration((int) $employee->id, (string) $punchType, 'codigo', $request);
    }

    public function handleCpfPunch(RequestInterface $request, string $cpfInput, ?string $punchType): array
    {
        $cpfDigits = preg_replace('/\D+/', '', $cpfInput) ?? '';

        $validation = $this->validateRequiredPunchInput([
            'cpf' => $cpfDigits,
            'punch_type' => $punchType,
        ], [
            'cpf' => 'required|exact_length[11]|numeric',
            'punch_type' => 'required|in_list[entrada,saida,intervalo_inicio,intervalo_fim]',
        ]);

        if (! $validation['valid']) {
            return $this->invalidDataResponse($validation['errors']);
        }

        $employee = $this->punchService->findEmployeeByCpf($cpfDigits);

        if (! $employee || ! $employee->active) {
            $this->auditModel->log(null, 'PUNCH_FAILED_CPF', 'time_punches', null, null, ['cpf' => $cpfDigits], 'Tentativa de registro com CPF inválido/inativo.', 'warning');
            return $this->errorResult('CPF inválido ou funcionário inativo.', 404);
        }

        return $this->processPunchRegistration((int) $employee->id, (string) $punchType, 'cpf', $request);
    }

    public function handleQrPunch(RequestInterface $request, string $qrData, ?string $punchType): array
    {
        $validation = $this->validateRequiredPunchInput([
            'qr_data' => $qrData,
            'punch_type' => $punchType,
        ], [
            'qr_data' => 'required',
            'punch_type' => 'required|in_list[entrada,saida,intervalo_inicio,intervalo_fim]',
        ]);

        if (! $validation['valid']) {
            return $this->invalidDataResponse($validation['errors']);
        }

        try {
            $qrValidation = $this->punchService->validateQrToken($qrData);
            if (! ($qrValidation['valid'] ?? false)) {
                $error = $qrValidation['error'] ?? 'QR Code inválido.';
                $this->auditModel->log(null, 'PUNCH_FAILED', 'time_punches', null, null, ['flow' => 'qrcode'], "Tentativa de registro com QR Code inválido: {$error}", 'warning');
                return $this->errorResult($error, 400);
            }

            $employee = $qrValidation['employee'] ?? null;
            if (! $employee || ! $employee->active) {
                return $this->errorResult('Funcionário não encontrado ou inativo.', 404);
            }

            $result = $this->processPunchRegistration((int) $employee->id, (string) $punchType, 'qrcode', $request);

            // Só marca o token como consumido se o registro realmente foi efetuado —
            // caso contrário (ex.: sequência de ponto inválida), o funcionário precisa
            // poder tentar de novo com o mesmo QR Code dentro da janela de 5 minutos.
            if (($result['success'] ?? false) && ! empty($qrValidation['jti'])) {
                $this->punchService->markQrTokenUsed((string) $qrValidation['jti'], (int) $employee->id);
            }

            return $result;
        } catch (\Throwable $e) {
            log_message('error', 'QR validation flow failed: ' . $e->getMessage());
            return $this->errorResult('Erro ao validar QR Code.', 500);
        }
    }

    public function handleFingerprintPunch(RequestInterface $request, string $template, ?string $punchType): array
    {
        $validation = $this->validateRequiredPunchInput([
            'template' => trim($template),
            'punch_type' => $punchType,
        ], [
            'template' => 'required',
            'punch_type' => 'required|in_list[entrada,saida,intervalo_inicio,intervalo_fim]',
        ]);

        if (! $validation['valid']) {
            return $this->invalidDataResponse($validation['errors']);
        }

        $match = $this->punchService->identifyFingerprint(trim($template));
        $employeeId = $match['employee_id'] ?? null;

        if (! $employeeId) {
            $this->auditModel->log(null, 'PUNCH_FAILED', 'time_punches', null, null, ['flow' => 'biometria'], 'Tentativa de registro por biometria digital sem reconhecimento', 'warning');
            return $this->errorResult('Biometria não reconhecida. Tente novamente.', 404);
        }

        $employee = $this->employeeModel->find((int) $employeeId);
        if (! $employee || ! $employee->active) {
            return $this->errorResult('Funcionário não encontrado ou inativo.', 404);
        }

        return $this->processPunchRegistration((int) $employeeId, (string) $punchType, 'biometria', $request, [
            'biometric_score' => $match['score'] ?? null,
        ]);
    }

    public function handleFacialPunch(RequestInterface $request, string $photoBase64, ?string $punchType, array $context = []): array
    {
        if (trim($photoBase64) === '') {
            return $this->errorResult('Foto é obrigatória.', 400);
        }

        try {
            $faceService = service('faceRecognition') ?: new \App\Services\Biometric\FaceRecognitionService();
            $recognition = $faceService->recognizeFace($photoBase64);

            if (! ($recognition['success'] ?? false)) {
                $this->logFacialRecognitionAttempt(null, false, 0, 'punch', $recognition['error'] ?? 'Erro desconhecido', $request);
                return $this->errorResult($recognition['error'] ?? 'Erro ao processar reconhecimento facial.', 500);
            }

            if (! ($recognition['recognized'] ?? false)) {
                $this->logFacialRecognitionAttempt(null, false, 0, 'punch', 'Rosto não reconhecido', $request);
                $this->auditModel->log(null, 'PUNCH_FAILED', 'time_punches', null, null, ['flow' => 'facial'], 'Tentativa de registro facial sem reconhecimento', 'warning');
                return $this->errorResult('Rosto não reconhecido. Cadastre sua biometria facial primeiro.', 404);
            }

            $employeeId = (int) ($recognition['employee_id'] ?? 0);
            $employee = $recognition['employee'] ?? null;
            $similarity = (float) ($recognition['similarity'] ?? 0);

            if (! $employee || ! $employee->active) {
                $this->logFacialRecognitionAttempt($employeeId ?: null, false, $similarity, 'punch', 'Colaborador inativo', $request);
                return $this->errorResult('Funcionário não encontrado ou inativo.', 404);
            }

            $this->logFacialRecognitionAttempt($employeeId, true, $similarity, 'punch', null, $request);

            $resolvedType = $this->resolvePunchType($punchType, $employeeId);

            $latitude = $context['latitude'] ?? null;
            $longitude = $context['longitude'] ?? null;
            if ($this->settingModel->get('require_geolocation', false) && ($latitude === null || $longitude === null || $latitude === '' || $longitude === '')) {
                return $this->errorResult('Geolocalização é obrigatória para registro de ponto.', 400);
            }

            $result = $this->processPunchRegistration($employeeId, $resolvedType, 'facial', $request, [
                'face_similarity' => $similarity,
                'location_lat' => $latitude,
                'location_lng' => $longitude,
            ]);

            if ($result['success'] ?? false) {
                $result['data']['employee_name'] = $employee->name;
                $result['data']['similarity'] = $similarity;
                $result['data']['punch_type_label'] = $this->getPunchTypeLabel($resolvedType);
                $result['data']['punch_time'] = date('d/m/Y H:i:s');
            }

            return $result;
        } catch (\Throwable $e) {
            log_message('error', 'Facial punch flow failed: ' . $e->getMessage());
            $this->logFacialRecognitionAttempt(null, false, 0, 'punch', $e->getMessage(), $request);
            return $this->errorResult('Erro ao processar reconhecimento facial.', 500);
        }
    }

    public function validateKioskToken(?string $token, string $clientIp, ?RequestInterface $request = null): bool
    {
        if ($request !== null) {
            return (new PublicTerminalSecurityService())->validateKioskToken($token, $request);
        }

        return $this->punchService->validateKioskToken($token, $clientIp);
    }

    public function generateKioskToken(RequestInterface|string $requestOrIp): array
    {
        if ($requestOrIp instanceof RequestInterface) {
            return (new PublicTerminalSecurityService())->issueToken($requestOrIp);
        }

        return $this->punchService->generateKioskToken((string) $requestOrIp);
    }

    protected function processPunchRegistration(int $employeeId, string $punchType, string $method, RequestInterface $request, array $additionalData = []): array
    {
        $result = $this->punchService->registerPunch($employeeId, $punchType, $method, $request, $additionalData);

        if (! ($result['success'] ?? false)) {
            $action = ((int) ($result['status'] ?? 400) >= 500) ? 'PUNCH_INCONSISTENCY' : 'PUNCH_ATTEMPT_BLOCKED';
            $this->auditModel->log(
                $employeeId,
                $action,
                'time_punches',
                null,
                null,
                [
                    'flow' => $method,
                    'punch_type' => $punchType,
                    'status' => $result['status'] ?? 400,
                    'errors' => $result['errors'] ?? null,
                ],
                $result['message'] ?? 'Falha na tentativa de registro de ponto.',
                'warning'
            );
        }

        return $result;
    }

    protected function validateRequiredPunchInput(array $data, array $rules): array
    {
        $validator = \Config\Services::validation();
        $validator->setRules($rules);

        return [
            'valid' => $validator->run($data),
            'errors' => $validator->getErrors(),
        ];
    }

    protected function invalidDataResponse(array $errors): array
    {
        return [
            'success' => false,
            'status' => 400,
            'message' => 'Dados inválidos.',
            'errors' => $errors,
        ];
    }

    protected function errorResult(string $message, int $status, ?array $errors = null): array
    {
        return [
            'success' => false,
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
        ];
    }

    protected function resolvePunchType(?string $punchType, int $employeeId): string
    {
        if ($punchType && in_array($punchType, ['entrada', 'saida', 'intervalo_inicio', 'intervalo_fim'], true)) {
            return $punchType;
        }

        return $this->punchService->determinePunchType($employeeId);
    }

    protected function getPunchTypeLabel(string $punchType): string
    {
        $labels = [
            'entrada' => 'ENTRADA',
            'saida' => 'SAÍDA',
            'intervalo_inicio' => 'INTERVALO - INÍCIO',
            'intervalo_fim' => 'INTERVALO - FIM',
        ];

        return $labels[$punchType] ?? strtoupper($punchType);
    }

    protected function logFacialRecognitionAttempt(?int $employeeId, bool $success, float $similarity, string $action, ?string $errorMessage, RequestInterface $request): void
    {
        $db = \Config\Database::connect();

        try {
            $threshold = $this->settingModel->get('facial_recognition_threshold', 0.70);
            $db->table('facial_recognition_logs')->insert([
                'employee_id' => $employeeId,
                'action' => $action,
                'success' => $success ? 1 : 0,
                'similarity_score' => $similarity,
                'threshold_used' => $threshold,
                'ip_address' => $request->getIPAddress(),
                'user_agent' => (string) $request->getUserAgent()->getAgentString(),
                'error_message' => $errorMessage,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to log facial recognition attempt: ' . $e->getMessage());
        }
    }
}
