<?php

namespace App\Services\Biometric;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Services\Biometric\Face\FaceDeepFaceClient;
use App\Services\Biometric\Face\FaceImageService;
use App\Services\Biometric\Face\FaceRecognitionLogService;
use App\Services\Biometric\Face\FaceTemplateService;

class FaceRecognitionService
{
    protected BiometricTemplateModel $biometricModel;
    protected EmployeeModel $employeeModel;
    protected SettingModel $settingModel;
    protected FacialRecognitionCache $cache;

    protected FaceImageService $faceImageService;
    protected FaceTemplateService $faceTemplateService;
    protected FaceRecognitionLogService $faceRecognitionLogService;
    protected FaceDeepFaceClient $faceDeepFaceClient;

    protected string $storagePath;
    protected float $similarityThreshold;

    public function __construct()
    {
        $this->biometricModel = new BiometricTemplateModel();
        $this->employeeModel = new EmployeeModel();
        $this->settingModel = new SettingModel();
        $this->cache = new FacialRecognitionCache();

        $this->storagePath = WRITEPATH . 'uploads/faces/';
        $this->similarityThreshold = (float) ($this->settingModel->get('facial_recognition_threshold') ?? 0.70);
        $deepFaceApiUrl = (string) ($this->settingModel->get('deepface_api_url') ?? 'http://localhost:5000');
        $deepFaceApiKey = env('DEEPFACE_API_KEY') ?: $this->settingModel->get('deepface_api_key');
        $deepFaceInternalToken = env('DEEPFACE_INTERNAL_TOKEN') ?: $this->settingModel->get('deepface_internal_token');
        $deepFaceTimeout = (int) ($this->settingModel->get('deepface_timeout') ?? env('DEEPFACE_TIMEOUT', 20));
        $deepFaceRetryAttempts = (int) ($this->settingModel->get('deepface_retry_attempts') ?? env('DEEPFACE_RETRY_ATTEMPTS', 2));

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $this->faceImageService = new FaceImageService();
        $this->faceTemplateService = new FaceTemplateService($this->biometricModel, $this->employeeModel);
        $this->faceRecognitionLogService = new FaceRecognitionLogService();
        $this->faceDeepFaceClient = new FaceDeepFaceClient($deepFaceApiUrl, is_string($deepFaceApiKey) ? $deepFaceApiKey : null, is_string($deepFaceInternalToken) ? $deepFaceInternalToken : null, $deepFaceTimeout, $deepFaceRetryAttempts);
    }

    public function enrollFace(int $employeeId, string $photoBase64, bool $force = false, array $auditContext = []): array
    {
        if (!$this->employeeModel->find($employeeId)) {
            return ['success' => false, 'error' => 'Colaborador não encontrado.'];
        }

        $validation = $this->faceImageService->validateImage($photoBase64);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }

        $cleanPhoto = $this->faceImageService->cleanBase64($photoBase64);
        $response = $this->callDeepFaceAPI('/enroll', [
            'image' => $cleanPhoto,
            'photo' => $cleanPhoto,
            'employee_id' => (string) $employeeId,
            'force' => $force,
            'requester_id' => isset($auditContext['actor_id']) ? (string) $auditContext['actor_id'] : null,
            'requester_role' => $auditContext['actor_role'] ?? null,
            'requester_ip' => $auditContext['request_ip'] ?? null,
        ]);

        if (!$response['success']) {
            $this->logRecognitionAttempt($employeeId, false, 0, 'enroll', $response['error'] ?? 'Erro no DeepFace API');
            return ['success' => false, 'error' => $response['error'] ?? 'Erro ao processar imagem no DeepFace.'];
        }

        $imageStore = $this->faceImageService->storeBackupImage($this->storagePath, $employeeId, $photoBase64);
        if (!($imageStore['success'] ?? false)) {
            return ['success' => false, 'error' => $imageStore['error'] ?? 'Erro ao salvar imagem facial.'];
        }

        $descriptor = $response['descriptor'] ?? [
            'provider' => 'deepface-api',
            'employee_id' => (string) $employeeId,
            'facial_area' => $response['facial_area'] ?? null,
        ];

        $quality = (float) ($response['quality'] ?? ($response['confidence'] ?? 1.0));
        $persist = $this->faceTemplateService->persistEnrollment(
            $employeeId,
            $descriptor,
            (string) $imageStore['file_path'],
            (string) $imageStore['image_hash'],
            $quality,
            $force
        );

