<?php

namespace App\Services\Biometric;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\NotificationModel;
use App\Models\UserConsentModel;
use App\Services\Queue\AsyncJobService;
use App\Services\Biometric\BiometricDataPurgeService;

class FaceRecognitionWorkflowService
{
    private BiometricTemplateModel $biometricModel;
    private EmployeeModel $employeeModel;
    private UserConsentModel $consentModel;
    private NotificationModel $notificationModel;
    private AsyncJobService $asyncJobService;
    private FaceRecognitionService $faceRecognitionService;
    private BiometricDataPurgeService $biometricDataPurgeService;

    public function __construct()
    {
        $this->biometricModel = new BiometricTemplateModel();
        $this->employeeModel = new EmployeeModel();
        $this->consentModel = new UserConsentModel();
        $this->notificationModel = new NotificationModel();
        $this->asyncJobService = new AsyncJobService();
        $this->faceRecognitionService = new FaceRecognitionService();
        $this->biometricDataPurgeService = new BiometricDataPurgeService();
    }


    public function biometricDiagnostics(bool $withConnections = true): array
    {
        return $this->faceRecognitionService->biometricDiagnostics($withConnections);
    }

    public function cleanupOrphanFaceFiles(bool $dryRun = true): array
    {
        return $this->faceRecognitionService->cleanupOrphanFaceFiles($dryRun);
    }

    public function enrollmentPageData(object $currentUser): array
    {
        $hasConsent = $this->consentModel->hasConsent((int) $currentUser->id, 'biometric_data');

        return [
            'hasConsent' => $hasConsent,
            'faceTemplates' => $this->biometricModel
                ->where('employee_id', (int) $currentUser->id)
                ->where('biometric_type', 'face')
                ->orderBy('created_at', 'DESC')
                ->findAll(),
            'fingerprintTemplates' => $this->biometricModel
                ->where('employee_id', (int) $currentUser->id)
                ->where('biometric_type', 'fingerprint')
                ->orderBy('created_at', 'DESC')
                ->findAll(),
            'currentUser' => $currentUser,
        ];
    }

    public function queueEnrollment(int $employeeId, string $photoBase64, bool $force = false, array $auditContext = []): array
    {
        // Accept biometric_face (new LGPD gate) or legacy biometric_data consent
        $hasFaceConsent = $this->consentModel->hasConsent($employeeId, 'biometric_face')
            || $this->consentModel->hasConsent($employeeId, 'biometric_data');
        if (!$hasFaceConsent) {
            return ['success' => false, 'status' => 403, 'message' => 'Colaborador precisa aceitar o termo de consentimento biométrico.'];
        }

        $hasTemplate = (bool) $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->first();

        if ($hasTemplate && ! $force) {
            return ['success' => false, 'status' => 409, 'message' => 'Você já possui biometria facial cadastrada. Exclua o cadastro atual ou solicite recadastro forçado.'];
        }

        $existingTemplate = $hasTemplate
            ? $this->biometricModel
                ->where('employee_id', $employeeId)
                ->where('biometric_type', 'face')
                ->where('active', true)
                ->first()
            : null;

        helper('operational_link');

        $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_FACE_ENROLL, [
            'employee_id' => $employeeId,
            'photo' => $photoBase64,
            'force' => $force,
            'actor_id' => (int) ($auditContext['actor_id'] ?? $employeeId),
            'actor_role' => (string) ($auditContext['actor_role'] ?? ''),
            'request_ip' => (string) ($auditContext['request_ip'] ?? ''),
            'user_agent' => (string) ($auditContext['user_agent'] ?? ''),
            'previous_image_hash' => (string) ($existingTemplate->image_hash ?? ''),
        ], [
            'employee_id' => $employeeId,
            'queue' => 'biometric',
            'priority' => 70,
            'max_attempts' => 3,
        ]);

