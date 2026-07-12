<?php

namespace App\Services\Biometric;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\UserConsentModel;
use App\Services\Biometric\BiometricDataPurgeService;
use App\Services\Queue\AsyncJobService;

class ApiFaceBiometricService
{
    private BiometricTemplateModel $biometricModel;
    private EmployeeModel $employeeModel;
    private UserConsentModel $consentModel;
    private DeepFaceService $deepFaceService;
    private BiometricDataPurgeService $biometricDataPurgeService;
    private AsyncJobService $asyncJobService;

    public function __construct()
    {
        $this->biometricModel = new BiometricTemplateModel();
        $this->employeeModel = new EmployeeModel();
        $this->consentModel = new UserConsentModel();
        $this->deepFaceService = new DeepFaceService();
        $this->biometricDataPurgeService = new BiometricDataPurgeService();
        $this->asyncJobService = new AsyncJobService();
    }

    public function enrollFace(int $employeeId, string $photoBase64): array
    {
        if (!$this->consentModel->hasConsent($employeeId, 'biometric_data')) {
            return [
                'success' => false,
                'status' => 403,
                'code' => 'consent_required',
                'message' => 'Você precisa consentir com o uso de dados biométricos.',
                'errors' => ['consent_required' => true],
            ];
        }

        $hasTemplate = (bool) $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->first();

        if ($hasTemplate) {
            return [
                'success' => false,
                'status' => 409,
                'code' => 'face_template_exists',
                'message' => 'Você já possui biometria facial cadastrada. Solicite recadastro forçado pelo fluxo administrativo.',
            ];
        }

        $job = $this->asyncJobService->enqueue(AsyncJobService::TYPE_FACE_ENROLL, [
            'employee_id' => $employeeId,
            'photo' => $photoBase64,
            'force' => false,
            'actor_id' => $employeeId,
            'actor_role' => 'api',
            'request_ip' => (service('request') instanceof \CodeIgniter\HTTP\IncomingRequest) ? service('request')->getIPAddress() : '127.0.0.1',
            'user_agent' => (service('request') instanceof \CodeIgniter\HTTP\IncomingRequest) ? service('request')->getUserAgent()->getAgentString() : null,
        ], [
            'employee_id' => $employeeId,
            'queue' => 'biometric',
            'priority' => 80,
            'max_attempts' => 3,
        ]);

        helper('operational_link');

        return [
            'success' => true,
            'status' => 202,
            'message' => 'Biometria facial enfileirada para processamento seguro em background.',
            'data' => [
                'queued' => true,
                'job_id' => $job['job_id'] ?? null,
                'status_url' => sp_api_async_job_status_url((string) ($job['job_id'] ?? '')),
            ],
        ];
    }

    public function testFace(int $employeeId, string $photoBase64): array
    {
        $result = $this->deepFaceService->recognizeFace($photoBase64);
        if (!$result['success']) {
            return [
                'success' => false,
                'status' => 400,
                'code' => 'face_test_failed',
                'message' => $result['error'] ?? 'Erro ao processar reconhecimento facial.',
            ];
        }

        if (!($result['recognized'] ?? false)) {
            return [
                'success' => true,
                'message' => 'Reconhecimento processado.',
                'data' => [
                    'recognized' => false,
                    'message' => 'Rosto não reconhecido.',
                ],
            ];
        }

        $recognizedEmployeeId = (int) ($result['employee_id'] ?? 0);

        return [
            'success' => true,
            'message' => 'Reconhecimento processado.',
            'data' => [
                'recognized' => true,
                'is_current_user' => $recognizedEmployeeId === $employeeId,
                'similarity' => $result['similarity'] ?? 0,
                'distance' => $result['distance'] ?? null,
                'message' => $recognizedEmployeeId === $employeeId
                    ? 'Reconhecimento bem-sucedido!'
                    : 'Reconhecido como outro usuário.',
            ],
        ];
    }

    public function deleteFace(int $employeeId, int $templateId): array
    {
        $template = $this->biometricModel->find($templateId);
        if (!$template) {
            return ['success' => false, 'status' => 404, 'code' => 'template_not_found', 'message' => 'Template não encontrado.'];
        }

        if ((int) $template->employee_id !== $employeeId) {
            return ['success' => false, 'status' => 403, 'code' => 'forbidden', 'message' => 'Acesso negado.'];
        }

        if (!empty($template->image_hash)) {
            $this->deepFaceService->deleteFaceByHash($template->image_hash);
        }

        $this->biometricModel->delete($templateId);

        $remainingTemplates = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->countAllResults();

        if ($remainingTemplates === 0) {
            $this->employeeModel->update($employeeId, ['has_face_biometric' => false]);
        }

        return ['success' => true, 'message' => 'Template biométrico excluído com sucesso.'];
    }

    public function templates(int $employeeId): array
    {
        $templates = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'DESC')
            ->findAll();

        return [
            'success' => true,
            'data' => array_map(static function ($template) {
                return [
                    'id' => $template->id,
                    'biometric_type' => $template->biometric_type,
                    'enrollment_quality' => $template->enrollment_quality,
                    'model_used' => $template->model_used,
                    'active' => $template->active,
                    'created_at' => $template->created_at,
                ];
            }, $templates),
        ];
    }

    public function grantConsent(int $employeeId): array
    {
        if ($this->consentModel->hasConsent($employeeId, 'biometric_data')) {
            return ['success' => false, 'status' => 400, 'code' => 'consent_exists', 'message' => 'Você já consentiu com o uso de dados biométricos.'];
        }

        $consentId = $this->consentModel->insert([
            'employee_id' => $employeeId,
            'consent_type' => 'biometric_data',
            'purpose' => 'Registro de ponto eletrônico através de reconhecimento facial e biometria',
            'legal_basis' => 'Consentimento (Art. 7º, I da LGPD)',
            'granted' => true,
            'granted_at' => date('Y-m-d H:i:s'),
            'ip_address' => get_client_ip(),
            'consent_text' => 'Autorizo o tratamento de meus dados biométricos (facial e digital) para fins de registro de ponto eletrônico.',
            'version' => '1.0',
        ]);

        if (!$consentId) {
            return ['success' => false, 'status' => 500, 'code' => 'consent_persist_failed', 'message' => 'Erro ao registrar consentimento.'];
        }

        return ['success' => true, 'status' => 201, 'message' => 'Consentimento registrado com sucesso!'];
    }

    public function revokeConsent(int $employeeId): array
    {
        $revoked = $this->consentModel->revoke($employeeId, 'biometric_data');
        if (!$revoked) {
            return ['success' => false, 'status' => 500, 'code' => 'consent_revoke_failed', 'message' => 'Erro ao revogar consentimento.'];
        }

        $purge = $this->biometricDataPurgeService->purgeEmployee($employeeId, ['face', 'fingerprint'], 'consent_revoked');

        return [
            'success' => true,
            'message' => 'Consentimento revogado. Seus dados biométricos foram removidos.',
            'data' => [
                'deleted_records' => $purge['deleted_records'] ?? 0,
                'deleted_files' => $purge['deleted_files'] ?? 0,
                'deepface_sync_errors' => $purge['deepface_sync_errors'] ?? [],
            ],
        ];
    }

    public function consentStatus(int $employeeId): array
    {
        return [
            'success' => true,
            'data' => [
                'has_consent' => $this->consentModel->hasConsent($employeeId, 'biometric_data'),
            ],
        ];
    }
}
