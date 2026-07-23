<?php

namespace App\Services\Employees;

use Config\Services as ServiceFactory;

class EmployeeControllerActionService
{
    private readonly EmployeeCoordinatorService $coordinator;
    private readonly EmployeeIdentityService $identityService;
    private readonly EmployeeAfdEventRecorderService $afdEventRecorder;

    public function __construct(
        ?EmployeeCoordinatorService $coordinator = null,
        ?EmployeeIdentityService $identityService = null,
        ?EmployeeAfdEventRecorderService $afdEventRecorder = null,
    ) {
        $this->coordinator = $coordinator ?? ServiceFactory::employeeCoordinatorService();
        $this->identityService = $identityService ?? ServiceFactory::employeeIdentityService();
        $this->afdEventRecorder = $afdEventRecorder ?? new EmployeeAfdEventRecorderService();
    }

    public function resolveManagerAccess(?object $currentUser, int $id): array
    {
        return $this->coordinator->findEmployeeForManagerAccess($currentUser, $id);
    }

    public function showPayload(int $id): array
    {
        $overviewResult = $this->coordinator->employeeOverview($id);
        if (!($overviewResult['success'] ?? false)) {
            return $overviewResult;
        }

        $overview = $overviewResult['overview'];

        return [
            'success' => true,
            'viewData' => [
                'employee' => $overview['employee'],
                'punchedToday' => $overview['punchedToday'],
                'lastPunchToday' => $overview['lastPunchToday'],
                'hourBalance' => $overview['hourBalance'],
                'totalJustifications' => $overview['totalJustifications'],
                'totalWarnings' => $overview['totalWarnings'],
                'recentPunches' => $overview['recentPunches'],
                'recentJustifications' => $overview['recentJustifications'],
                'hasFaceBiometric' => $overview['hasFaceBiometric'],
                'hasFingerprintBiometric' => $overview['hasFingerprintBiometric'],
                'faceTemplate'            => $overview['faceTemplate'] ?? null,
                'fingerprintTemplate'     => $overview['fingerprintTemplate'] ?? null,
            ],
        ];
    }

    public function qrcodeDownloadPayload(mixed $employee): array
    {
        $employeeIdentity = $this->identityService->toArray($employee);
        $qrText = $employeeIdentity['unique_code'];

        return [
            'success' => true,
            'qr_text' => $qrText,
            'message' => 'Para fazer download, use um gerador online de QR Code com o texto: ' . $qrText,
            'filename' => 'qrcode_' . $qrText . '.png',
        ];
    }

    public function profileValidationRules(): array
    {
        return [
            'phone' => 'permit_empty|max_length[20]',
            'password' => 'permit_empty|min_length[8]',
            'password_confirm' => 'permit_empty|matches[password]',
        ];
    }

    public function passwordValidationRules(): array
    {
        return [
            'current_password' => 'required',
            'new_password' => 'required|min_length[8]|matches[confirm_password]',
            'confirm_password' => 'required',
        ];
    }

    public function isSelfAction(int $targetId, ?object $currentUser): bool
    {
        return $targetId === (int) ($currentUser->id ?? 0);
    }

