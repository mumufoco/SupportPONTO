<?php

namespace App\Services\Biometric;

use App\Models\BiometricTemplateModel;
use App\Models\EmployeeModel;
use App\Models\SettingModel;
use App\Services\Biometric\DeepFace\DeepFaceApiClient;
use App\Services\Biometric\DeepFace\DeepFacePayloadFactory;
use App\Services\Biometric\DeepFace\DeepFaceResultNormalizer;

/**
 * DeepFace Service
 *
 * Mantém a API pública para workflows biométricos enquanto delega
 * integração, payload e normalização para componentes especializados.
 */
class DeepFaceService
{
    protected SettingModel $settingModel;
    protected BiometricTemplateModel $biometricModel;
    protected EmployeeModel $employeeModel;
    protected DeepFaceApiClient $apiClient;
    protected DeepFacePayloadFactory $payloadFactory;
    protected DeepFaceResultNormalizer $resultNormalizer;
    /** @var list<array{body:?array,status:int}> */
    protected array $mockResponses = [];
    protected bool $loggingEnabled = false;
    protected array $requestLogs = [];
    protected array $recognitionCache = [];

    public function __construct()
    {
        $this->settingModel = new SettingModel();
        $this->biometricModel = new BiometricTemplateModel();
        $this->employeeModel = new EmployeeModel();

        $this->apiClient = new DeepFaceApiClient($this->settingModel);
        $this->payloadFactory = new DeepFacePayloadFactory();
        $this->resultNormalizer = new DeepFaceResultNormalizer();
    }


    /**
     * Test helper for deterministic API responses.
     */
    public function setMockResponse(?array $body, int $statusCode): void
    {
        $this->mockResponses = [[
            'body' => $body,
            'status' => $statusCode,
        ]];
    }

    /**
     * @param list<array{0:?array,1:int}> $responses
     */
    public function setRetryResponses(array $responses): void
    {
        $this->mockResponses = [];

        foreach ($responses as $response) {
            $this->mockResponses[] = [
                'body' => $response[0] ?? null,
                'status' => (int) ($response[1] ?? 0),
            ];
        }
    }

    public function enableLogging(bool $enabled): void
    {
        $this->loggingEnabled = $enabled;
    }

    public function getLogs(): array
    {
        return $this->requestLogs;
    }

    public function recognizeWithThreshold(string $photoBase64, float $threshold): array
    {
        return $this->recognizeFace($photoBase64, $threshold);
    }

    public function recognizeWithCache(string $photoBase64, ?float $customThreshold = null): array
    {
        $cacheKey = sha1($photoBase64 . '|' . (string) ($customThreshold ?? 'default'));

        if (array_key_exists($cacheKey, $this->recognitionCache)) {
            $cached = $this->recognitionCache[$cacheKey];
            $cached['from_cache'] = true;

            return $cached;
        }

        $result = $this->recognizeFace($photoBase64, $customThreshold);
        $this->recognitionCache[$cacheKey] = $result;
        $result['from_cache'] = false;

        return $result;
    }

    public function healthCheck(): array
    {
        return $this->resultNormalizer->health($this->performGet('/health', 5));
    }

    /**
     * Compatibilidade com chamadas legadas em testes/POCs.
     */
    public function setTimeout(int $timeout): void
    {
        $this->apiClient->setTimeout($timeout);
    }

    /**
     * Alias legado.
     */
    public function enroll(int $employeeId, string $photoBase64): array
    {
        return $this->enrollFace($employeeId, $photoBase64);
    }

    public function enrollFace(int $employeeId, string $photoBase64): array
    {
        $employee = $this->employeeModel->find($employeeId);
        if (!$employee) {
            return [
                'success' => false,
                'error' => 'Colaborador não encontrado.',
            ];
        }

        $response = $this->performPost('/enroll', $this->payloadFactory->forEnroll($employeeId, $photoBase64));

        return $this->resultNormalizer->enroll($response);
    }

