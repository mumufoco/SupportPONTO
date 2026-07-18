<?php

namespace App\Services\Timesheet\Endpoint;

use App\Models\EmployeeModel;
use App\Models\TimePunchModel;

class TimePunchIntegrityService
{
    private const PUBLIC_VALIDATION_VERSION = '1';

    public function __construct(
        private readonly TimePunchModel $timePunchModel,
        private readonly EmployeeModel $employeeModel,
        private readonly TimePunchEndpointResultFactory $resultFactory = new TimePunchEndpointResultFactory(),
    ) {
    }

    public function verifyHash(int $punchId, ?int $actorEmployeeId = null, string $actorRole = '', string $actorDepartment = ''): array
    {
        $punch = $this->timePunchModel->find($punchId);
        if (!$punch) {
            return $this->resultFactory->error('Registro não encontrado.', 404);
        }

        if (!$this->canActorAccessPunch($punch, $actorEmployeeId, $actorRole, $actorDepartment)) {
            return $this->resultFactory->error('Você não possui permissão para verificar este registro.', 403);
        }

        $isValid = $this->timePunchModel->verifyHash($punch);

        return [
            'success' => true,
            'status' => 200,
            'message' => $isValid ? 'Hash válido.' : 'Hash inválido!',
            'data' => [
                'punch_id' => $punchId,
                'nsr' => $punch->nsr,
                'hash' => $punch->hash,
                'is_valid' => $isValid,
            ],
        ];
    }

    public function validatePunchByNsr(int $nsr, ?int $actorEmployeeId = null, bool $canManage = false): array
    {
        $punch = $this->timePunchModel->where('nsr', (string) $nsr)->first();
        if (!$punch) {
            return $this->resultFactory->error('Registro não encontrado.', 404);
        }

        if (!$canManage && ($actorEmployeeId === null || (int) $punch->employee_id !== $actorEmployeeId)) {
            return $this->resultFactory->error('Você não possui permissão para validar este registro.', 403);
        }

        $isValid = $this->timePunchModel->verifyHash($punch);

        return [
            'success' => true,
            'status' => 200,
            'message' => $isValid ? 'Hash válido.' : 'Hash inválido!',
            'data' => [
                'nsr' => $punch->nsr,
                'employee_id' => $punch->employee_id,
                'punch_time' => $punch->punch_time,
                'punch_type' => $punch->punch_type,
                'method' => $punch->method,
                'is_valid' => $isValid,
            ],
        ];
    }

    public function validatePunchByNsrPublic(int $nsr): array
    {
        $punch = $this->timePunchModel->where('nsr', (string) $nsr)->first();
        if (!$punch) {
            return $this->resultFactory->error('Registro não encontrado.', 404);
        }

        $isValid = $this->timePunchModel->verifyHash($punch);
        $signature = $this->buildPublicValidationSignature((int) $punch->nsr, (string) $punch->hash, $isValid);

        return [
            'success' => true,
            'status' => 200,
            'message' => $isValid ? 'Hash válido.' : 'Hash inválido!',
            'data' => [
                'nsr' => $punch->nsr,
                'is_valid' => $isValid,
                'signature_available' => $signature !== null,
                'validation_signature' => $signature,
                'signature_version' => $signature !== null ? self::PUBLIC_VALIDATION_VERSION : null,
                'signature_reason' => $signature !== null ? 'configured' : 'missing_public_validation_secret',
            ],
        ];
    }


    private function canActorAccessPunch(object $punch, ?int $actorEmployeeId, string $actorRole, string $actorDepartment): bool
    {
        $normalizedRole = strtolower(trim($actorRole));

        if (in_array($normalizedRole, ['admin', 'rh'], true)) {
            return true;
        }

        if ($normalizedRole === 'gestor') {
            $targetEmployee = $this->employeeModel->find((int) $punch->employee_id);
            if (!$targetEmployee) {
                return false;
            }

            return (string) ($targetEmployee->department ?? '') !== ''
                && (string) ($targetEmployee->department ?? '') === $actorDepartment;
        }

        return $actorEmployeeId !== null && (int) $punch->employee_id === $actorEmployeeId;
    }

    private function buildPublicValidationSignature(int $nsr, string $hash, bool $isValid): ?string
    {
        $secret = trim((string) (env('QR_SECRET_KEY') ?: ''));
        if ($secret == '') {
            return null;
        }

        $payload = implode('|', [self::PUBLIC_VALIDATION_VERSION, (string) $nsr, $hash, $isValid ? '1' : '0']);

        return hash_hmac('sha256', $payload, $secret);
    }
}