    public function deleteEmployee(int $id, ?object $currentUser): array
    {
        if ($this->isSelfAction($id, $currentUser)) {
            return ['success' => false, 'self_action' => true, 'message' => 'Você não pode excluir sua própria conta.'];
        }

        $result = $this->coordinator->deleteEmployee($id);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'status' => (int) ($result['status'] ?? 500), 'message' => $result['error'] ?? 'Erro ao excluir colaborador.'];
        }

        $identity = $this->identityService->toArray($result['employee']);

        // Registro tipo "5" do AFD ("E" — exclusão de empregado no REP), a partir do
        // snapshot capturado ANTES da exclusão. Nunca bloqueia o fluxo principal —
        // ver EmployeeAfdEventRecorderService.
        $this->afdEventRecorder->recordExclusion($result['employee'], $currentUser);

        return [
            'success' => true,
            'identity' => $identity,
            'audit' => [
                'action' => 'EMPLOYEE_DELETED',
                'old_values' => ['name' => $identity['name'], 'email' => $identity['email']],
                'new_values' => null,
                'description' => "Colaborador excluído: {$identity['name']} ({$identity['email']})",
            ],
            'message' => 'Colaborador excluído com sucesso.',
        ];
    }

    public function setEmployeeActiveState(int $id, bool $active, ?object $currentUser): array
    {
        if (!$active && $this->isSelfAction($id, $currentUser)) {
            return ['success' => false, 'self_action' => true, 'message' => 'Você não pode desativar sua própria conta.'];
        }

        $result = $this->coordinator->setEmployeeActive($id, $active);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'status' => (int) ($result['status'] ?? 404), 'message' => $result['error'] ?? 'Colaborador não encontrado.'];
        }

        $identity = $this->identityService->toArray($result['employee']);

        // Registro tipo "5" do AFD — reativação conta como "A" (alteração de cadastro);
        // desativação conta como "E" (exclusão/saída do empregado do REP), conforme o
        // racional de operações do leiaute. Nunca bloqueia o fluxo principal — ver
        // EmployeeAfdEventRecorderService.
        if ($active) {
            $this->afdEventRecorder->recordAlteration($result['employee'], $currentUser);
        } else {
            $this->afdEventRecorder->recordExclusion($result['employee'], $currentUser);
        }

        return [
            'success' => true,
            'identity' => $identity,
            'audit' => $active
                ? [
                    'action' => 'EMPLOYEE_ACTIVATED',
                    'old_values' => ['active' => false],
                    'new_values' => ['active' => true],
                    'description' => "Colaborador ativado: {$identity['name']}",
                ]
                : [
                    'action' => 'EMPLOYEE_DEACTIVATED',
                    'old_values' => ['active' => true],
                    'new_values' => ['active' => false],
                    'description' => "Colaborador desativado: {$identity['name']}",
                ],
            'message' => $active ? 'Colaborador ativado com sucesso.' : 'Colaborador desativado com sucesso.',
        ];
    }

    public function approveRegistration(int $id): array
    {
        $result = $this->coordinator->approveRegistration($id);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'status' => (int) ($result['status'] ?? 404), 'message' => $result['error'] ?? 'Colaborador não encontrado.'];
        }

        $identity = $this->identityService->toArray($result['employee']);

        // Registro tipo "5" do AFD ("I" — inclusão de empregado no REP, efetivada na
        // aprovação do cadastro). Este método não recebe `$currentUser` — o recorder
        // resolve o CPF do responsável via `sp_session_user_id()` como fallback. Nunca
        // bloqueia o fluxo principal — ver EmployeeAfdEventRecorderService.
        $this->afdEventRecorder->recordInclusion($result['employee']);

        return [
            'success' => true,
            'audit' => [
                'action' => 'EMPLOYEE_APPROVED',
                'old_values' => ['active' => false],
                'new_values' => ['active' => true],
                'description' => "Cadastro aprovado: {$identity['name']}",
            ],
            'message' => 'Cadastro aprovado com sucesso.',
        ];
    }

    public function rejectRegistration(int $id): array
    {
        $result = $this->coordinator->rejectRegistration($id);
        if (!($result['success'] ?? false)) {
            return ['success' => false, 'status' => (int) ($result['status'] ?? 404), 'message' => $result['error'] ?? 'Colaborador não encontrado.'];
        }

        $identity = $this->identityService->toArray($result['employee']);

        return [
            'success' => true,
            'audit' => [
                'action' => 'EMPLOYEE_REJECTED',
                'old_values' => ['active' => false],
                'new_values' => null,
                'description' => "Cadastro rejeitado: {$identity['name']}",
            ],
            'message' => 'Cadastro rejeitado com sucesso.',
        ];
    }

    public function exportEmployeeData(int $id, ?object $currentUser, bool $isAdmin): array
    {
        if ($id !== (int) ($currentUser->id ?? 0) && !$isAdmin) {
            return ['success' => false, 'status' => 403, 'message' => 'Você não tem permissão para exportar esses dados.'];
        }

        $data = $this->coordinator->exportEmployeeData($id);
        if ($data === null) {
            return ['success' => false, 'status' => 404, 'message' => 'Colaborador não encontrado.'];
        }

        $identity = $this->identityService->toArray($data['employee']);

        return [
            'success' => true,
            'data' => $data,
            'audit' => [
                'action' => 'DATA_EXPORTED',
                'old_values' => null,
                'new_values' => null,
                'description' => "Dados exportados (LGPD): {$identity['name']}",
            ],
            'message' => 'Dados exportados com sucesso.',
        ];
    }

    public function updateProfile(int $employeeId, array $payload): array
    {
        return $this->coordinator->updateProfile($employeeId, $payload);
    }

    public function grantBiometricConsent(int $employeeId, string $ipAddress): array
    {
        return $this->coordinator->grantBiometricConsent($employeeId, $ipAddress);
    }

    public function revokeBiometricConsent(int $employeeId): array
    {
        return $this->coordinator->revokeBiometricConsent($employeeId);
    }

    public function changePassword(object $currentUser, string $currentPassword, string $newPassword): array
    {
        return $this->coordinator->changePassword($currentUser, $currentPassword, $newPassword);
    }
}