        return [
            'success' => true,
            'status' => 202,
            'message' => 'Biometria facial enfileirada para processamento.',
            'data' => [
                'job_id' => $job['job_id'],
                'status_url' => sp_async_job_status_url((string) $job['job_id']),
            ],
            'audit_events' => [[
                'action' => 'BIOMETRIC_ENROLLMENT_QUEUED',
                'entity' => 'async_jobs',
                'entity_id' => null,
                'old_values' => null,
                'new_values' => ['type' => 'face', 'job_id' => $job['job_id'], 'force' => $force],
                'description' => 'Cadastro de biometria facial enfileirado via DeepFace',
            ]],
        ];
    }

    public function deleteTemplate(int $templateId, int $actorId, bool $isAdmin): array
    {
        $template = $this->biometricModel->find($templateId);
        if (!$template) {
            return ['success' => false, 'status' => 404, 'message' => 'Template não encontrado.'];
        }

        if ((int) $template->employee_id !== $actorId && !$isAdmin) {
            return ['success' => false, 'status' => 403, 'message' => 'Você não tem permissão para excluir este template.'];
        }

        $deleteResult = $this->faceRecognitionService->deleteFaceEnrollment((int) $template->employee_id);

        return [
            'success' => true,
            'message' => 'Template biométrico excluído com sucesso.',
            'data' => [
                'deleted_files' => $deleteResult['deleted_files'] ?? 0,
                'deleted_records' => $deleteResult['deleted_records'] ?? 0,
            ],
            'audit_events' => [[
                'action' => 'BIOMETRIC_DELETED',
                'entity' => 'biometric_templates',
                'entity_id' => $templateId,
                'old_values' => ['type' => $template->biometric_type],
                'new_values' => null,
                'description' => "Template biométrico excluído: {$template->biometric_type} - DeepFace sync",
            ]],
        ];
    }

    public function deleteUserTemplates(int $employeeId): array
    {
        $templates = $this->biometricModel->where('employee_id', $employeeId)->findAll();
        if (empty($templates)) {
            return ['success' => false, 'status' => 404, 'message' => 'Nenhum template encontrado para este usuário.'];
        }

        $deleteResult = $this->faceRecognitionService->deleteFaceEnrollment($employeeId);
        $this->biometricModel->where('employee_id', $employeeId)->delete();

        return [
            'success' => true,
            'message' => 'Templates do usuário removidos com sucesso.',
            'data' => [
                'deleted_files' => $deleteResult['deleted_files'] ?? [],
                'deleted_records' => $deleteResult['deleted_records'] ?? 0,
            ],
            'audit_events' => [[
                'action' => 'BIOMETRIC_USER_DELETED',
                'entity' => 'biometric_templates',
                'entity_id' => $employeeId,
                'old_values' => ['deleted_count' => count($templates)],
                'new_values' => null,
                'description' => "Templates biométricos removidos para usuário {$employeeId}",
            ]],
        ];
    }

    public function grantConsent(int $employeeId, string $ipAddress): array
    {
        if ($this->consentModel->hasConsent($employeeId, 'biometric_data')) {
            return ['success' => false, 'status' => 400, 'message' => 'Você já consentiu com o uso de dados biométricos.'];
        }

        $consentId = $this->consentModel->insert([
            'employee_id' => $employeeId,
            'consent_type' => 'biometric_data',
            'purpose' => 'Registro de ponto eletrônico através de reconhecimento facial e biometria',
            'legal_basis' => 'Consentimento (Art. 7º, I da LGPD)',
            'granted' => true,
            'granted_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'consent_text' => 'Autorizo o tratamento de meus dados biométricos (facial e digital) para fins de registro de ponto eletrônico.',
            'version' => '1.0',
        ]);

        if (!$consentId) {
            return ['success' => false, 'status' => 500, 'message' => 'Erro ao registrar consentimento.'];
        }

        return [
            'success' => true,
            'status' => 201,
            'message' => 'Consentimento registrado com sucesso!',
            'audit_events' => [[
                'action' => 'CONSENT_GRANTED',
                'entity' => 'user_consents',
                'entity_id' => $consentId,
                'old_values' => null,
                'new_values' => ['consent_type' => 'biometric_data'],
                'description' => 'Consentimento para dados biométricos concedido',
            ]],
        ];
    }

    public function revokeConsent(int $employeeId): array
    {
        $revoked = $this->consentModel->revoke($employeeId, 'biometric_data');
        if (!$revoked) {
            return ['success' => false, 'status' => 500, 'message' => 'Erro ao revogar consentimento.'];
        }

        $purge = $this->biometricDataPurgeService->purgeEmployee($employeeId, ['face', 'fingerprint'], 'workflow_consent_revoked');

        return [
            'success' => true,
            'message' => 'Consentimento revogado. Seus dados biométricos foram removidos.',
            'audit_events' => [[
                'action' => 'CONSENT_REVOKED',
                'entity' => 'user_consents',
                'entity_id' => null,
                'old_values' => null,
                'new_values' => ['consent_type' => 'biometric_data'],
                'description' => 'Consentimento para dados biométricos revogado - templates removidos e DeepFace sincronizado',
            ]],
        ];
    }

    public function testRecognition(int $employeeId, string $photoBase64): array
    {
        $faceTemplate = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->first();

        if (!$faceTemplate) {
            return ['success' => false, 'status' => 404, 'message' => 'Você não possui biometria facial cadastrada.'];
        }

        $result = $this->faceRecognitionService->verifyFace($employeeId, $photoBase64);
        if (!$result['success']) {
            return ['success' => false, 'status' => 500, 'message' => $result['error'] ?? 'Erro ao processar reconhecimento.'];
        }

        $similarity = (float) ($result['similarity'] ?? 0);
        $similarityPercent = round($similarity * 100, 2);

        if (!($result['verified'] ?? false)) {
            $recentFailures = $this->countRecentFailures($employeeId);
            $failures = $recentFailures + 1;
            $auditEvents = [[
                'action' => 'BIOMETRIC_TEST_FAILED',
                'entity' => 'biometric_templates',
                'entity_id' => $faceTemplate->id,
                'old_values' => null,
                'new_values' => [
                    'reason' => 'not_verified',
                    'failures' => $failures,
                    'similarity' => $similarityPercent,
                    'model' => $result['model'] ?? 'DeepFace',
                ],
                'description' => 'Teste de reconhecimento facial falhou via DeepFace',
            ]];

            if ($recentFailures >= 1) {
                $this->faceRecognitionService->deleteFaceEnrollment($employeeId);
                $this->notifyAdminsBiometricFailure($employeeId, 'consecutive_failures');

                $auditEvents[] = [
                    'action' => 'BIOMETRIC_DEACTIVATED',
                    'entity' => 'biometric_templates',
                    'entity_id' => $faceTemplate->id,
                    'old_values' => ['active' => true],
                    'new_values' => ['active' => false],
                    'description' => 'Biometria desativada após 2 falhas consecutivas via DeepFace',
                ];

                return [
                    'success' => false,
                    'status' => 400,
                    'message' => 'Reconhecimento falhou pela 2ª vez. Sua biometria foi desativada. Cadastre novamente.',
                    'errors' => ['disabled' => true, 'failures' => $failures],
                    'audit_events' => $auditEvents,
                ];
            }

            return [
                'success' => true,
                'message' => "Teste falhou. Similaridade: {$similarityPercent}%. Tente com melhor iluminação.",
                'data' => [
                    'recognized' => false,
                    'test_passed' => false,
                    'similarity' => $similarity,
                    'failures' => $failures,
                ],
                'audit_events' => $auditEvents,
            ];
        }

        return [
            'success' => true,
            'message' => "Teste bem-sucedido! Similaridade: {$similarityPercent}%",
            'data' => [
                'recognized' => true,
                'is_current_user' => true,
                'test_passed' => true,
                'similarity' => $similarity,
                'similarity_percent' => $similarityPercent,
                'distance' => $result['distance'] ?? null,
                'model' => $result['model'] ?? 'DeepFace',
            ],
            'audit_events' => [[
                'action' => 'BIOMETRIC_TEST_SUCCESS',
                'entity' => 'biometric_templates',
                'entity_id' => $faceTemplate->id,
                'old_values' => null,
                'new_values' => ['similarity' => $similarityPercent, 'model' => $result['model'] ?? 'DeepFace'],
                'description' => "Teste bem-sucedido via DeepFace - Similaridade: {$similarityPercent}%",
            ]],
        ];
    }

    public function managementData(): array
    {
        $users = $this->employeeModel
            ->select('id, name, email, has_face_biometric, has_fingerprint_biometric, active')
            ->where('active', true)
            ->findAll();

        return [
            'title' => 'Gerenciar Face & Biometria',
            'users' => $users,
            'stats' => [
                'total_users' => count($users),
                'with_face' => count(array_filter($users, static fn($u) => $u->has_face_biometric)),
                'with_fingerprint' => count(array_filter($users, static fn($u) => $u->has_fingerprint_biometric)),
                'with_both' => count(array_filter($users, static fn($u) => $u->has_face_biometric && $u->has_fingerprint_biometric)),
            ],
        ];
    }

    private function countRecentFailures(int $employeeId): int
    {
        $db = \Config\Database::connect();
        $auditTable = (new \App\Models\AuditModel())->getTable();

        $lastSuccess = $db->table($auditTable)
            ->where('user_id', $employeeId)
            ->where('action', 'BIOMETRIC_TEST_SUCCESS')
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get()
            ->getRow();

        $query = $db->table($auditTable)
            ->where('user_id', $employeeId)
            ->where('action', 'BIOMETRIC_TEST_FAILED');

        if ($lastSuccess) {
            $query->where('created_at >', $lastSuccess->created_at);
        } else {
            $query->where('created_at >', date('Y-m-d H:i:s', strtotime('-24 hours')));
        }

        return $query->countAllResults();
    }

    private function notifyAdminsBiometricFailure(int $employeeId, string $reason, ?int $recognizedAs = null): void
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return;
        }

        $message = match ($reason) {
            'consecutive_failures' => "Biometria facial de {$employee->name} (ID: {$employeeId}) foi desativada após 2 falhas consecutivas no teste via DeepFace.",
            'wrong_person_recognized' => "CRÍTICO: Biometria facial de {$employee->name} (ID: {$employeeId}) reconheceu outra pessoa (ID: {$recognizedAs}). Cadastro cancelado via DeepFace.",
            default => "Falha no teste de biometria facial para {$employee->name} (ID: {$employeeId}) via DeepFace.",
        };

        $admins = $this->employeeModel->getByRole('admin');
        foreach ($admins as $admin) {
            $this->notificationModel->insert([
                'user_id' => $admin->id,
                'title' => $reason === 'wrong_person_recognized' ? '🚨 Alerta de Segurança Biométrica' : '⚠️ Falha em Teste Biométrico',
                'message' => $message,
                'type' => $reason === 'wrong_person_recognized' ? 'critical' : 'warning',
                'read' => false,
            ]);
        }
    }
}
