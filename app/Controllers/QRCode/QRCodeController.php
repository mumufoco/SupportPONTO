<?php

namespace App\Controllers\QRCode;

use App\Controllers\BaseController;
use App\Services\QRCodeService;
use App\Models\TimePunchModel;
use App\Services\Audit\CanonicalAuditLogger;
use App\Services\QRCode\QRCodePunchActionService;
use App\DTO\Timesheet\PunchRegistrationCommand;
use App\Services\Timesheet\TimesheetPunchRegistrationService;

class QRCodeController extends BaseController
{
    protected QRCodeService $qrCodeService;
    protected TimePunchModel $timePunchModel;
    protected CanonicalAuditLogger $canonicalAuditLogger;
    protected QRCodePunchActionService $qrCodePunchActionService;
    protected TimesheetPunchRegistrationService $punchRegistrationService;

    public function __construct()
    {
        $this->qrCodeService = new QRCodeService();
        $this->timePunchModel = new TimePunchModel();
        $this->canonicalAuditLogger = new CanonicalAuditLogger();
        $this->qrCodePunchActionService = new QRCodePunchActionService();
        $this->punchRegistrationService = new TimesheetPunchRegistrationService();
    }

    public function myQRCode()
    {
        $employeeId = session()->get('user_id');
        
        if (!$employeeId) {
            return redirect()->to(sp_login_url());
        }

        try {
            $qrData = $this->qrCodeService->generateQRCode($employeeId);
            
            $this->attachResponseContext($this->response, true);

            return view('qrcode/my_qrcode', [
                'qr_image' => $qrData['qr_image'],
                'expires_at' => $qrData['expires_at'],
                'employee' => $qrData['employee'],
                'expiration_seconds' => $this->qrCodeService->getTokenExpiration(),
            ]);
        } catch (\Exception $e) {
            $this->logSecurityEvent('error', 'QR code generation failed', ['exception' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Não foi possível gerar o QR Code agora.');
        }
    }

    public function regenerate()
    {
        $employeeId = session()->get('user_id');
        
        if (!$employeeId) {
            return $this->attachResponseContext($this->response->setJSON(['success' => false, 'code' => 'unauthorized', 'message' => 'Não autorizado']), true);
        }

        try {
            $qrData = $this->qrCodeService->generateQRCode($employeeId);
            
            return $this->attachResponseContext($this->response->setJSON([
                'success' => true,
                'qr_image' => $qrData['qr_image'],
                'expires_at' => $qrData['expires_at'],
                'expiration_seconds' => $this->qrCodeService->getTokenExpiration(),
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);
        } catch (\Exception $e) {
            $this->logSecurityEvent('error', 'QR code regeneration failed', ['exception' => $e->getMessage()]);
            return $this->attachResponseContext($this->response->setJSON([
                'success' => false,
                'code' => 'qrcode_generation_failed',
                'message' => 'Não foi possível gerar o QR Code agora.',
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);
        }
    }

    public function scanner()
    {
        return view('qrcode/scanner', [
            'title' => 'Terminal de Ponto - QR Code',
        ]);
    }

    public function validateQRCode()
    {
        $jsonData = $this->request->getJSON(true) ?? [];
        $token = $jsonData['token'] ?? $this->request->getPost('token');
        $latitude = $jsonData['latitude'] ?? $this->request->getPost('latitude');
        $longitude = $jsonData['longitude'] ?? $this->request->getPost('longitude');
        $requestedPunchType = $jsonData['punch_type'] ?? $this->request->getPost('punch_type');
        
        if (!$token) {
            return $this->attachResponseContext($this->response->setJSON([
                'success' => false,
                'code' => 'validation_error',
                'message' => 'Token não fornecido',
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);
        }

        $validation = $this->qrCodeService->validateToken($token);
        
        if (!$validation['valid']) {
            $this->logAuditFailed($token, $validation['error']);
            
            return $this->attachResponseContext($this->response->setJSON([
                'success' => false,
                'code' => 'qrcode_invalid',
                'message' => $validation['error'],
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);
        }

        $employee = $validation['employee'];
        $jti = $validation['jti'];

        $geofenceValidation = $this->qrCodePunchActionService->validateGeofenceWithCoords($latitude, $longitude);
        if (!$geofenceValidation['valid']) {
            return $this->attachResponseContext($this->response->setJSON([
                'success' => false,
                'code' => 'geofence_denied',
                'message' => $geofenceValidation['error'],
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);
        }

        $scheduleValidation = $this->validateSchedule($employee);
        
        $punchType = $this->qrCodePunchActionService->resolvePunchType(
            (int) $employee->id,
            is_string($requestedPunchType) ? $requestedPunchType : null
        );

        try {
            $registration = $this->punchRegistrationService->register(new PunchRegistrationCommand(
                employeeId: (int) $employee->id,
                punchType: (string) $punchType,
                method: 'qrcode',
                latitude: $latitude,
                longitude: $longitude,
                ipAddress: $this->request->getIPAddress(),
                userAgent: (string) $this->request->getUserAgent()->getAgentString(),
                source: 'qrcode',
            ));

            if (! ($registration['success'] ?? false)) {
                return $this->attachResponseContext($this->response->setJSON([
                    'success' => false,
                    'code' => 'punch_registration_denied',
                    'message' => $registration['message'] ?? 'Erro ao registrar ponto.',
                    'errors' => $registration['errors'] ?? null,
                    'meta' => ['request_id' => $this->getRequestId()],
                ])->setStatusCode((int) ($registration['status'] ?? 400)), true);
            }

            $punchId = (int) ($registration['data']['punch_id'] ?? $registration['data']['id'] ?? 0);
            $this->qrCodeService->markTokenAsUsed($jti, $employee->id);
            $this->logAuditSuccess($employee, $punchId, $punchType);

            return $this->attachResponseContext($this->response->setJSON([
                'success' => true,
                'message' => 'Ponto registrado com sucesso!',
                'data' => array_merge($registration['data'] ?? [], [
                    'employee_name' => $employee->name,
                    'punch_type_label' => $this->qrCodePunchActionService->punchTypeLabel($punchType),
                ]),
                'schedule_warning' => $scheduleValidation['warning'] ?? null,
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);

        } catch (\Exception $e) {
            $this->logSecurityEvent('error', 'QR code punch failed', ['exception' => $e->getMessage()]);
            
            return $this->attachResponseContext($this->response->setJSON([
                'success' => false,
                'code' => 'punch_registration_failed',
                'message' => 'Erro ao registrar ponto.',
                'meta' => ['request_id' => $this->getRequestId()],
            ]), true);
        }
    }

    public function download()
    {
        $employeeId = session()->get('user_id');
        
        if (!$employeeId) {
            return redirect()->to(sp_login_url());
        }

        try {
            $qrData = $this->qrCodeService->generateQRCode($employeeId, true);
            $employee = $qrData['employee'];
            
            $filename = 'qrcode_' . preg_replace('/[^a-z0-9]/', '_', strtolower($employee->name)) . '.png';
            
            $imageData = $qrData['qr_image'];
            if (strpos($imageData, 'data:image/png;base64,') === 0) {
                $imageData = substr($imageData, strlen('data:image/png;base64,'));
            }
            
            return $this->attachResponseContext($this->response
                ->setHeader('Content-Type', 'image/png')
                ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->setBody(base64_decode($imageData)), true);
                
        } catch (\Exception $e) {
            $this->logSecurityEvent('error', 'QR code download failed', ['exception' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Erro ao baixar QR Code');
        }
    }

    protected function logAuditSuccess($employee, int $punchId, string $punchType): void
    {
        try {
            $this->canonicalAuditLogger->logEntityEvent(
                isset($employee->id) ? (int) $employee->id : null,
                'QRCODE_PUNCH_SUCCESS',
                'time_punches',
                $punchId,
                null,
                [
                    'employee_name' => $employee->name ?? null,
                    'punch_type' => $punchType,
                    'method' => 'qrcode',
                    'ip_address' => $this->request->getIPAddress(),
                ],
                "Ponto registrado via QR Code: {$employee->name} - {$punchType}"
            );
        } catch (\Exception $e) {
            log_message('error', 'Failed to log audit: ' . $e->getMessage());
        }
    }

    protected function logAuditFailed(string $token, string $error): void
    {
        try {
            $this->canonicalAuditLogger->logEntityEvent(
                null,
                'QRCODE_PUNCH_FAILED',
                'time_punches',
                null,
                null,
                [
                    'error' => $error,
                    'token_hash' => $this->hashValue($token),
                    'method' => 'qrcode',
                    'ip_address' => $this->request->getIPAddress(),
                ],
                "Tentativa de ponto via QR Code falhou: {$error}",
                'warning'
            );
        } catch (\Exception $e) {
            log_message('error', 'Failed to log failed audit: ' . $e->getMessage());
        }
    }
}
