<?php

namespace App\Services\Warning;

use App\Models\AuditModel;
use App\Models\EmployeeModel;
use App\Models\WarningModel;
use App\Models\WarningWitnessModel;
use App\Services\NotificationService;
use App\Services\SMSService;
use App\Services\Warning\Workflow\WarningDocumentService;
use App\Services\Warning\Workflow\WarningEvidenceService;
use App\Services\Warning\Workflow\WarningNotificationService;
use App\Services\Warning\Workflow\WarningSignatureService;
use App\Services\WarningPDFService;
use Config\Services;

class WarningWorkflowService
{
    private WarningEvidenceService $warningEvidenceService;
    private WarningSignatureService $warningSignatureService;
    private WarningDocumentService $warningDocumentService;
    private WarningNotificationService $warningNotificationService;
    private WarningWitnessModel $warningWitnessModel;

    public function __construct(
        private readonly WarningModel $warningModel,
        private readonly EmployeeModel $employeeModel,
        private readonly AuditModel $auditModel,
        private readonly NotificationService $notificationService,
        private readonly WarningPDFService $pdfService,
        private readonly SMSService $smsService,
        ?WarningEvidenceService $warningEvidenceService = null,
        ?WarningSignatureService $warningSignatureService = null,
        ?WarningDocumentService $warningDocumentService = null,
        ?WarningNotificationService $warningNotificationService = null,
        ?WarningWitnessModel $warningWitnessModel = null
    ) {
        $this->warningWitnessModel = $warningWitnessModel ?? new WarningWitnessModel();
        $this->warningEvidenceService = $warningEvidenceService ?? Services::warningEvidenceService(false);
        $this->warningSignatureService = $warningSignatureService ?? Services::warningSignatureService(false);
        $this->warningDocumentService = $warningDocumentService ?? Services::warningDocumentService(false);
        $this->warningNotificationService = $warningNotificationService ?? Services::warningNotificationWorkflowService(false);
    }

    private function buildCreatePayload(array $actor, array $input, array $uploadResult): array
    {
        return [
            'employee_id' => (int) $input['employee_id'],
            'issued_by' => (int) $actor['id'],
            'warning_type' => $input['warning_type'],
            'occurrence_date' => $input['occurrence_date'],
            'reason' => $input['reason'],
            'evidence_files' => $uploadResult['files'],
            'status' => 'pendente-assinatura',
        ];
    }

    private function resolveWarningActors(int $employeeId, int $issuerId): array
    {
        return [
            'targetEmployee' => $this->employeeModel->find($employeeId),
            'issuer' => $this->employeeModel->find($issuerId),
        ];
    }

    public function createWarning(array $actor, array $input, array $files): array
    {
        $employeeId = (int) $input['employee_id'];

        if ($this->warningModel->isAtLimit($employeeId)) {
            return ['success' => false, 'warning' => 'ATENÇÃO: Este colaborador já possui 3 advertências. Considere medidas adicionais.'];
        }

        $uploadResult = $this->warningEvidenceService->handleEvidenceUploads($files);
        if (!($uploadResult['success'] ?? false)) {
            return ['success' => false, 'error' => implode('<br>', $uploadResult['errors'] ?? [])];
        }

        $data = $this->buildCreatePayload($actor, $input, $uploadResult);

        $warningId = $this->warningModel->insert($data);
        if (!$warningId) {
            return ['success' => false, 'error' => 'Erro ao criar advertência.'];
        }

        ['targetEmployee' => $targetEmployee, 'issuer' => $issuer] = $this->resolveWarningActors($employeeId, (int) $actor['id']);

        $this->warningDocumentService->generateInitialPdf((int) $warningId, (int) $actor['id'], $targetEmployee, $issuer);
        $this->auditIssue($actor, (int) $warningId, $data, $employeeId);

        if ($targetEmployee) {
            $warningRecord = $this->warningModel->find((int) $warningId);
            $this->warningNotificationService->notifyTargetEmployee(
                $targetEmployee,
                (string) $input['warning_type'],
                (int) $warningId,
                $employeeId,
                $issuer,
                $warningRecord
            );
        }

        return ['success' => true, 'warningId' => $warningId];
    }

