<?php

namespace App\Services\LGPD\Anonymization;

use App\Models\AuditModel;
use App\Models\BiometricTemplateModel;
use App\Models\ChatMessageModel;
use App\Models\EmployeeModel;
use App\Models\JustificationModel;
use App\Models\NotificationModel;
use App\Models\UserConsentModel;
use App\Models\WarningModel;
use App\Services\Audit\AuditMutationService;
use App\Services\Biometric\FaceRecognitionService;

class EmployeeDataAnonymizationProcessor
{
    private EmployeeModel $employeeModel;
    private BiometricTemplateModel $biometricModel;
    private JustificationModel $justificationModel;
    private ChatMessageModel $chatMessageModel;
    private WarningModel $warningModel;
    private UserConsentModel $consentModel;
    private AuditModel $auditModel;
    private NotificationModel $notificationModel;
    private AuditMutationService $auditMutationService;
    private FaceRecognitionService $faceRecognitionService;

    public function __construct(
        ?EmployeeModel $employeeModel = null,
        ?BiometricTemplateModel $biometricModel = null,
        ?JustificationModel $justificationModel = null,
        ?ChatMessageModel $chatMessageModel = null,
        ?WarningModel $warningModel = null,
        ?UserConsentModel $consentModel = null,
        ?AuditModel $auditModel = null,
        ?NotificationModel $notificationModel = null,
        ?AuditMutationService $auditMutationService = null,
        ?FaceRecognitionService $faceRecognitionService = null,
    ) {
        $this->employeeModel = $employeeModel ?? new EmployeeModel();
        $this->biometricModel = $biometricModel ?? new BiometricTemplateModel();
        $this->justificationModel = $justificationModel ?? new JustificationModel();
        $this->chatMessageModel = $chatMessageModel ?? new ChatMessageModel();
        $this->warningModel = $warningModel ?? new WarningModel();
        $this->consentModel = $consentModel ?? new UserConsentModel();
        $this->auditModel = $auditModel ?? new AuditModel();
        $this->notificationModel = $notificationModel ?? new NotificationModel();
        $this->auditMutationService = $auditMutationService ?? AuditMutationService::createDefault();
        $this->faceRecognitionService = $faceRecognitionService ?? new FaceRecognitionService();
    }

    /**
     * @return array{count:int,employee_email:string}
     */
    public function anonymizeAllForEmployee(int $employeeId, array $profile): array
    {
        $count = 0;

        $employeeAnonymization = $this->anonymizeEmployeeData($employeeId);
        $count += $employeeAnonymization['count'];

        $count += $this->anonymizeBiometricData($employeeId);
        $count += $this->anonymizeChatMessages($employeeId);
        $count += $this->anonymizeJustifications($employeeId);
        $count += $this->anonymizeWarnings($employeeId);
        $count += $this->anonymizeAuditLogs($employeeId);
        $count += $this->revokeAllConsents($employeeId);
        $count += $this->deleteNotifications($employeeId);

        return [
            'count' => $count,
            'employee_email' => $employeeAnonymization['email'],
        ];
    }

    public function anonymizeByType(string $dataType, int $employeeId): int
    {
        switch ($dataType) {
            case 'biometric':
                return $this->anonymizeBiometricData($employeeId);
            case 'chat':
                return $this->anonymizeChatMessages($employeeId);
            case 'justifications':
                return $this->anonymizeJustifications($employeeId);
            case 'warnings':
                return $this->anonymizeWarnings($employeeId);
            case 'audit_logs':
                return $this->anonymizeAuditLogs($employeeId);
            default:
                throw new \InvalidArgumentException('Tipo de dado inválido');
        }
    }

    /**
     * @return array{count:int,email:string}
     */
    private function anonymizeEmployeeData(int $employeeId): array
    {
        $anonymizedEmail = sprintf('anonimizado_%d@deleted.local', $employeeId);
        $anonymizedCode = sprintf('ANON-%s', uniqid());

        $updated = $this->employeeModel->update($employeeId, [
            'name' => 'Usuário Anonimizado',
            'email' => $anonymizedEmail,
            'cpf' => '000.000.000-00',
            'phone' => '(00) 00000-0000',
            'unique_code' => $anonymizedCode,
            'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_ARGON2ID),
            'active' => false,
            'anonymized_at' => date('Y-m-d H:i:s'),
            'has_face_biometric' => false,
            'has_fingerprint_biometric' => false,
        ]);

        return ['count' => $updated ? 1 : 0, 'email' => $anonymizedEmail];
    }

