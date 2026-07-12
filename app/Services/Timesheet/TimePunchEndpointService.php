<?php
// ARQ-03 FIX: Este arquivo é o núcleo real do serviço.
// A nomenclatura "LegacyCore" é um artefato histórico — não indica código obsoleto.
// Roadmap: renomear para *ServiceCore e a subclasse pública para *Service
// na próxima refatoração maior. (Ver SECURITY_AUDIT_IMPLEMENTATION.md)


namespace App\Services\Timesheet;

use App\Services\Timesheet\Endpoint\TimePunchEndpointFaceGuard;
use App\Services\Timesheet\Endpoint\TimePunchEndpointInputResolver;
use App\Services\Timesheet\Endpoint\TimePunchEndpointPunchProcessor;
use App\Services\Timesheet\Endpoint\TimePunchEndpointQrService;
use App\Services\Timesheet\Endpoint\TimePunchEndpointResultFactory;
use App\Services\Timesheet\Endpoint\TimePunchIntegrityService;
use App\Services\Timesheet\Endpoint\TimePunchReceiptService;
use CodeIgniter\HTTP\RequestInterface;

class TimePunchEndpointService
{
    private TimePunchEndpointPunchProcessor $punchProcessor;
    private TimePunchReceiptService $receiptService;
    private TimePunchIntegrityService $integrityService;
    private TimePunchEndpointQrService $qrService;

    public function __construct(
        private readonly PunchService $punchService = new PunchService(),
        private readonly TimePunchFlowService $flowService = new TimePunchFlowService(),
        private readonly TimePunchEndpointInputResolver $inputResolver = new TimePunchEndpointInputResolver(),
        private readonly TimePunchEndpointResultFactory $resultFactory = new TimePunchEndpointResultFactory(),
        private readonly ?TimePunchEndpointFaceGuard $faceGuard = null,
        ?TimePunchEndpointPunchProcessor $punchProcessor = null,
        ?TimePunchReceiptService $receiptService = null,
        ?TimePunchIntegrityService $integrityService = null,
        ?TimePunchEndpointQrService $qrService = null,
    ) {
        $this->punchProcessor = $punchProcessor ?? new TimePunchEndpointPunchProcessor(
            $this->flowService,
            $this->inputResolver,
            $this->resultFactory,
            $this->faceGuard,
        );

        $this->receiptService = $receiptService ?? new TimePunchReceiptService(
            new \App\Models\TimePunchModel(),
            new \App\Models\AuditModel(),
            new \App\Models\SettingModel(),
            $this->resultFactory,
        );

        $this->integrityService = $integrityService ?? new TimePunchIntegrityService(
            new \App\Models\TimePunchModel(),
            new \App\Models\EmployeeModel(),
            $this->resultFactory,
        );

        $this->qrService = $qrService ?? new TimePunchEndpointQrService();
    }

    public function getEnabledPunchMethods(): array
    {
        return $this->punchService->getEnabledPunchMethods();
    }

    public function isKioskMode(string $uri): bool
    {
        $normalized = trim($uri, '/');

        if ($normalized === '') {
            return false;
        }

        if (str_starts_with($normalized, 'punch-terminal')) {
            return true;
        }

        return in_array($normalized, [
            'operacao/terminal',
            'operacao/terminal-publico',
            'timesheet/punch-kiosk',
            'timesheet/punch-terminal',
        ], true);
    }

    public function dispatchPunch(RequestInterface $request, string $uri): array
    {
        return $this->punchProcessor->dispatchPunch($request, $uri);
    }

    public function handleCodePunch(RequestInterface $request): array
    {
        return $this->punchProcessor->handleCodePunch($request);
    }

    public function handleCpfPunch(RequestInterface $request): array
    {
        return $this->punchProcessor->handleCpfPunch($request);
    }

    public function handleQrPunch(RequestInterface $request): array
    {
        return $this->punchProcessor->handleQrPunch($request);
    }

    public function handleFacePunch(RequestInterface $request, string $uri): array
    {
        return $this->punchProcessor->handleFacePunch($request, $uri);
    }

    public function handleFingerprintPunch(RequestInterface $request): array
    {
        return $this->punchProcessor->handleFingerprintPunch($request);
    }

    public function generateKioskToken(\CodeIgniter\HTTP\RequestInterface|string $requestOrIp): array
    {
        return $this->flowService->generateKioskToken($requestOrIp);
    }

    public function generateQrCodeForEmployee(int $employeeId): array
    {
        return $this->qrService->generateForEmployee($employeeId);
    }

    public function generateReceipt(int $punchId, int $actorEmployeeId, bool $canManage = false): array
    {
        return $this->receiptService->generateReceipt($punchId, $actorEmployeeId, $canManage);
    }

    public function verifyHash(int $punchId, ?int $actorEmployeeId = null, string $actorRole = '', string $actorDepartment = ''): array
    {
        return $this->integrityService->verifyHash($punchId, $actorEmployeeId, $actorRole, $actorDepartment);
    }

    public function validatePunchByNsr(int $nsr, ?int $actorEmployeeId = null, bool $canManage = false): array
    {
        return $this->integrityService->validatePunchByNsr($nsr, $actorEmployeeId, $canManage);
    }

    public function validatePunchByNsrPublic(int $nsr): array
    {
        return $this->integrityService->validatePunchByNsrPublic($nsr);
    }

    public function resolveReceiptPath(string $year, string $month, string $filename, int $actorEmployeeId, bool $canManage = false): array
    {
        return $this->receiptService->resolveReceiptPath($year, $month, $filename, $actorEmployeeId, $canManage);
    }
}
