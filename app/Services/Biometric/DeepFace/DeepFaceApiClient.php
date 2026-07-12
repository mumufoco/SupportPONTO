<?php

namespace App\Services\Biometric\DeepFace;

use App\Support\BootstrapEnv;

use App\Models\SettingModel;

class DeepFaceApiClient
{
    private SettingModel $settingModel;
    private \App\Services\Biometric\DeepFaceCircuitBreakerService $circuitBreaker;
    private string $apiUrl;
    private int $timeout;
    private int $retryAttempts;

    public function __construct(SettingModel $settingModel)
    {
        $this->settingModel = $settingModel;
        $this->apiUrl = (string) $this->settingModel->get('deepface_api_url', 'http://localhost:5000');
        $this->timeout = (int) $this->settingModel->get('deepface_timeout', env('DEEPFACE_TIMEOUT', 15));
        $this->retryAttempts = max(1, (int) ($this->settingModel->get('deepface_retry_attempts', env('DEEPFACE_RETRY_ATTEMPTS', 2))));
        $this->circuitBreaker = new \App\Services\Biometric\DeepFaceCircuitBreakerService();
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getRetryAttempts(): int
    {
        return $this->retryAttempts;
    }

    public function setRetryAttempts(int $retryAttempts): void
    {
        $this->retryAttempts = max(1, $retryAttempts);
    }

    public function health(?int $timeout = null): array
    {
        $response = $this->get('/health', $timeout);
        $body = $response['body'] ?? [];

        return [
            'success' => ($response['request_success'] ?? false) && (($response['status_code'] ?? 0) >= 200) && (($response['status_code'] ?? 0) < 300),
            'status_code' => $response['status_code'] ?? 0,
            'version' => $body['version'] ?? null,
            'models_loaded' => $body['models_loaded'] ?? null,
            'error' => $body['error'] ?? (($response['exception'] ?? null) instanceof \Throwable ? $response['exception']->getMessage() : null),
        ];
    }

    public function get(string $endpoint, ?int $timeout = null): array
    {
        return $this->request('get', $endpoint, [], $timeout);
    }

    public function post(string $endpoint, array $payload = [], ?int $timeout = null): array
    {
        return $this->request('post', $endpoint, $payload, $timeout);
    }

    private function request(string $method, string $endpoint, array $payload = [], ?int $timeout = null): array
    {
        $gate = $this->circuitBreaker->beforeCall($method . ' ' . $endpoint);
        if (($gate['allowed'] ?? false) !== true) {
            return [
                'request_success' => false,
                'status_code' => 503,
                'body' => [
                    'success' => false,
                    'error' => $gate['message'] ?? 'DeepFace temporariamente indisponível.',
                    'code' => 'deepface_circuit_open',
                    'retry_after' => $gate['retry_after'] ?? null,
                ],
                'circuit_state' => $gate['state'] ?? 'open',
            ];
        }

        $attempts = max(1, $this->retryAttempts);
        $lastFailure = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client = \Config\Services::curlrequest();

                $options = [
                    'headers' => $this->getApiHeaders(),
                    'timeout' => $timeout ?? $this->timeout,
                    'connect_timeout' => min(5, $timeout ?? $this->timeout),
                    'http_errors' => false,
                ];

                if (!empty($payload)) {
                    $options['json'] = $payload;
                }

                $response = $client->{$method}(rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/'), $options);
                $decodedBody = json_decode((string) $response->getBody(), true);
                $statusCode = $response->getStatusCode();

                $result = [
                    'request_success' => true,
                    'status_code' => $statusCode,
                    'body' => is_array($decodedBody) ? $decodedBody : [],
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                ];

                if ($statusCode >= 500) {
                    $this->circuitBreaker->recordFailure($method . ' ' . $endpoint, (string) ($result['body']['error'] ?? 'HTTP ' . $statusCode), $statusCode);
                    if ($attempt < $attempts) {
                        usleep(150000 * $attempt);
                        $lastFailure = $result;
                        continue;
                    }
                } elseif ($statusCode >= 200 && $statusCode < 500) {
                    $this->circuitBreaker->recordSuccess($method . ' ' . $endpoint);
                }

                return $result;
            } catch (\Exception $e) {
                $this->circuitBreaker->recordFailure($method . ' ' . $endpoint, $e->getMessage(), 0);
                $lastFailure = [
                    'request_success' => false,
                    'status_code' => 0,
                    'body' => ['success' => false, 'error' => 'DeepFace indisponível.', 'code' => 'deepface_unavailable'],
                    'exception' => $e,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                ];

                if ($attempt < $attempts) {
                    usleep(150000 * $attempt);
                    continue;
                }
            }
        }

        return $lastFailure ?? [
            'request_success' => false,
            'status_code' => 0,
            'body' => [],
            'attempt' => $attempts,
            'max_attempts' => $attempts,
        ];
    }

    private function getApiHeaders(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SupportPONTO/' . (function_exists('app_version') ? app_version() : '1.1.500') . ' DeepFaceClient',
        ];

        $apiKey = BootstrapEnv::get('DEEPFACE_API_KEY') ?: $this->settingModel->get('deepface_api_key');
        if ($apiKey) {
            $headers['X-API-Key'] = $apiKey;
        }

        $internalToken = BootstrapEnv::get('DEEPFACE_INTERNAL_TOKEN') ?: $this->settingModel->get('deepface_internal_token');
        if ($internalToken) {
            $headers['X-Internal-Token'] = $internalToken;
        }

        return $headers;
    }
}