    private function anonymizeBiometricData(int $employeeId): int
    {
        $count = 0;
        $templates = $this->biometricModel->where('employee_id', $employeeId)->findAll();
        $hasFaceTemplate = false;

        foreach ($templates as $template) {
            if ($template->biometric_type === 'face') {
                $hasFaceTemplate = true;

                if (!empty($template->file_path)) {
                    $this->deleteWritableFile($template->file_path);
                }
            }

            $this->biometricModel->update($template->id, [
                'active' => false,
                'template_data' => null,
                'file_path' => null,
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
            $count++;
        }

        // CRIT-06 (auditoria): antes, a anonimização LGPD nunca avisava a API de
        // reconhecimento facial — o registro local virava inativo, mas a foto usada para
        // o matching continuava cadastrada em FACES_DB_PATH, plenamente reconhecível.
        if ($hasFaceTemplate) {
            try {
                $this->faceRecognitionService->deleteFaceEnrollment($employeeId);
            } catch (\Throwable $e) {
                log_message('error', "Falha ao sincronizar exclusão facial externa durante anonimização LGPD do funcionário {$employeeId}: " . $e->getMessage());
            }
        }

        return $count;
    }

    private function anonymizeChatMessages(int $employeeId): int
    {
        $this->chatMessageModel
            ->where('sender_id', $employeeId)
            ->set([
                'message' => '[Mensagem anonimizada]',
                'deleted' => true,
            ])
            ->update();

        return $this->chatMessageModel->affectedRows();
    }

    private function anonymizeJustifications(int $employeeId): int
    {
        $justifications = $this->justificationModel->where('employee_id', $employeeId)->findAll();
        $count = 0;

        foreach ($justifications as $justification) {
            $pathsJson = $justification->attachment_paths ?? $justification->attachment_path ?? null;
            if (!empty($pathsJson)) {
                $paths = json_decode((string) $pathsJson, true);
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        if (is_string($path) && $path !== '') {
                            $this->deleteWritableFile($path);
                        }
                    }
                }
            }

            $this->justificationModel->update($justification->id, [
                'reason' => '[Motivo anonimizado]',
                'attachment_paths' => null,
                'attachment_path' => null,
            ]);

            $count++;
        }

        return $count;
    }

    private function anonymizeWarnings(int $employeeId): int
    {
        $warnings = $this->warningModel->where('employee_id', $employeeId)->findAll();
        $count = 0;

        foreach ($warnings as $warning) {
            if (!empty($warning->evidence_files)) {
                $paths = json_decode((string) $warning->evidence_files, true);
                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        if (is_string($path) && $path !== '') {
                            $this->deleteWritableFile($path);
                        }
                    }
                }
            }

            $this->warningModel->update($warning->id, [
                'reason' => '[Motivo anonimizado]',
                'evidence_files' => null,
                'employee_signature' => null,
                'witness_signature' => null,
            ]);

            $count++;
        }

        return $count;
    }

    private function anonymizeAuditLogs(int $employeeId): int
    {
        return (int) $this->auditMutationService->runControlled(function () use ($employeeId): int {
            $this->auditModel
                ->where('user_id', $employeeId)
                ->set([
                    'old_values' => null,
                    'new_values' => null,
                    'description' => '[Descrição anonimizada]',
                ])
                ->update();

            return (int) $this->auditModel->affectedRows();
        }, 'anonymization');
    }

    private function revokeAllConsents(int $employeeId): int
    {
        $this->consentModel
            ->where('employee_id', $employeeId)
            ->where('granted', true)
            ->where('revoked_at', null)
            ->set([
                'granted' => false,
                'revoked_at' => date('Y-m-d H:i:s'),
                'revocation_reason' => 'Anonimização de dados (LGPD)',
            ])
            ->update();

        return $this->consentModel->affectedRows();
    }

    private function deleteNotifications(int $employeeId): int
    {
        $this->notificationModel->where('employee_id', $employeeId)->delete();
        return $this->notificationModel->affectedRows();
    }

    private function deleteWritableFile(string $relativePath): void
    {
        $filePath = WRITEPATH . $relativePath;
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }
}
