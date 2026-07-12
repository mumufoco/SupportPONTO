<?php

namespace App\Services\Biometric\Face;

class FaceDeepFaceClient
{
    private string $apiUrl;
    private \App\Services\Biometric\DeepFaceCircuitBreakerService $circuitBreaker;
    private ?string $apiKey;
    private ?string $internalToken;
    private int $timeout;
    private int $retryAttempts;

    public function __construct(string $apiUrl, ?string $apiKey = null, ?string $internalToken = null, int $timeout = 20, int $retryAttempts = 2)
    {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->internalToken = $internalToken;
        $this->timeout = max(5, $timeout);
        $this->retryAttempts = max(1, $retryAttempts);
        $this->circuitBreaker = new \App\Services\Biometric\DeepFaceCircuitBreakerService();
    }

    private function headers(): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'SupportPONTO/' . (function_exists('app_version') ? app_version() : '1.1.500') . ' FaceDeepFaceClient',
        ];

        if (!empty($this->apiKey)) {
            $headers['X-API-Key'] = $this->apiKey;
        }

        if (!empty($this->internalToken)) {
            $headers['X-Internal-Token'] = $this->internalToken;
        }

        return $headers;
    }

    public function call(string $endpoint, array $data): array
    {
        $gate = $this->circuitBreaker->beforeCall($endpoint);
        if (($gate['allowed'] ?? false) !== true) {
            return [
                'success' => false,
                'recognized' => false,
                'verified' => false,
                'error' => $gate['message'] ?? 'DeepFace temporariamente indisponível.',
                'code' => 'deepface_circuit_open',
                '_circuit_state' => $gate['state'] ?? 'open',
                '_retry_after' => $gate['retry_after'] ?? null,
            ];
        }

        $attempts = max(1, $this->retryAttempts);
        $lastError = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $client = \Config\Services::curlrequest();
                $url = rtrim($this->apiUrl, '/') . $endpoint;

                $response = $client->post($url, [
                    'json' => $data,
                    'timeout' => $this->timeout,
                    'connect_timeout' => min(5, $this->timeout),
                    'http_errors' => false,
                    'headers' => $this->headers(),
                ]);

                $statusCode = $response->getStatusCode();
                $body = json_decode((string) $response->getBody(), true);
                $result = is_array($body) ? $body : ['success' => false, 'error' => 'Resposta inválida da API'];
                $result['_attempt'] = $attempt;
                $result['_status_code'] = $statusCode;

                if ($statusCode >= 500) {
                    $this->circuitBreaker->recordFailure($endpoint, (string) ($result['error'] ?? 'HTTP ' . $statusCode), $statusCode);
                    if ($attempt < $attempts) {
                        usleep(150000 * $attempt);
                        $lastError = $result;
                        continue;
                    }
                } elseif ($statusCode >= 200 && $statusCode < 500 && (($result['success'] ?? true) !== false)) {
                    $this->circuitBreaker->recordSuccess($endpoint);
                }

                return $result;
            } catch (\Exception $e) {
                $lastError = ['success' => false, 'error' => 'Erro de comunicação com DeepFace API.', 'code' => 'deepface_unavailable', '_attempt' => $attempt];
                $this->circuitBreaker->recordFailure($endpoint, $e->getMessage(), 0);
                log_message('error', 'DeepFace API call failed: ' . $e->getMessage());

                if ($attempt < $attempts) {
                    usleep(150000 * $attempt);
                    continue;
                }
            }
        }

        return $lastError ?? ['success' => false, 'error' => 'Erro desconhecido de comunicação com DeepFace API'];
    }
}