    public function recognizeFace(string $photoBase64, ?float $customThreshold = null): array
    {
        $threshold = (float) ($customThreshold ?? $this->settingModel->get('deepface_threshold', 0.40));

        $response = $this->performPost('/recognize', $this->payloadFactory->forRecognize($photoBase64, $threshold));
        $normalized = $this->resultNormalizer->recognize($response, $threshold);

        if (!($normalized['success'] ?? false) || !($normalized['recognized'] ?? false)) {
            if (($normalized['similarity'] ?? 0) < $threshold) {
            $normalized['recognized'] = false;
            $normalized['message'] = 'Nenhum rosto reconhecido.';
        }

        return $normalized;
        }

        $employee = $this->employeeModel->find((int) $normalized['employee_id']);
        $normalized['employee'] = $employee;

        return $normalized;
    }

    /**
     * Alias legado do método canônico recognizeFace().
     *
     * O parâmetro $employeeId é ignorado para manter compatibilidade com
     * assinaturas antigas.
     *
     * @deprecated Use recognizeFace() em novos chamadores.
     */
    public function recognize(string $photoBase64, ?int $employeeId = null, ?float $customThreshold = null): array
    {
        return $this->recognizeFace($photoBase64, $customThreshold);
    }

    public function verifyFace(int $employeeId, string $photoBase64): array
    {
        $template = $this->biometricModel
            ->where('employee_id', $employeeId)
            ->where('biometric_type', 'face')
            ->where('active', true)
            ->orderBy('created_at', 'DESC')
            ->first();

        if (!$template) {
            return [
                'success' => false,
                'error' => 'Colaborador não possui cadastro facial.',
            ];
        }

        if (!$template->file_path || !file_exists($template->file_path)) {
            return [
                'success' => false,
                'error' => 'Foto de cadastro facial não encontrada.',
            ];
        }

        $enrolledPhotoBase64 = base64_encode((string) file_get_contents($template->file_path));

        $response = $this->performPost('/verify', $this->payloadFactory->forVerify($photoBase64, $enrolledPhotoBase64));

        return $this->resultNormalizer->verify($response);
    }

    /**
     * Alias legado do fluxo verifyFace(), com suporte à assinatura antiga
     * baseada em duas fotos (photo1, photo2).
     *
     * Método canônico para verificação por colaborador: verifyFace().
     *
     * @param int|string $employeeIdOrPhoto1
     *
     * @deprecated Use verifyFace() para verificação por colaborador.
     *             Este alias existe apenas para compatibilidade com POCs e
     *             testes legados que comparam duas imagens diretamente.
     */
    public function verify($employeeIdOrPhoto1, string $photoBase64): array
    {
        if (is_int($employeeIdOrPhoto1)) {
            return $this->verifyFace($employeeIdOrPhoto1, $photoBase64);
        }

        $response = $this->performPost('/verify', [
            'photo1' => (string) $employeeIdOrPhoto1,
            'photo2' => $photoBase64,
        ]);

        return $this->resultNormalizer->verify($response);
    }

    public function analyzeFace(string $photoBase64): array
    {
        $response = $this->performPost('/analyze', $this->payloadFactory->forAnalyze($photoBase64));

        return $this->resultNormalizer->analyze($response);
    }

    /**
     * Alias legado do método canônico analyzeFace().
     *
     * @deprecated Use analyzeFace() em novos chamadores.
     */
    public function analyze(string $photoBase64): array
    {
        return $this->analyzeFace($photoBase64);
    }

    protected function performGet(string $endpoint, ?int $timeout = null): array
    {
        return $this->performRequest('get', $endpoint, [], $timeout);
    }

    protected function performPost(string $endpoint, array $payload = [], ?int $timeout = null): array
    {
        return $this->performRequest('post', $endpoint, $payload, $timeout);
    }

    protected function performRequest(string $method, string $endpoint, array $payload = [], ?int $timeout = null): array
    {
        $startedAt = microtime(true);
        $response = $this->shiftMockResponse();

        if ($response === null) {
            $response = $method === 'get'
                ? $this->apiClient->get($endpoint, $timeout)
                : $this->apiClient->post($endpoint, $payload, $timeout);
        }

        if ($this->loggingEnabled) {
            $this->requestLogs[] = [
                'request' => [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                ],
                'response' => $response,
                'duration' => microtime(true) - $startedAt,
            ];
        }

        return $response;
    }

