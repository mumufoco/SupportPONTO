<?php

namespace App\Services\Timesheet;

use App\Enums\PunchMethod;
use App\Enums\PunchType;
use App\Models\AuditModel;
use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Models\TimePunchModel;
use App\Services\GeolocationService;
use App\Services\QRCodeService;
use App\Services\Biometric\FingerprintMatchingService;
use App\DTO\Timesheet\PunchRegistrationCommand;
use CodeIgniter\HTTP\RequestInterface;

class PunchService
{
    protected EmployeeModel $employeeModel;
    protected TimePunchModel $timePunchModel;
    protected AuditModel $auditModel;
    protected SettingModel $settingModel;
    protected GeolocationService $geolocationService;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->timePunchModel = new TimePunchModel();
        $this->auditModel = new AuditModel();
        $this->settingModel = new SettingModel();
        $this->geolocationService = new GeolocationService();
    }

    public function getEnabledPunchMethods(): array
    {
        return [
            'codigo' => $this->settingModel->get('punch_method_code_enabled', true),
            'cpf' => $this->settingModel->get('punch_method_cpf_enabled', true),
            'qrcode' => $this->settingModel->get('punch_method_qr_enabled', true),
            'facial' => $this->settingModel->get('punch_method_face_enabled', true),
            'biometria' => $this->settingModel->get('punch_method_fingerprint_enabled', false),
        ];
    }

    public function findEmployeeByCode(string $uniqueCode): ?object
    {
        return $this->employeeModel->findByCode($uniqueCode);
    }

    public function findEmployeeByCpf(string $cpfDigits): ?object
    {
        // MED-11 (auditoria): cpf agora guarda o valor criptografado (nonce aleatório),
        // não pesquisável diretamente por REPLACE/comparação de string. Usa o
        // cpf_hash determinístico via EmployeeModel::findByCpf().
        return $this->employeeModel->findByCpf($cpfDigits);
    }

    public function validateQrToken(string $qrData): array
    {
        $qrService = new QRCodeService();
        return $qrService->validateToken($qrData);
    }

    /**
     * Marca o token de QR Code como usado após um registro de ponto bem-sucedido.
     *
     * Achado na auditoria: QRCodeService::validateToken() checa isTokenUsed(jti),
     * mas nada no fluxo de /timesheet/punch chamava markTokenAsUsed() depois de
     * um registro válido — só o fluxo separado /qrcode/validate (scanner de
     * terminal) fazia essa marcação. Na prática, o mesmo QR Code (válido por 5
     * minutos) podia ser reaproveitado várias vezes dentro da janela de validade,
     * já que a tabela qrcode_used_tokens nunca era populada por este caminho.
     */
    public function markQrTokenUsed(string $jti, int $employeeId): void
    {
        (new QRCodeService())->markTokenAsUsed($jti, $employeeId);
    }

    public function identifyFingerprint(string $template): ?array
    {
        // Fase 11: identificação digital usa somente engine biométrica dedicada.
        // Comparações textuais/heurísticas continuam proibidas em produção.
        $templates = (new BiometricTemplateModel())
            ->where('biometric_type', 'fingerprint')
            ->groupStart()
                ->where('active', true)
                ->orWhere('is_active', true)
            ->groupEnd()
            ->findAll();

        if ($templates === []) {
            log_message('warning', 'Fingerprint punch bloqueado: nenhum template ativo encontrado.');
            return null;
        }

        $match = (new FingerprintMatchingService())->bestMatch($template, $templates);
        $threshold = (float) (env('SOURCEAFIS_THRESHOLD') ?: env('sourceafis.threshold') ?: 0.40);
        $score = (float) ($match['similarity'] ?? 0.0);

        if (($match['employee_id'] ?? null) === null || $score < $threshold) {
            log_message('warning', 'Fingerprint punch sem correspondência suficiente.', [
                'score' => $score,
                'threshold' => $threshold,
            ]);
            return null;
        }

        return [
            'employee_id' => (int) $match['employee_id'],
            'template_id' => $match['template_id'] ?? null,
            'score' => $score,
            'engine' => 'sourceafis',
        ];
    }

    public function determinePunchType(int $employeeId): string
    {
        return $this->timePunchModel->getNextPunchType($employeeId, date('Y-m-d'))->value;
    }

    public function validatePunchAttempt(int $employeeId, string $punchType, ?string $date = null): array
    {
        $normalizedPunchType = $this->timePunchModel->normalizePunchType($punchType);
        $resolvedPunchType = PunchType::tryFrom($normalizedPunchType);

        if (! $resolvedPunchType) {
            return [
                'valid' => false,
                'status' => 400,
                'message' => 'Tipo de marcação inválido.',
                'errors' => ['punch_type' => 'Tipo de marcação inválido.'],
            ];
        }

        if (! $this->timePunchModel->canPunch($employeeId)) {
            return [
                'valid' => false,
                'status' => 429,
                'message' => 'Já existe uma marcação muito recente para este colaborador. Aguarde 1 minuto antes de tentar novamente.',
                'errors' => ['cooldown' => true],
            ];
        }

        $referenceDate = $date ?: date('Y-m-d');
        $expectedType = $this->timePunchModel->getNextPunchType($employeeId, $referenceDate);

        if ($resolvedPunchType !== $expectedType) {
            return [
                'valid' => false,
                'status' => 422,
                'message' => sprintf(
                    'Sequência de ponto inválida. O próximo tipo esperado é %s.',
                    mb_strtolower($expectedType->label())
                ),
                'errors' => [
                    'punch_type' => 'Sequência de ponto inválida.',
                    'expected_punch_type' => $expectedType->value,
                    'expected_punch_type_label' => $expectedType->label(),
                    'received_punch_type' => $resolvedPunchType->value,
                ],
            ];
        }

        return [
            'valid' => true,
            'punch_type' => $resolvedPunchType->value,
        ];
    }

    public function registerPunch(int $employeeId, string $punchType, string $method, RequestInterface $request, array $additionalData = []): array
    {
        $command = PunchRegistrationCommand::fromRequest(
            $employeeId,
            $punchType,
            $method,
            $request,
            $additionalData,
            [],
            'web'
        );

        return (new TimesheetPunchRegistrationService())->register($command);
    }

    public function validateKioskToken(?string $token, string $clientIp): bool
    {
        if (empty($token)) {
            return false;
        }

        $authorizedKiosks = $this->settingModel->get('authorized_kiosk_ips', '');
        if (! empty($authorizedKiosks)) {
            $allowedIps = array_map('trim', explode(',', $authorizedKiosks));
            if (! in_array($clientIp, $allowedIps, true) && ! ((ENVIRONMENT ?? 'production') !== 'production' && in_array('*', $allowedIps, true))) {
                log_message('warning', "Kiosk access attempt from unauthorized IP: {$clientIp}");
                return false;
            }
        }

        $encryptionKey = env('app.encryption.key') ?? env('encryption.key');
        if (empty($encryptionKey)) {
            log_message('error', 'Encryption key not configured for kiosk token validation');
            return false;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 4) {
            return false;
        }

        [$timestamp, $ipHash, $nonce, $signature] = $parts;
        if ((time() - (int) $timestamp) > 3600) {
            return false;
        }

        $expectedIpHash = substr(hash('sha256', $clientIp), 0, 16);
        if (! hash_equals($expectedIpHash, $ipHash)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . $ipHash . $nonce, $encryptionKey);
        return hash_equals($expectedSignature, $signature);
    }

    public function generateKioskToken(string $clientIp): array
    {
        $authorizedKiosks = $this->settingModel->get('authorized_kiosk_ips', '');
        if (empty($authorizedKiosks)) {
            return ['success' => false, 'status' => 403, 'message' => 'Terminal de ponto não configurado. Contate o administrador.'];
        }

        $allowedIps = array_map('trim', explode(',', $authorizedKiosks));
        if (! in_array($clientIp, $allowedIps, true) && ! ((ENVIRONMENT ?? 'production') !== 'production' && in_array('*', $allowedIps, true))) {
            return ['success' => false, 'status' => 403, 'message' => 'Terminal não autorizado.'];
        }

        $encryptionKey = env('app.encryption.key') ?? env('encryption.key');
        if (empty($encryptionKey)) {
            return ['success' => false, 'status' => 500, 'message' => 'Configuração de segurança ausente.'];
        }

        $timestamp = time();
        $ipHash = substr(hash('sha256', $clientIp), 0, 16);
        $nonce = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $timestamp . $ipHash . $nonce, $encryptionKey);

        return [
            'success' => true,
            'status' => 200,
            'message' => 'Token gerado com sucesso.',
            'data' => [
                'kiosk_token' => "{$timestamp}.{$ipHash}.{$nonce}.{$signature}",
                'expires_at' => date('Y-m-d H:i:s', $timestamp + 3600),
            ],
        ];
    }
}
