<?php

namespace App\Controllers\Timesheet;

use App\Controllers\BaseController;
use App\Models\HolidayModel;
use App\Models\TimePunchModel;
use App\Services\Timesheet\PendingPunchAttemptStore;
use App\Services\Timesheet\PendingPunchService;
use App\Services\Timesheet\PunchMethodReadinessService;
use App\Services\Timesheet\TimePunchEndpointService;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class TimePunchController extends BaseController
{
    protected TimePunchModel $timePunchModel;
    protected TimePunchEndpointService $endpointService;
    protected PunchMethodReadinessService $punchMethodReadiness;
    protected PendingPunchAttemptStore $pendingPunchAttemptStore;
    protected PendingPunchService $pendingPunchService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->timePunchModel = new TimePunchModel();
        $this->endpointService = new TimePunchEndpointService();
        $this->punchMethodReadiness = new PunchMethodReadinessService();
        $this->pendingPunchAttemptStore = new PendingPunchAttemptStore();
        $this->pendingPunchService = new PendingPunchService();
    }

    public function index()
    {
        $uri = uri_string();

        if ($this->endpointService->isManagerOnlyKioskRoute($uri) && !$this->hasAnyRole(['admin', 'gestor'])) {
            $this->session->setFlashdata('error', 'Você não tem permissão para acessar o Terminal.');
            return redirect()->to(site_url('timesheet/punch'));
        }

        $isKioskMode    = $this->endpointService->isKioskMode($uri);
        $enabledMethods = $this->endpointService->getEnabledPunchMethods();

        $today          = date('Y-m-d');
        $holidayModel   = new HolidayModel();
        $todayHoliday   = $holidayModel->getHolidayInfo($today);

        $data = [
            'enabledMethods'    => $enabledMethods,
            'methodReadiness'   => $this->punchMethodReadiness->summary($isKioskMode, false),
            'isKioskMode'       => $isKioskMode,
            'todayHoliday'      => $todayHoliday,
            'canOverride'       => $this->isManager(),
            'canAccessTerminal' => $this->hasAnyRole(['admin', 'gestor']),
        ];

        return $isKioskMode
            ? view('timesheet/punch_kiosk', $data)
            : view('timesheet/punch', $data);
    }

    public function quickAccess()
    {
        return view('timesheet/quick_access', [
            'enabledMethods' => $this->endpointService->getEnabledPunchMethods(),
            'methodReadiness' => $this->punchMethodReadiness->summary(false, true),
        ]);
    }


    public function capabilities()
    {
        return $this->respondSuccess([
            'methods' => $this->punchMethodReadiness->summary($this->endpointService->isKioskMode(uri_string()), false),
            'enabled_methods' => $this->endpointService->getEnabledPunchMethods(),
            'is_kiosk_mode' => $this->endpointService->isKioskMode(uri_string()),
        ], 'Capacidades dos métodos de ponto carregadas com sucesso.');
    }

    public function punch()
    {
        try {
            return $this->respondPunchResult($this->endpointService->dispatchPunch($this->request, uri_string()));
        } catch (\Throwable $e) {
            log_message('error', 'TimePunchController::punch fatal error: ' . $e->getMessage());
            return $this->respondError('Erro interno ao processar registro de ponto.', null, 500);
        }
    }

    public function myPunches()
    {
        $this->requireAuth();

        $monthRange = get_month_datetime_range($this->request->getGet('month'));

        $punches = $this->timePunchModel
            ->where('employee_id', $this->currentUser->id)
            ->where('punch_time >=', $monthRange['start_at'])
            ->where('punch_time <', $monthRange['end_at'])
            ->orderBy('punch_time', 'DESC')
            ->paginate(50);

        return view('timesheet/my_punches', [
            'punches' => $punches,
            'pager' => $this->timePunchModel->pager,
            'currentMonth' => $monthRange['month'],
        ]);
    }

    public function punchByCode()
    {
        return $this->safePunchResponse(fn () => $this->endpointService->handleCodePunch($this->request));
    }

    public function punchByCpf()
    {
        return $this->safePunchResponse(fn () => $this->endpointService->handleCpfPunch($this->request));
    }

    public function punchByQRCode()
    {
        return $this->safePunchResponse(fn () => $this->endpointService->handleQrPunch($this->request));
    }

    public function punchByFace()
    {
        return $this->safePunchResponse(fn () => $this->endpointService->handleFacePunch($this->request, uri_string()));
    }

    public function punchByFingerprint()
    {
        return $this->safePunchResponse(fn () => $this->endpointService->handleFingerprintPunch($this->request));
    }

    public function generateKioskToken()
    {
        return $this->respondPunchResult($this->endpointService->generateKioskToken($this->request));
    }

    public function generateQRCode()
    {
        $this->requireAuth();
        return $this->respondPunchResult($this->endpointService->generateQrCodeForEmployee((int) $this->currentUser->id));
    }

    public function generateReceipt(int $punchId)
    {
        $this->requireAuth();

        return $this->respondPunchResult($this->endpointService->generateReceipt(
            $punchId,
            (int) ($this->currentUser->id ?? 0),
            (string) ($this->currentUser->role ?? ''),
            (string) ($this->currentUser->department ?? '')
        ));
    }

    public function receipt(int $punchId)
    {
        return $this->generateReceipt($punchId);
    }

    public function downloadReceipt(string $year, string $month, string $filename)
    {
        $this->requireAuth();

        $result = $this->endpointService->resolveReceiptPath(
            $year,
            $month,
            $filename,
            (int) ($this->currentUser->id ?? 0),
            (string) ($this->currentUser->role ?? ''),
            (string) ($this->currentUser->department ?? '')
        );

        if (!($result['success'] ?? false)) {
            return $this->respondPunchResult($result);
        }

        $filepath = (string) ($result['data']['filepath'] ?? '');
        if ($filepath === '' || !file_exists($filepath)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Comprovante não encontrado.');
        }

        return $this->response->download($filepath, null)->setFileName($filename);
    }

    public function verifyHash(int $punchId)
    {
        $this->requireManager();
        return $this->respondPunchResult($this->endpointService->verifyHash(
            $punchId,
            $this->currentUser ? (int) $this->currentUser->id : null,
            (string) ($this->currentUser->role ?? ''),
            (string) ($this->currentUser->department ?? '')
        ));
    }

    public function validatePunchByNsr(int $nsr)
    {
        $this->requireAuth();

        return $this->respondPunchResult($this->endpointService->validatePunchByNsr(
            $nsr,
            $this->currentUser ? (int) $this->currentUser->id : null,
            $this->isManager()
        ));
    }

    public function validatePunchByNsrPublic(int $nsr)
    {
        return $this->respondPunchResult($this->endpointService->validatePunchByNsrPublic($nsr));
    }


    protected function safePunchResponse(callable $callback)
    {
        try {
            return $this->respondPunchResult($callback());
        } catch (\Throwable $e) {
            log_message('error', 'TimePunchController request failed: ' . $e->getMessage());
            return $this->respondError('Erro interno ao processar registro de ponto.', null, 500);
        }
    }

    protected function respondPunchResult(array $result)
    {
        if (!($result['success'] ?? false)) {
            return $this->respondPunchFailure($result);
        }

        $this->clearPendingPunchAttempts();

        return $this->respondSuccess($result['data'] ?? [], (string) ($result['message'] ?? 'OK'), (int) ($result['status'] ?? 200));
    }

    protected function respondPunchFailure(array $result): ResponseInterface
    {
        $status = (int) ($result['status'] ?? 400);
        $message = (string) ($result['message'] ?? 'Falha ao registrar ponto.');
        $errors = $result['errors'] ?? null;
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $this->sanitizeForLog($errors);
        }

        $attemptMeta = $this->capturePendingPunchAttempt($result);
        if ($attemptMeta !== null) {
            $payload['data'] = $attemptMeta;
        }

        return $this->attachResponseContext($this->response->setStatusCode($status)->setJSON($payload), true);
    }

    protected function clearPendingPunchAttempts(): void
    {
        if (!isset($this->currentUser->id) || !is_numeric($this->currentUser->id)) {
            return;
        }

        $this->pendingPunchAttemptStore->clearForEmployee((int) $this->currentUser->id);
    }

    protected function capturePendingPunchAttempt(array $result): ?array
    {
        if (!isset($this->currentUser->id) || !is_numeric($this->currentUser->id)) {
            return null;
        }

        $path = trim((string) $this->request->getUri()->getPath(), '/');
        if (!str_starts_with($path, 'timesheet/punch')) {
            return null;
        }

        $method = $this->resolvePunchAttemptMethod($path);
        if ($method === null) {
            return null;
        }

        $errorCode = $this->resolvePunchFailureCode($method, $result);
        if ($errorCode === null) {
            return null;
        }

        $enabledSummary = (array) ($this->punchMethodReadiness->summary(false, false)['enabled'] ?? []);
        $enabledMethods = array_keys(array_filter($enabledSummary));

        $context = $this->pendingPunchAttemptStore->registerAttempt((int) $this->currentUser->id, [
            'method' => $method,
            'error_code' => $errorCode,
            'message' => (string) ($result['message'] ?? 'Falha ao registrar ponto.'),
            'status' => (int) ($result['status'] ?? 400),
        ], $enabledMethods);

        $eligibility = $this->pendingPunchService->evaluateEligibility((int) $this->currentUser->id, $context['attempt_log'] ?? [], $enabledMethods);
        $this->pendingPunchAttemptStore->updateEligibility((int) $this->currentUser->id, $eligibility);

        return [
            'pending_punch' => [
                'attempt_registered' => true,
                'method' => $method,
                'error_code' => $errorCode,
                'eligible' => (bool) ($eligibility['eligible'] ?? false),
                'blocked' => (bool) ($eligibility['blocked'] ?? false),
                'block_reason' => $eligibility['block_reason'] ?? null,
                'technical_failures' => (int) ($eligibility['technical_failures'] ?? 0),
                'methods_tried' => $eligibility['methods_tried'] ?? [],
                'justify_url' => sp_timesheet_justify_url(),
            ],
        ];
    }

    protected function resolvePunchAttemptMethod(string $path): ?string
    {
        return match (true) {
            str_contains($path, '/code') => 'codigo',
            str_contains($path, '/cpf') => 'cpf',
            str_contains($path, '/qr') => 'qrcode',
            str_contains($path, '/face') => 'facial',
            str_contains($path, '/fingerprint') => 'biometria',
            default => null,
        };
    }

    protected function resolvePunchFailureCode(string $method, array $result): ?string
    {
        $status = (int) ($result['status'] ?? 400);
        $message = mb_strtolower((string) ($result['message'] ?? ''));

        if ($status >= 500) {
            return match ($method) {
                'facial' => 'deepface_unavailable',
                'biometria' => 'sourceafis_timeout',
                default => 'service_unavailable',
            };
        }

        if ($method === 'facial') {
            if (str_contains($message, 'rosto não reconhecido')) {
                return 'facial_not_recognized';
            }

            if (str_contains($message, 'foto é obrigatória')) {
                return 'camera_inaccessible';
            }
        }

        if ($method === 'biometria' && str_contains($message, 'biometria não reconhecida')) {
            return 'fingerprint_not_recognized';
        }

        if ($method === 'qrcode' && str_contains($message, 'qr')) {
            return 'invalid_qr';
        }

        if (in_array($status, [401, 403, 404, 422, 429], true)) {
            return null;
        }

        return null;
    }

}