    public function signWarning(array $actor, object $warning, array $input, $certificateFile = null): array
    {
        if (empty($input['terms_accepted'])) {
            return ['success' => false, 'message' => 'Você deve aceitar os termos para assinar.'];
        }

        $signatureMethod = $input['signature_method'] ?? null;
        if (!$signatureMethod) {
            return ['success' => false, 'message' => 'Método de assinatura inválido.'];
        }

        $signatureResult = $this->warningSignatureService->resolveSignature($actor, $warning, (string) $signatureMethod, $input, $certificateFile);
        if (!($signatureResult['success'] ?? false)) {
            return $signatureResult;
        }

        $signature = $signatureResult['signature'];
        $this->warningModel->sign((int) $warning->id, $signature);

        $targetEmployee = $this->employeeModel->find($warning->employee_id);
        $issuer = $this->employeeModel->find($warning->issued_by);
        $this->warningDocumentService->regenerateFinalPdf((int) $warning->id, $targetEmployee, $issuer);

        $this->auditModel->log(
            $actor['id'],
            'WARNING_SIGNED',
            'warnings',
            $warning->id,
            ['status' => 'pendente-assinatura'],
            ['status' => 'assinado', 'signature' => $signature],
            "Advertência ID {$warning->id} assinada pelo colaborador",
            'info'
        );

        $this->warningNotificationService->notifyIssuerWarningSigned((int) $warning->issued_by, (string) $actor['name'], (int) $warning->id);

        return ['success' => true, 'message' => 'Advertência assinada com sucesso.'];
    }

    public function sendSMSCode(array $actor): array
    {
        $targetEmployee = $this->employeeModel->find($actor['id']);

        if (!$targetEmployee || !$targetEmployee->phone) {
            return ['success' => false, 'message' => 'Número de telefone não cadastrado.'];
        }

        return $this->smsService->sendVerificationCode($actor['id'], $targetEmployee->phone);
    }

    /**
     * @param list<array{name:string,cpf:string,signature:string}> $witnesses Exatamente 2
     *  testemunhas (orientação jurídica), ver WarningControllerActionService::witnessesFromPayload().
     */
    public function refuseWithWitness(array $actor, object $warning, array $witnesses): array
    {
        $this->warningModel->markRefused((int) $warning->id);

        foreach ($witnesses as $witness) {
            $this->warningWitnessModel->insert([
                'warning_id'        => (int) $warning->id,
                'witness_name'      => $witness['name'],
                'witness_cpf'       => $witness['cpf'],
                'witness_signature' => $witness['signature'],
            ]);
        }

        $targetEmployee = $this->employeeModel->find($warning->employee_id);
        $issuer = $this->employeeModel->find($warning->issued_by);
        $this->warningDocumentService->regenerateFinalPdf((int) $warning->id, $targetEmployee, $issuer);

        $this->auditModel->log(
            $actor['id'],
            'WARNING_REFUSED',
            'warnings',
            $warning->id,
            ['status' => 'pendente-assinatura'],
            ['status' => 'recusado'],
            "Advertência ID {$warning->id} marcada como recusada com 2 testemunhas",
            'warning'
        );

        $admins = $this->employeeModel->where('role', 'admin')->findAll();
        if ($targetEmployee) {
            $this->warningNotificationService->notifyAdminsRefusal($admins, $targetEmployee, (int) $warning->id);
        }

        return ['success' => true, 'message' => 'Testemunhas adicionadas. Advertência marcada como recusada.'];
    }

    public function deleteWarning(array $actor, object $warning): void
    {
        // Soft delete preserva evidências e PDF para trilha de auditoria e eventual restauração.

        $this->auditModel->log(
            $actor['id'],
            'WARNING_DELETED',
            'warnings',
            $warning->id,
            (array) $warning,
            null,
            "Advertência ID {$warning->id} excluída",
            'critical'
        );

        $this->warningModel->delete($warning->id);
    }

    private function auditIssue(array $actor, int $warningId, array $data, int $employeeId): void
    {
        $this->auditModel->log(
            $actor['id'],
            'WARNING_ISSUED',
            'warnings',
            $warningId,
            null,
            $data,
            "Advertência {$data['warning_type']} emitida para colaborador ID {$employeeId}",
            'warning'
        );
    }
}