        if (!($persist['success'] ?? false)) {
            return ['success' => false, 'error' => $persist['error'] ?? 'Erro ao salvar cadastro facial.'];
        }

        $this->cache->invalidateEmployee($employeeId);
        $rebuild = $this->rebuildDeepFaceDatabase();
        $this->logRecognitionAttempt($employeeId, true, 1.0, $force ? 're_enroll' : 'enroll');

        return [
            'success' => true,
            'template_id' => $persist['template_id'],
            'image_hash' => $imageStore['image_hash'],
            'previous_image_hash' => $response['previous_image_hash'] ?? null,
            'confidence' => $quality,
            'model' => 'DeepFace',
            'rebuild' => $rebuild,
            're_enroll' => $force,
        ];
    }

    public function verifyFace(int $employeeId, string $photoBase64): array
    {
        if (!$this->faceTemplateService->activeTemplate($employeeId)) {
            return ['success' => false, 'verified' => false, 'error' => 'Colaborador não possui cadastro facial.'];
        }

        $validation = $this->faceImageService->validateImage($photoBase64);
        if (!$validation['valid']) {
            return ['success' => false, 'verified' => false, 'error' => $validation['error']];
        }

        $cleanPhoto = $this->faceImageService->cleanBase64($photoBase64);

        $response = $this->callDeepFaceAPI('/recognize', [
            'photo' => $cleanPhoto,
            'image' => $cleanPhoto,
            'threshold' => $this->similarityThreshold,
        ]);

        if (isset($response['recognized'])) {
            return $this->normalizeRecognizeVerification($employeeId, $response);
        }

        return $this->verifyByLegacyEndpoint($employeeId, $cleanPhoto);
    }

    public function recognizeFace(string $photoBase64): array
    {
        $validation = $this->faceImageService->validateImage($photoBase64);
        if (!$validation['valid']) {
            return ['success' => false, 'recognized' => false, 'error' => $validation['error']];
        }

        $imageHash = hash('sha256', $photoBase64);
        $cached = $this->cache->get($imageHash);
        if ($cached) {
            return $cached['result'];
        }

        $response = $this->callDeepFaceAPI('/recognize', [
            'photo' => $this->faceImageService->cleanBase64($photoBase64),
        ]);

        if (!$response['success']) {
            $result = ['success' => false, 'recognized' => false, 'error' => $response['error'] ?? 'Erro no DeepFace API'];
            $this->cache->set($imageHash, $result, false);
            return $result;
        }

        if (!($response['recognized'] ?? false)) {
            $result = ['success' => true, 'recognized' => false, 'message' => 'Nenhum rosto reconhecido.'];
            $this->cache->set($imageHash, $result, false);
            return $result;
        }

        $employee = $this->employeeModel->find((int) $response['employee_id']);
        if (!$employee || !$employee->active) {
            $result = ['success' => true, 'recognized' => false, 'message' => 'Colaborador não encontrado ou inativo.'];
            $this->cache->set($imageHash, $result, false);
            return $result;
        }

        $similarity = (float) ($response['similarity'] ?? 0);
        $this->logRecognitionAttempt((int) $employee->id, true, $similarity, 'recognize');

        $result = [
            'success' => true,
            'recognized' => true,
            'employee_id' => $employee->id,
            'employee' => $employee,
            'similarity' => round($similarity, 4),
            'distance' => isset($response['distance']) ? (float) $response['distance'] : null,
            'model' => 'DeepFace',
            'threshold_used' => $this->similarityThreshold,
        ];

        $this->cache->set($imageHash, $result, true);
        return $result;
    }

    public function deleteFaceEnrollment(int $employeeId): array
    {
        $result = $this->faceTemplateService->deleteEnrollment($employeeId);
        $this->cache->invalidateEmployee($employeeId);
        $this->callDeepFaceAPI('/delete', ['employee_id' => $employeeId]);
        $result['rebuild'] = $this->rebuildDeepFaceDatabase();

        return $result;
    }



    public function biometricDiagnostics(bool $withConnections = true): array
    {
        $service = new BiometricProductionReadinessService();
        return $service->diagnostics($withConnections);
    }

    public function cleanupOrphanFaceFiles(bool $dryRun = true): array
    {
        $service = new BiometricProductionReadinessService();
        return $service->cleanupOrphanFaceFiles($dryRun);
    }

    public function healthCheck(): array
    {
        $deepFaceHealth = $this->callDeepFaceAPI('/health', []);
        $success = $deepFaceHealth['success'] ?? false;

        return [
            'success' => $success,
            'status' => $success ? 'online' : 'degraded',
            'deepface_api' => $success,
            'gd_extension' => extension_loaded('gd'),
            'storage_writable' => is_writable($this->storagePath),
            'storage_path' => $this->storagePath,
            'threshold' => $this->similarityThreshold,
            'circuit_breaker' => (new DeepFaceCircuitBreakerService())->state(),
        ];
    }

    public function getStatistics(): array
    {
        $enrolledCount = $this->biometricModel
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->countAllResults();

        $withoutFaceCount = $this->employeeModel
            ->where('active', true)
            ->where('role !=', 'admin')
            ->where('has_face_biometric', false)
            ->countAllResults();

        return [
            'enrolled_faces' => $enrolledCount,
            'employees_without_face' => $withoutFaceCount,
            'api_status' => $this->healthCheck()['status'],
            'threshold' => $this->similarityThreshold,
            'cache_metrics' => $this->cache->getMetrics(),
        ];
    }

    public function validateImage(string $base64): array
    {
        return $this->faceImageService->validateImage($base64);
    }

    protected function callDeepFaceAPI(string $endpoint, array $data): array
    {
        return $this->faceDeepFaceClient->call($endpoint, $data);
    }

    protected function logRecognitionAttempt(int $employeeId, bool $success, float $similarity, string $action, ?string $errorMessage = null): void
    {
        $this->faceRecognitionLogService->logAttempt($employeeId, $success, $similarity, $this->similarityThreshold, $action, $errorMessage);
    }

    public function setThreshold(float $threshold): self
    {
        $this->similarityThreshold = max(0.5, min(0.99, $threshold));
        return $this;
    }

    public function getThreshold(): float
    {
        return $this->similarityThreshold;
    }

    private function normalizeRecognizeVerification(int $employeeId, array $response): array
    {
        $recognizedEmployeeId = isset($response['employee_id']) ? (int) $response['employee_id'] : null;
        $similarity = (float) ($response['similarity'] ?? 0);
        $verified = ($response['recognized'] === true) && ($recognizedEmployeeId === $employeeId);

        $this->logRecognitionAttempt(
            $employeeId,
            $verified,
            $similarity,
            'verify',
            $verified ? null : ($response['message'] ?? 'Rosto não corresponde ao colaborador')
        );

        return [
            'success' => true,
            'verified' => $verified,
            'recognized' => (bool) $response['recognized'],
            'recognized_employee_id' => $recognizedEmployeeId,
            'similarity' => round($similarity, 4),
            'threshold' => $this->similarityThreshold,
            'distance' => isset($response['distance']) ? (float) $response['distance'] : null,
            'model' => $response['model'] ?? 'DeepFace',
            'message' => $response['message'] ?? null,
        ];
    }

    private function rebuildDeepFaceDatabase(): array
    {
        $response = $this->callDeepFaceAPI('/admin/rebuild-db', []);

        if (! ($response['success'] ?? false)) {
            log_message('warning', 'DeepFace database rebuild failed: {error}', ['error' => $response['error'] ?? 'unknown']);
        }

        return [
            'success' => (bool) ($response['success'] ?? false),
            'rebuilt' => (bool) ($response['rebuilt'] ?? false),
            'indexed_faces' => (int) ($response['indexed_faces'] ?? 0),
            'message' => $response['message'] ?? null,
        ];
    }

    private function verifyByLegacyEndpoint(int $employeeId, string $cleanPhoto): array
    {
        $response = $this->callDeepFaceAPI('/verify', [
            'image' => $cleanPhoto,
            'employee_id' => (string) $employeeId,
        ]);

        if (!($response['success'] ?? false)) {
            $this->logRecognitionAttempt($employeeId, false, 0, 'verify', $response['error'] ?? 'Erro no DeepFace API');
            return ['success' => false, 'verified' => false, 'error' => $response['error'] ?? 'Erro na verificação.'];
        }

        $verified = (bool) ($response['verified'] ?? false);
        $similarity = (float) ($response['similarity'] ?? 0);

        $this->logRecognitionAttempt($employeeId, $verified, $similarity, 'verify');

        return [
            'success' => true,
            'verified' => $verified,
            'similarity' => round($similarity, 4),
            'threshold' => $this->similarityThreshold,
            'distance' => isset($response['distance']) ? (float) $response['distance'] : null,
            'model' => 'DeepFace',
        ];
    }
}