    protected function shiftMockResponse(): ?array
    {
        if ($this->mockResponses === []) {
            return null;
        }

        $mock = array_shift($this->mockResponses);
        $statusCode = (int) ($mock['status'] ?? 0);
        $body = $mock['body'] ?? [];

        if ($statusCode <= 0) {
            return [
                'request_success' => false,
                'status_code' => 0,
                'body' => [],
                'exception' => new \RuntimeException('Mocked DeepFace connection failure'),
            ];
        }

        return [
            'request_success' => true,
            'status_code' => $statusCode,
            'body' => is_array($body) ? $body : [],
        ];
    }

    public function deleteFaceByHash(string $imageHash): array
    {
        $response = $this->performPost('/delete', $this->payloadFactory->forDeleteByHash($imageHash));

        if (!($response['request_success'] ?? false)) {
            return $this->resultNormalizer->connectionFailure('delete by hash', $response);
        }

        if (($response['status_code'] ?? 0) >= 400) {
            $error = $response['body']['error'] ?? 'Falha ao excluir biometria facial externa.';

            return [
                'success' => false,
                'error' => $error,
            ];
        }

        return [
            'success' => true,
        ];
    }

    public function deleteFaceEnrollment(int $employeeId): array
    {
        try {
            $templates = $this->biometricModel
                ->where('employee_id', $employeeId)
                ->where('biometric_type', 'face')
                ->findAll();

            if (empty($templates)) {
                return [
                    'success' => true,
                    'message' => 'Nenhum cadastro facial encontrado.',
                ];
            }

            $deletedCount = 0;
            foreach ($templates as $template) {
                if ($template->file_path && file_exists($template->file_path) && unlink($template->file_path)) {
                    $deletedCount++;
                }
            }

            $this->biometricModel
                ->where('employee_id', $employeeId)
                ->where('biometric_type', 'face')
                ->delete();

            $this->employeeModel->update($employeeId, ['has_face_biometric' => false]);

            return [
                'success' => true,
                'deleted_files' => $deletedCount,
                'deleted_records' => count($templates),
            ];
        } catch (\Exception $e) {
            log_message('error', 'DeepFace delete enrollment error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Erro ao excluir cadastro facial.',
                'details' => $e->getMessage(),
            ];
        }
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

        $health = $this->healthCheck();

        return [
            'enrolled_faces' => $enrolledCount,
            'employees_without_face' => $withoutFaceCount,
            'api_status' => $health['success'] ? 'online' : 'offline',
            'api_url' => $this->apiClient->getApiUrl(),
            'model' => $this->settingModel->get('deepface_model', 'VGG-Face'),
            'threshold' => $this->settingModel->get('deepface_threshold', 0.40),
        ];
    }

    public function validateImage(string $base64): array
    {
        return (new \App\Services\Biometric\Face\FaceImageService())->validateImage($base64);
    }

    public function getAvailableModels(): array
    {
        return [
            'VGG-Face' => ['name' => 'VGG-Face', 'accuracy' => 99.65, 'threshold' => 0.40, 'recommended' => true],
            'Facenet' => ['name' => 'Facenet', 'accuracy' => 99.65, 'threshold' => 0.40, 'recommended' => true],
            'Facenet512' => ['name' => 'Facenet512', 'accuracy' => 99.65, 'threshold' => 0.30, 'recommended' => false],
            'OpenFace' => ['name' => 'OpenFace', 'accuracy' => 92.92, 'threshold' => 0.10, 'recommended' => false],
            'DeepFace' => ['name' => 'DeepFace', 'accuracy' => 97.35, 'threshold' => 0.23, 'recommended' => false],
            'DeepID' => ['name' => 'DeepID', 'accuracy' => 97.45, 'threshold' => 0.015, 'recommended' => false],
            'ArcFace' => ['name' => 'ArcFace', 'accuracy' => 99.41, 'threshold' => 0.68, 'recommended' => true],
            'Dlib' => ['name' => 'Dlib', 'accuracy' => 99.38, 'threshold' => 0.07, 'recommended' => false],
        ];
    }
}
