<?php

namespace App\Services\Biometric\SourceAFIS;

use CodeIgniter\HTTP\CURLRequest;
use Exception;

class SourceAFISApiClient
{
    private CURLRequest $client;

    public function __construct(
        private readonly string $apiUrl,
        private readonly int $timeout,
    ) {
        $this->client = \Config\Services::curlrequest([
            'timeout' => $this->timeout,
            'http_errors' => false,
        ]);
    }

    public function health(): array
    {
        try {
            $response = $this->client->get($this->apiUrl . '/health', ['timeout' => 5]);
            return [
                'request_success' => true,
                'status_code' => $response->getStatusCode(),
                'body' => $this->decodeJson((string) $response->getBody()),
            ];
        } catch (Exception $e) {
            return [
                'request_success' => false,
                'status_code' => 0,
                'body' => [],
                'exception' => $e,
            ];
        }
    }

    public function extract(string $imageBase64): array
    {
        return $this->post('/extract', ['image' => $imageBase64]);
    }

    public function compare(string $template1, string $template2): array
    {
        return $this->post('/compare', [
            'template1' => $template1,
            'template2' => $template2,
        ]);
    }

    private function post(string $endpoint, array $payload): array
    {
        try {
            $response = $this->client->post($this->apiUrl . $endpoint, ['json' => $payload]);

            return [
                'request_success' => true,
                'status_code' => $response->getStatusCode(),
                'body' => $this->decodeJson((string) $response->getBody()),
            ];
        } catch (Exception $e) {
            return [
                'request_success' => false,
                'status_code' => 0,
                'body' => [],
                'exception' => $e,
            ];
        }
    }

    private function decodeJson(string $body): array
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }
}
